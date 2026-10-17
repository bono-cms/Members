<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Members\Storage\MySQL\ConversationMapper;
use Members\Storage\MySQL\ParticipantMapper;
use Members\Storage\MySQL\MessageMapper;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

/**
 * Owns every rule of the chat pipeline.
 *
 * Controllers and API endpoints talk only to this service. It is the
 * sole place where the direct-conversation key is produced, where the
 * send and soft-delete invariants are enforced, and where the
 * participant access check is applied.
 */
final class ChatService
{
    /**
     * Maximum accepted message length in characters
     *
     * @const int
     */
    const MAX_BODY_LENGTH = 5000;

    /**
     * Conversation mapper instance
     *
     * @var \Members\Storage\MySQL\ConversationMapper
     */
    private $conversationMapper;

    /**
     * Participant mapper instance
     *
     * @var \Members\Storage\MySQL\ParticipantMapper
     */
    private $participantMapper;

    /**
     * Message mapper instance
     *
     * @var \Members\Storage\MySQL\MessageMapper
     */
    private $messageMapper;

    /**
     * State initialization
     *
     * @param \Members\Storage\MySQL\ConversationMapper $conversationMapper
     * @param \Members\Storage\MySQL\ParticipantMapper $participantMapper
     * @param \Members\Storage\MySQL\MessageMapper $messageMapper
     * @return void
     */
    public function __construct(
        ConversationMapper $conversationMapper,
        ParticipantMapper $participantMapper,
        MessageMapper $messageMapper
    ) {
        $this->conversationMapper = $conversationMapper;
        $this->participantMapper = $participantMapper;
        $this->messageMapper = $messageMapper;
    }

    /**
     * Returns the other participant of a direct conversation.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId The viewer's member id
     * @return array|boolean Row as array on success, false if not found
     */
    public function findOtherParticipant($conversationId, $memberId)
    {
        return $this->participantMapper->findOtherParticipant((int) $conversationId, (int) $memberId);
    }

    /**
     * Returns the total number of unread messages for a member
     * across all their conversations.
     *
     * This is what the nav badge displays. It counts every live
     * message sent by someone else that the member hasn't read yet.
     *
     * @param int $memberId Member id
     * @return int
     */
    public function countUnreadForMember($memberId)
    {
        return $this->participantMapper->countUnreadForMember((int) $memberId);
    }

    /**
     * Determines whether a member belongs to a conversation.
     *
     * This is the sole access check used by the controllers.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id
     * @return boolean
     */
    public function isParticipant($conversationId, $memberId)
    {
        return $this->participantMapper->isParticipant((int) $conversationId, (int) $memberId);
    }

    /**
     * Finds a direct conversation between two members or creates one.
     *
     * The direct_key unique index guarantees at most one conversation
     * per pair even under concurrent requests. If the insert loses the
     * race, the winning row is re-read and returned.
     *
     * @param int $memberId Member id of the initiator
     * @param int $otherId Member id of the other party
     * @throws \InvalidArgumentException If a member tries to message themselves
     * @throws \PDOException Rethrown on non-race database failures
     * @return int Conversation id
     */
    public function findOrCreateDirect($memberId, $otherId)
    {
        $memberId = (int) $memberId;
        $otherId = (int) $otherId;

        if ($memberId === $otherId) {
            throw new InvalidArgumentException('A member cannot start a conversation with themselves');
        }

        $key = $this->directKey($memberId, $otherId);

        // Fast path: the conversation already exists
        $existing = $this->conversationMapper->findByDirectKey($key);

        if ($existing !== false) {
            return (int) $existing['id'];
        }

        try {
            $this->conversationMapper->createDirect($key, $memberId);

        } catch (PDOException $exception) {
            // The unique index rejected a concurrent insert. Re-read
            // the winner. If even that fails, the exception is not a
            // race we can recover from and must be rethrown.
            $existing = $this->conversationMapper->findByDirectKey($key);

            if ($existing !== false) {
                return (int) $existing['id'];
            }

            throw $exception;
        }

        $conversationId = (int) $this->conversationMapper->getLastId();

        $this->participantMapper->addParticipants($conversationId, [$memberId, $otherId]);

        return $conversationId;
    }

