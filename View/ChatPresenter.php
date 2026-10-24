<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\View;

use Members\Service\MemberEntity;

/**
 * Shapes chat rows into the JSON contract consumed by every client.
 */
final class ChatPresenter
{
    /**
     * Base URL used to build avatar URLs
     *
     * @var string
     */
    private $rootUrl;

    /**
     * Relative avatar directory, without trailing slash
     *
     * @var string
     */
    private $avatarDir;

    /**
     * State initialization
     *
     * @param string $rootUrl Absolute site root URL, e.g. "https://example.com"
     * @param string $avatarDir Avatar directory relative to the root, e.g. "/data/uploads/module/members/avatars"
     * @return void
     */
    public function __construct($rootUrl, $avatarDir)
    {
        $this->rootUrl = rtrim((string) $rootUrl, '/');
        $this->avatarDir = '/' . trim((string) $avatarDir, '/');
    }

    /**
     * Presents a single message row.
     *
     * @param array $row Raw message row
     * @param array|boolean $sender Sender member row, or false if deleted
     * @return array
     */
    public function message(array $row, $sender)
    {
        $senderId = isset($row['sender_id']) ? (int) $row['sender_id'] : 0;
        $deleted = !empty($row['deleted_at']);

        return [
            '_id' => (int) $row['id'],
            'text' => $deleted ? '' : (string) $row['body'],
            'createdAt' => $this->isoDate(isset($row['created_at']) ? $row['created_at'] : null),
            'type' => isset($row['type']) ? (string) $row['type'] : 'text',
            'deleted' => $deleted,
            'editedAt' => $this->isoDate(isset($row['edited_at']) ? $row['edited_at'] : null),
            'replyToId' => isset($row['reply_to_id']) && $row['reply_to_id'] !== null ? (int) $row['reply_to_id'] : null,
            'clientId' => isset($row['client_id']) && $row['client_id'] !== '' ? (string) $row['client_id'] : null,
            'user' => $this->messageUser($senderId, $sender)
        ];
    }

    /**
     * Presents a collection of message rows.
     *
     * @param array $rows Raw message rows
     * @param array $senders Sender rows indexed by member id
     * @return array
     */
    public function messages(array $rows, array $senders)
    {
        $result = [];

        foreach ($rows as $row) {
            $senderId = isset($row['sender_id']) ? (int) $row['sender_id'] : 0;
            $sender = isset($senders[$senderId]) ? $senders[$senderId] : false;

            $result[] = $this->message($row, $sender);
        }

        return $result;
    }

    /**
     * Presents an inbox row.
     *
     * @param array $row Enriched inbox row from ParticipantMapper
     * @return array
     */
    public function conversation(array $row)
    {
        return [
            'id' => (int) $row['conversation_id'],
            'type' => isset($row['type']) ? (string) $row['type'] : 'direct',
            'displayName' => $this->displayName($row),
            'other' => $this->conversationOther(isset($row['other']) ? $row['other'] : null),
            'lastMessage' => $this->conversationLastMessage($row),
            'lastMessageAt' => $this->isoDate(isset($row['last_message_at']) ? $row['last_message_at'] : null),
            'messageCount' => isset($row['message_count']) ? (int) $row['message_count'] : 0,
            'unreadCount' => isset($row['unread_count']) ? (int) $row['unread_count'] : 0,
            'mutedUntil' => $this->isoDate(isset($row['muted_until']) ? $row['muted_until'] : null)
        ];
    }

    /**
     * Presents a collection of inbox rows.
     *
     * @param array $rows Enriched inbox rows from ParticipantMapper
     * @return array
     */
    public function conversations(array $rows)
    {
        $result = [];

        foreach ($rows as $row) {
            $result[] = $this->conversation($row);
        }

        return $result;
    }

    /**
     * Builds the "user" field of a message.
     *
     * @param int $senderId Sender member id as stored on the message
     * @param array|boolean $sender Sender row, or false if deleted
     * @return array|null
     */
    private function messageUser($senderId, $sender)
    {
        if ($sender === false || !is_array($sender)) {
            return null;
        }

        return [
            '_id' => (int) $sender['id'],
            'name' => $this->memberName($sender),
            'avatar' => $this->avatarUrl(isset($sender['avatar']) ? $sender['avatar'] : null, isset($sender['id']) ? (int) $sender['id'] : $senderId)
        ];
    }

    /**
     * Resolves the display name for an inbox row.
     *
     * @param array $row Inbox row
     * @return string
     */
    private function displayName(array $row)
    {
        $type = isset($row['type']) ? $row['type'] : 'direct';

        if ($type === 'group' && !empty($row['title'])) {
            return (string) $row['title'];
        }

        if (!empty($row['other']) && is_array($row['other'])) {
            return $this->memberName($row['other']);
        }

        return '#' . (int) $row['conversation_id'];
    }

    /**
     * Resolves the member's display name from a raw row.
     *
     * @param array $member Member row
     * @return string
     */
    private function memberName(array $member)
    {
        if (!empty($member['name'])) {
            return (string) $member['name'];
        }

        return '#' . (int) $member['id'];
    }

    /**
     * Shapes the "other participant" field of an inbox row.
     *
     * @param array|null $other Other participant row, or null
     * @return array|null
     */
    private function conversationOther($other)
    {
        if ($other === null || !is_array($other)) {
            return null;
        }

        return [
            'id' => (int) $other['member_id'],
            'name' => $this->memberName($other),
            'avatar' => $this->avatarUrl(isset($other['avatar']) ? $other['avatar'] : null, isset($other['member_id']) ? (int) $other['member_id'] : 0)
        ];
    }

    /**
     * Shapes the "last message" field of an inbox row.
     *
     * @param array $row Inbox row
     * @return array|null
     */
    private function conversationLastMessage(array $row)
    {
        if (empty($row['last_message']) || !is_array($row['last_message'])) {
            return null;
        }

        $last = $row['last_message'];

        return [
            'text' => (string) $last['body'],
            'createdAt' => $this->isoDate(isset($last['created_at']) ? $last['created_at'] : null),
            'senderId' => isset($last['sender_id']) ? (int) $last['sender_id'] : 0
        ];
    }

    /**
     * Builds an absolute avatar URL for a member.
     *
     * @param string|null $avatar Avatar filename, or null
     * @param int $memberId Member id
     * @return string|null
     */
    private function avatarUrl($avatar, $memberId)
    {
        if ($avatar === null || $avatar === '') {
            return null;
        }

        return sprintf(
            '%s%s/%d/%s/%s',
            $this->rootUrl,
            $this->avatarDir,
            (int) $memberId,
            MemberEntity::AVATAR_SMALL,
            rawurlencode($avatar)
        );
    }

    /**
     * Converts a MySQL datetime into an ISO 8601 UTC string.
     *
     * @param string|null $value MySQL datetime, or null
     * @return string|null
     */
    private function isoDate($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }
}