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
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id
     * @param string $role Participant role
     * @return boolean Depending on success
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
     *
     * @param int $conversationId Conversation id
     * @param array $memberIds List of member ids
     * @param string $role Participant role
     * @return boolean Depending on success
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
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id
     * @return boolean
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
     * Never moves the cursor backwards. The compound
     * "(last_read_message_id IS NULL OR last_read_message_id < N)"
     * guard cannot be expressed through the fluent where-methods
     * without breaking the parenthesisation, so it is appended as
     * a single literal fragment. The message id is cast to int
     * before being interpolated, so no SQL injection is possible.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id
     * @param int $messageId Message id the member has now read up to
     * @return boolean Depending on success
     */
    public function setLastReadMessageId($conversationId, $memberId, $messageId)
    {
        $conversationId = (int) $conversationId;
        $memberId = (int) $memberId;
        $messageId = (int) $messageId;

        $guard = '(last_read_message_id IS NULL OR last_read_message_id < ' . $messageId . ')';

        $db = $this->db->update(self::getTableName(), [
            'last_read_message_id' => $messageId,
            'last_read_at' => date('Y-m-d H:i:s')
        ])->whereEquals('conversation_id', $conversationId)
          ->andWhereEquals('member_id', $memberId)
          ->append(' AND ' . $guard);

        return $db->execute(true) > 0;
    }

    /**
     * Fetches the participant row for a member in a conversation.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id
     * @return array|boolean Row as array on success, false if not found
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
     *
     * @param int $conversationId Conversation id
     * @return array
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
     *
     * @param int $conversationId Conversation id
     * @param int $memberId The viewer's member id
     * @return array|boolean Row as array on success, false if not found
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
     * Excludes messages sent by the member themselves: a message
     * you wrote is not unread by you.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id
     * @param int|null $lastReadMessageId The member's read cursor
     * @return int
     */
    public function countUnread($conversationId, $memberId, $lastReadMessageId)
    {
        $messagesTable = MessageMapper::getTableName();

        $db = $this->db->select()
                       ->count('id', 'count')
                       ->from($messagesTable)
                       ->whereEquals('conversation_id', (int) $conversationId)
                       ->andWhereNotEquals('sender_id', (int) $memberId)
                       ->andWhere('deleted_at', 'IS', new RawSqlFragment('NULL'));

        if ($lastReadMessageId > 0) {
            $db->andWhereGreaterThan('id', (int) $lastReadMessageId);
        }

        return (int) $db->query('count');
    }

    /**
     * Counts all unread messages for a member across every
     * conversation they participate in.
     *
     * @param int $memberId Member id
     * @return int
     */
    public function countUnreadForMember($memberId)
    {
        $messagesTable = MessageMapper::getTableName();
        $participantsTable = self::getTableName();

        $db = $this->db->select()
                       ->count('message.id', 'count')
                       ->from($messagesTable, 'message')
                       ->innerJoin($participantsTable . ' AS participant', [
                           'participant.conversation_id' => new RawSqlFragment('message.conversation_id')
                       ])
                       ->whereEquals('participant.member_id', (int) $memberId)
                       ->andWhereNotEquals('message.sender_id', (int) $memberId)
                       ->andWhere('message.deleted_at', 'IS', new RawSqlFragment('NULL'))
                       ->append(' AND (participant.last_read_message_id IS NULL OR message.id > participant.last_read_message_id)');

        return (int) $db->query('count');
    }

    /**
     * Fetches the inbox rows for a member.
     *
     * @param int $memberId Member id
     * @return array
     */
    public function fetchInboxForMember($memberId)
    {
        $participantsTable  = self::getTableName();
        $conversationsTable = ConversationMapper::getTableName();
        $membersTable       = MemberMapper::getTableName();
        $messagesTable      = MessageMapper::getTableName();
        $transTable         = MemberTranslationMapper::getTableName();

        $memberId = (int) $memberId;

        // --- Main query: inbox rows ---------------------------------
        $db = $this->db->select([
                'participant.conversation_id',
                'participant.last_read_message_id',
                'participant.muted_until',
                'conversation.type',
                'conversation.title',
                'conversation.last_message_id',
                'conversation.last_message_at',
                'conversation.message_count',
            ])
            ->from($participantsTable, 'participant')
            ->innerJoin($conversationsTable . ' AS conversation', [
                'conversation.id' => new RawSqlFragment('participant.conversation_id'),
            ])
            ->whereEquals('participant.member_id', $memberId)
            ->andWhereEquals('participant.is_archived', 0);

        $rows = $db->queryAll();

        if (!$rows) {
            return [];
        }

        $result = [];

        foreach ($rows as $row) {
            $conversationId = (int) $row['conversation_id'];

            // --- Other participant of this conversation -------------
            $dbOther = $this->db->select([
                    // Participant id
                    'participant.member_id',

                    // Name from the translation table
                    MemberTranslationMapper::column('name', 'translation'),

                    // Login / avatar from the members table (via the "member" alias)
                    MemberMapper::column('avatar', 'member'),
                ])
                ->from($participantsTable, 'participant')
                ->innerJoin($membersTable . ' AS member', [
                    'member.id' => new RawSqlFragment('participant.member_id'),
                ])
                ->leftJoin($transTable . ' AS translation', [
                    'translation.id'      => new RawSqlFragment('participant.member_id'),
                    'translation.lang_id' => new RawSqlFragment((string) $this->getLangId()),
                ])
                ->whereEquals('participant.conversation_id', $conversationId)
                ->andWhereNotEquals('participant.member_id', $memberId)
                ->limit(1);

            $other = $dbOther->query();

            // --- Last message of this conversation ------------------
            $last = null;

            if (!empty($row['last_message_id'])) {
                $dbLast = $this->db->select([
                        'body',
                        'sender_id',
                        'created_at',
                    ])
                    ->from($messagesTable)
                    ->whereEquals('id', (int) $row['last_message_id'])
                    ->andWhere('deleted_at', 'IS', new RawSqlFragment('NULL'));

                $last = $dbLast->query();
            }

            // --- Unread count ---------------------------------------
            $unreadCount = $this->countUnread($conversationId, $memberId, $row['last_read_message_id']);

            $result[] = [
                'conversation_id'      => $conversationId,
                'type'                 => $row['type'],
                'title'                => $row['title'],
                'last_message_id'      => $row['last_message_id'],
                'last_message_at'      => $row['last_message_at'],
                'message_count'        => (int) $row['message_count'],
                'last_read_message_id' => $row['last_read_message_id'],
                'muted_until'          => $row['muted_until'],
                'unread_count'         => $unreadCount,
                'other'                => $other ?: null,
                'last_message'         => $last ?: null,
            ];
        }

        usort($result, function ($a, $b) {
            $timeA = $a['last_message_at'] ? strtotime($a['last_message_at']) : 0;
            $timeB = $b['last_message_at'] ? strtotime($b['last_message_at']) : 0;
            return $timeB - $timeA;
        });

        return $result;
    }
}