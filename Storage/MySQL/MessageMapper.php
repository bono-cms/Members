<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Storage\MySQL;

use Cms\Storage\MySQL\AbstractMapper;

/**
 * Persists chat message rows.
 */
final class MessageMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_messages');
    }

    /**
     * {@inheritDoc}
     */
    protected function getPk()
    {
        return 'id';
    }

    /**
     * Inserts a single message row.
     *
     * The client_id column is nullable. When present, the unique
     * index on (conversation_id, client_id) makes this insert
     * idempotent at the database level: retrying the same logical
     * send cannot create a duplicate. Callers that need to know
     * which path was taken must check findByClientId() first.
     *
     * @param int $conversationId Conversation id
     * @param int $senderId Sender member id
     * @param string $body Message text
     * @param string|null $clientId Optional client-generated UUID
     * @param int|null $replyToId Optional parent message id
     * @param string $type Message type (text, image, file, system)
     * @return boolean Depending on success
     */
    public function insertMessage($conversationId, $senderId, $body, $clientId = null, $replyToId = null, $type = 'text')
    {
        $db = $this->db->insert(self::getTableName(), [
            'conversation_id' => (int) $conversationId,
            'sender_id' => (int) $senderId,
            'type' => $type,
            'body' => $body,
            'client_id' => $clientId,
            'reply_to_id' => $replyToId === null ? null : (int) $replyToId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $db->execute();
    }

    /**
     * Finds a message by conversation id and client id.
     *
     * Used for optimistic-send idempotency: if a client retries a
     * send whose client_id already exists, the service returns the
     * existing message instead of inserting a duplicate.
     *
     * @param int $conversationId Conversation id
     * @param string $clientId Client-generated UUID
     * @return array|boolean Row as array on success, false if not found
     */
    public function findByClientId($conversationId, $clientId)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('conversation_id', (int) $conversationId)
                       ->andWhereEquals('client_id', $clientId);

        return $db->query();
    }

    /**
     * Fetches a thread, ascending by id.
     *
     * With $sinceId = 0, returns the oldest messages first up to
     * $limit. With $sinceId set, returns only messages with id
     * greater than $sinceId, which is what the polling endpoint
     * consumes. The composite index (conversation_id, id) serves
     * both directions.
     *
     * @param int $conversationId Conversation id
     * @param int $sinceId Return only messages newer than this id
     * @param int $limit Maximum number of rows to return
     * @return array
     */
    public function fetchThread($conversationId, $sinceId = 0, $limit = 50)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('conversation_id', (int) $conversationId)
                       ->andWhereGreaterThan('id', (int) $sinceId)
                       ->orderBy('id')
                       ->asc()
                       ->limit((int) $limit);

        return $db->queryAll();
    }

    /**
     * Fetches the newest messages for a thread, descending by id.
     *
     * Used by the "load earlier" flow: the client passes the oldest
     * id it currently has as $beforeId and receives older rows in
     * descending order.
     *
     * @param int $conversationId Conversation id
     * @param int $beforeId Return only messages older than this id
     * @param int $limit Maximum number of rows to return
     * @return array
     */
    public function fetchBefore($conversationId, $beforeId, $limit = 50)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('conversation_id', (int) $conversationId)
                       ->andWhereLessThan('id', (int) $beforeId)
                       ->orderBy('id')
                       ->desc()
                       ->limit((int) $limit);

        return $db->queryAll();
    }

    /**
     * Fetches the newest non-deleted message of a conversation.
     *
     * Used to recompute the denormalized conversation pointer after
     * a message is soft-deleted.
     *
     * @param int $conversationId Conversation id
     * @return array|boolean Row as array on success, false if none
     */
    public function findLatestLive($conversationId)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('conversation_id', (int) $conversationId)
                       ->andWhereEquals('deleted_at', null)
                       ->orderBy('id')
                       ->desc()
                       ->limit(1);

        return $db->query();
    }

    /**
     * Soft-deletes a message by setting its body to empty and
     * stamping deleted_at.
     *
     * Hard delete is intentionally not exposed: message rows must
     * survive for threading, moderation and audit.
     *
     * @param int $id Message id
     * @return boolean Depending on success
     */
    public function softDelete($id)
    {
        $db = $this->db->update(self::getTableName(), [
            'body' => '',
            'deleted_at' => date('Y-m-d H:i:s')
        ])->whereEquals('id', (int) $id)
          ->andWhereEquals('deleted_at', null);

        return $db->execute();
    }

    /**
     * Stamps a message as edited.
     *
     * Only applied to non-deleted messages; an edited-then-deleted
     * message keeps its original body for moderation purposes.
     *
     * @param int $id Message id
     * @param string $body New body text
     * @return boolean Depending on success
     */
    public function markEdited($id, $body)
    {
        $db = $this->db->update(self::getTableName(), [
            'body' => $body,
            'edited_at' => date('Y-m-d H:i:s')
        ])->whereEquals('id', (int) $id)
          ->andWhereEquals('deleted_at', null);

        return $db->execute();
    }
}