    /**
     * Sends a message to a conversation.
     *
     * Guarantees, all inside a single transaction:
     *  - the sender is a participant
     *  - the body is non-empty and within limits
     *  - a retried send with the same client_id returns the existing id
     *  - the conversation's last_message_id / last_message_at / message_count
     *    are updated atomically with the insert
     *  - the sender's own read cursor is advanced to the new message
     *
     * @param int $conversationId Conversation id
     * @param int $senderId Sender member id
     * @param string $body Raw message body
     * @param string|null $clientId Optional client-generated id for idempotency
     * @param int|null $replyToId Optional parent message id
     * @param string $type Message type (text, image, file, system)
     * @throws \InvalidArgumentException If the sender is not a participant
     * @throws \RuntimeException If the body is empty or too long
     * @return int Message id
     */
    public function sendMessage($conversationId, $senderId, $body, $clientId = null, $replyToId = null, $type = 'text')
    {
        $conversationId = (int) $conversationId;
        $senderId = (int) $senderId;

        if (!$this->isParticipant($conversationId, $senderId)) {
            throw new InvalidArgumentException('The sender does not belong to this conversation');
        }

        $body = trim((string) $body);

        if ($body === '') {
            throw new RuntimeException('The message body is empty');
        }

        if (mb_strlen($body) > self::MAX_BODY_LENGTH) {
            throw new RuntimeException('The message body is too long');
        }

        // Idempotent retry: the same client id must not create a second row
        if ($clientId !== null && $clientId !== '') {
            $existing = $this->messageMapper->findByClientId($conversationId, $clientId);

            if ($existing !== false) {
                return (int) $existing['id'];
            }
        }

        $messageId = $this->messageMapper->wrapTransaction(function () use ($conversationId, $senderId, $body, $clientId, $replyToId, $type) {
            $ok = $this->messageMapper->insertMessage(
                $conversationId,
                $senderId,
                $body,
                $clientId === null || $clientId === '' ? null : $clientId,
                $replyToId,
                $type
            );

            if (!$ok) {
                throw new RuntimeException('Failed to insert the message');
            }

            $messageId = (int) $this->messageMapper->getLastId();
            $createdAt = date('Y-m-d H:i:s');

            $this->conversationMapper->touchLastMessage($conversationId, $messageId, $createdAt);
            $this->participantMapper->setLastReadMessageId($conversationId, $senderId, $messageId);

            return $messageId;
        });

        return $messageId;
    }

    /**
     * Soft-deletes a message and repairs the conversation pointer.
     *
     * Only the sender may delete their own message in the 1-to-1 flow.
     * The message row survives as a tombstone so that threading,
     * moderation and audit remain intact.
     *
     * @param int $messageId Message id
     * @param int $memberId Member attempting the delete
     * @throws \InvalidArgumentException If the message does not exist or belongs to another member
     * @return boolean
     */
    public function softDeleteMessage($messageId, $memberId)
    {
        $messageId = (int) $messageId;
        $memberId = (int) $memberId;

        $message = $this->messageMapper->findByPk($messageId);

        if ($message === false) {
            throw new InvalidArgumentException('The message does not exist');
        }

        if ((int) $message['sender_id'] !== $memberId) {
            throw new InvalidArgumentException('Only the sender may delete their own message');
        }

        if (!empty($message['deleted_at'])) {
            return true;
        }

        $conversationId = (int) $message['conversation_id'];

        return $this->messageMapper->wrapTransaction(function () use ($messageId, $conversationId) {
            $this->messageMapper->softDelete($messageId);

            $latest = $this->messageMapper->findLatestLive($conversationId);

            if ($latest !== false) {
                $this->conversationMapper->resetLastMessage(
                    $conversationId,
                    (int) $latest['id'],
                    $latest['created_at'],
                    1
                );
            } else {
                $this->conversationMapper->resetLastMessage($conversationId, null, null, 1);
            }

            return true;
        });
    }

