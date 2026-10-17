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
 * Persists conversation rows (direct and group).
 */
final class ConversationMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_conversations');
    }

    /**
     * {@inheritDoc}
     */
    protected function getPk()
    {
        return 'id';
    }

    /**
     * Finds a direct conversation by its normalized key.
     *
     * The key is "minId:maxId" and is produced only by
     * ChatService::directKey(). The unique index on direct_key
     * guarantees at most one row per pair.
     *
     * @param string $key Normalized direct conversation key
     * @return array|boolean Row as array on success, false if not found
     */
    public function findByDirectKey($key)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('direct_key', $key);

        return $db->query();
    }

    /**
     * Creates a direct conversation row.
     *
     * Participants are added separately by the service; this method
     * only inserts the conversation itself.
     *
     * @param string $key Normalized direct conversation key
     * @param int $createdBy Member id of the initiator
     * @return boolean Depending on success
     */
    public function createDirect($key, $createdBy)
    {
        $db = $this->db->insert(self::getTableName(), [
            'type' => 'direct',
            'direct_key' => $key,
            'created_by' => (int) $createdBy,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $db->execute();
    }

    /**
     * Creates a group conversation row.
     *
     * Not used in the 1-to-1 v1 flow. Present so that adding groups
     * later is a code change, not a schema change.
     *
     * @param string $title Group title
     * @param int $createdBy Member id of the creator
     * @return boolean Depending on success
     */
    public function createGroup($title, $createdBy)
    {
        $db = $this->db->insert(self::getTableName(), [
            'type' => 'group',
            'title' => $title,
            'created_by' => (int) $createdBy,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $db->execute();
    }

    /**
     * Updates the denormalized last-message pointer and increments
     * the message counter.
     *
     * Called by ChatService::sendMessage() inside the send transaction.
     * The counter update uses RawSqlFragment so that MySQL computes
     * "message_count + 1" in place rather than reading and writing a
     * stale value from PHP.
     *
     * @param int $id Conversation id
     * @param int $messageId Newest message id
     * @param string $createdAt Newest message timestamp
     * @return boolean Depending on success
     */
    public function touchLastMessage($id, $messageId, $createdAt)
    {
        $db = $this->db->update(self::getTableName(), [
            'last_message_id' => (int) $messageId,
            'last_message_at' => $createdAt,
            'message_count' => new RawSqlFragment('message_count + 1')
        ])->whereEquals('id', (int) $id);

        return $db->execute();
    }

    /**
     * Recomputes the denormalized last-message fields from the
     * newest non-deleted message.
     *
     * Called by ChatService::softDeleteMessage() after a message is
     * tombstoned, because soft delete does not trigger foreign keys
     * and the application is the owner of last_message_id.
     *
     * The decrement uses GREATEST(0, ...) so a data anomaly cannot
     * push the counter negative.
     *
     * @param int $id Conversation id
     * @param int|null $messageId Newest surviving message id, or null
     * @param string|null $createdAt Newest surviving message timestamp, or null
     * @param int $decrement Number of messages that are no longer live
     * @return boolean Depending on success
     */
    public function resetLastMessage($id, $messageId, $createdAt, $decrement = 1)
    {
        $step = (int) $decrement;

        $db = $this->db->update(self::getTableName(), [
            'last_message_id' => $messageId === null ? null : (int) $messageId,
            'last_message_at' => $createdAt,
            'message_count' => new RawSqlFragment('GREATEST(0, message_count - ' . $step . ')')
        ])->whereEquals('id', (int) $id);

        return $db->execute();
    }

    /**
     * Returns the raw conversation row by its id.
     *
     * Thin wrapper kept for symmetry with other mappers; callers
     * that need participant checks should use ChatService.
     *
     * @param int $id Conversation id
     * @return array|boolean Row as array on success, false if not found
     */
    public function findById($id)
    {
        return $this->findByPk((int) $id);
    }
}