<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Storage\MySQL;

use Cms\Storage\MySQL\AbstractMapper;
use Krystal\Db\Sql\RawSqlFragment;

/**
 * Persists conversation participant rows.
 */
final class ParticipantMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_conversation_participants');
    }

    /**
     * {@inheritDoc}
     */
    protected function getPk()
    {
        return 'conversation_id';
    }

    /**
     * Inserts a single participant row.
     */
    public function addParticipant($conversationId, $memberId, $role = 'member')
    {
        $db = $this->db->insert(self::getTableName(), [
            'conversation_id' => (int) $conversationId,
            'member_id' => (int) $memberId,
            'role' => $role,
            'joined_at' => date('Y-m-d H:i:s')
        ]);

        return $db->execute();
    }

    /**
     * Inserts multiple participant rows for a conversation.
     */
    public function addParticipants($conversationId, array $memberIds, $role = 'member')
    {
        $ok = true;

        foreach ($memberIds as $memberId) {
            $ok = $this->addParticipant($conversationId, (int) $memberId, $role) && $ok;
        }

        return $ok;
    }

    /**
     * Determines whether a member belongs to a conversation.
     */
    public function isParticipant($conversationId, $memberId)
    {
        $db = $this->db->select()
                       ->count('member_id', 'count')
                       ->from(self::getTableName())
                       ->whereEquals('conversation_id', (int) $conversationId)
                       ->andWhereEquals('member_id', (int) $memberId);

        return (int) $db->query('count') > 0;
    }

    /**
     * Advances a member's read cursor up to a given message.
     *
     * Never moves the cursor backwards. Uses raw SQL because the
     * query builder cannot express "IS NULL OR <" in one statement.
     */
    public function setLastReadMessageId($conversationId, $memberId, $messageId)
    {
        $now = date('Y-m-d H:i:s');

        $sql = sprintf(
            'UPDATE %s SET last_read_message_id = %d, last_read_at = %s '
          . 'WHERE conversation_id = %d AND member_id = %d '
          . 'AND (last_read_message_id IS NULL OR last_read_message_id < %d)',
            self::getTableName(),
            (int) $messageId,
            $this->db->getPdo()->quote($now),
            (int) $conversationId,
            (int) $memberId,
            (int) $messageId
        );

        return $this->db->raw($sql)->execute(true) > 0;
    }

    /**
     * Fetches the participant row for a member in a conversation.
     */
    public function findOne($conversationId, $memberId)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('conversation_id', (int) $conversationId)
                       ->andWhereEquals('member_id', (int) $memberId);

        return $db->query();
    }

    /**
     * Fetches all participant rows for a conversation.
     */
    public function fetchByConversation($conversationId)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('conversation_id', (int) $conversationId);

        return $db->queryAll();
    }

    /**
     * Fetches the other participant of a direct conversation.
     */
    public function findOtherParticipant($conversationId, $memberId)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('conversation_id', (int) $conversationId)
                       ->andWhereNotEquals('member_id', (int) $memberId)
                       ->limit(1);

        return $db->query();
    }

    /**
     * Counts unread messages in a conversation for a member.
     *
     * Excludes messages sent by the member themselves — a message
     * you wrote is not unread by you.
     */
    public function countUnread($conversationId, $memberId, $lastReadMessageId)
    {
        $msg = MessageMapper::getTableName();

        $sql = sprintf(
            'SELECT COUNT(*) AS count FROM %s '
          . 'WHERE conversation_id = %d '
          . 'AND deleted_at IS NULL '
          . 'AND sender_id != %d',
            $msg,
            (int) $conversationId,
            (int) $memberId
        );

        if ($lastReadMessageId > 0) {
            $sql .= sprintf(' AND id > %d', (int) $lastReadMessageId);
        }

        $result = $this->db->raw($sql)->query();

        return isset($result['count']) ? (int) $result['count'] : 0;
    }

    /**
     * Fetches the inbox rows for a member.
     */
    public function fetchInboxForMember($memberId)
    {
        $p = self::getTableName();
        $c = ConversationMapper::getTableName();
        $m = MemberMapper::getTableName();
        $msg = MessageMapper::getTableName();

        // 1. Fetch base conversations
        $db = $this->db->select([
                'p.conversation_id',
                'p.last_read_message_id',
                'p.muted_until',
                'c.type',
                'c.title',
                'c.last_message_id',
                'c.last_message_at',
                'c.message_count'
            ])
            ->from($p, 'p')
            ->innerJoin($c . ' AS c', ['c.id' => new RawSqlFragment('p.conversation_id')])
            ->whereEquals('p.member_id', (int) $memberId)
            ->andWhereEquals('p.is_archived', 0);

        $rows = $db->queryAll();

        if (!$rows) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $conversationId = (int) $row['conversation_id'];

            // 2. Fetch the other participant
            $dbOther = $this->db->select([
                    'p.member_id',
                    'm.name',
                    'm.login',
                    'm.avatar'
                ])
                ->from($p, 'p')
                ->innerJoin($m . ' AS m', ['m.id' => new RawSqlFragment('p.member_id')])
                ->whereEquals('p.conversation_id', $conversationId)
                ->andWhereNotEquals('p.member_id', (int) $memberId)
                ->limit(1);

            $other = $dbOther->query();

            // 3. Fetch the last live message
            $last = null;

            if (!empty($row['last_message_id'])) {
                $sql = sprintf(
                    'SELECT body, sender_id, created_at FROM %s WHERE id = %d AND deleted_at IS NULL',
                    $msg,
                    (int) $row['last_message_id']
                );
                $last = $this->db->raw($sql)->query();
            }

            // 4. Calculate unread count
            $unreadCount = $this->countUnread($conversationId, (int) $memberId, $row['last_read_message_id']);

            $result[] = [
                'conversation_id' => $conversationId,
                'type' => $row['type'],
                'title' => $row['title'],
                'last_message_id' => $row['last_message_id'],
                'last_message_at' => $row['last_message_at'],
                'message_count' => (int) $row['message_count'],
                'last_read_message_id' => $row['last_read_message_id'],
                'muted_until' => $row['muted_until'],
                'unread_count' => $unreadCount,
                'other' => $other ?: null,
                'last_message' => $last ?: null
            ];
        }

        // 5. Sort, handling null timestamps safely
        usort($result, function ($a, $b) {
            $timeA = $a['last_message_at'] ? strtotime($a['last_message_at']) : 0;
            $timeB = $b['last_message_at'] ? strtotime($b['last_message_at']) : 0;
            return $timeB - $timeA;
        });

        return $result;
    }
}