    /**
     * Advances a member's read cursor up to a given message.
     *
     * The cursor never moves backwards; an out-of-order poll response
     * with an older id is ignored by the mapper itself.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id
     * @param int $messageId Message id that has now been read
     * @throws \InvalidArgumentException If the member does not belong to the conversation
     * @return boolean
     */
    public function markReadUpTo($conversationId, $memberId, $messageId)
    {
        $conversationId = (int) $conversationId;
        $memberId = (int) $memberId;

        if (!$this->isParticipant($conversationId, $memberId)) {
            throw new InvalidArgumentException('The member does not belong to this conversation');
        }

        return $this->participantMapper->setLastReadMessageId($conversationId, $memberId, (int) $messageId);
    }

    /**
     * Fetches a conversation thread in ascending id order.
     *
     * When $sinceId is zero, the oldest messages are returned first.
     * When it is set, only messages newer than that id are returned,
     * which is what the polling endpoint consumes.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id requesting the thread
     * @param int $sinceId Return only messages newer than this id
     * @param int $limit Maximum number of rows
     * @throws \InvalidArgumentException If the member does not belong to the conversation
     * @return array
     */
    public function fetchThread($conversationId, $memberId, $sinceId = 0, $limit = 50)
    {
        $conversationId = (int) $conversationId;
        $memberId = (int) $memberId;

        if (!$this->isParticipant($conversationId, $memberId)) {
            throw new InvalidArgumentException('The member does not belong to this conversation');
        }

        return $this->messageMapper->fetchThread($conversationId, (int) $sinceId, $this->clampLimit($limit));
    }

    /**
     * Fetches older messages for a "load earlier" flow.
     *
     * The client passes the oldest id it currently has; rows older
     * than that are returned in descending order.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id requesting the thread
     * @param int $beforeId Return only messages older than this id
     * @param int $limit Maximum number of rows
     * @throws \InvalidArgumentException If the member does not belong to the conversation
     * @return array
     */
    public function fetchBefore($conversationId, $memberId, $beforeId, $limit = 50)
    {
        $conversationId = (int) $conversationId;
        $memberId = (int) $memberId;

        if (!$this->isParticipant($conversationId, $memberId)) {
            throw new InvalidArgumentException('The member does not belong to this conversation');
        }

        return $this->messageMapper->fetchBefore($conversationId, (int) $beforeId, $this->clampLimit($limit));
    }

    /**
     * Fetches the inbox for a member.
     *
     * The mapper is responsible for the per-row enrichment (other
     * participant, last live message); this method only guards
     * input and delegates.
     *
     * @param int $memberId Member id
     * @return array
     */
    public function fetchInbox($memberId)
    {
        return $this->participantMapper->fetchInboxForMember((int) $memberId);
    }

    /**
     * Returns a conversation row if the member belongs to it.
     *
     * Returns false when the conversation does not exist or the
     * member is not a participant, so callers can treat both cases
     * as a 404 without leaking existence information.
     *
     * @param int $conversationId Conversation id
     * @param int $memberId Member id
     * @return array|boolean Row as array on success, false otherwise
     */
    public function findConversationForMember($conversationId, $memberId)
    {
        $conversationId = (int) $conversationId;
        $memberId = (int) $memberId;

        if (!$this->isParticipant($conversationId, $memberId)) {
            return false;
        }

        return $this->conversationMapper->findById($conversationId);
    }

    /**
     * Normalizes a member pair into a direct conversation key.
     *
     * This is the sole producer of direct keys. The unique index on
     * direct_key is only meaningful if every caller goes through this
     * method, so it stays private.
     *
     * @param int $memberId First member id
     * @param int $otherId Second member id
     * @return string
     */
    private function directKey($memberId, $otherId)
    {
        return min((int) $memberId, (int) $otherId) . ':' . max((int) $memberId, (int) $otherId);
    }

    /**
     * Clamps a requested page size into the accepted range.
     *
     * Prevents a client from asking for the entire history in one
     * request, which would defeat the point of incremental fetch.
     *
     * @param int $limit Requested limit
     * @return int
     */
    private function clampLimit($limit)
    {
        $limit = (int) $limit;

        if ($limit <= 0) {
            return 50;
        }

        if ($limit > 100) {
            return 100;
        }

        return $limit;
    }
}