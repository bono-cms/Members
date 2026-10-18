<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Api;

use Members\View\ChatPresenter;
use InvalidArgumentException;
use RuntimeException;

final class Chat extends AbstractApiController
{
    /**
     * GET /api/v1/members/messages
     *
     * @return string JSON
     */
    public function indexAction()
    {
        $chatService = $this->getModuleService('chatService');
        $rows = $chatService->fetchInbox($this->getAuthUserId());

        return $this->json([
            'data' => $this->presenter()->conversations($rows),
        ]);
    }

    /**
     * GET /api/v1/members/messages/thread?id=42[&before=100]
     *
     * @return string JSON
     */
    public function threadAction()
    {
        $conversationId = (int) $this->request->getQuery('id', 0);

        if ($conversationId <= 0) {
            $this->response->setStatusCode(400);
            return $this->json(['error' => 'Missing conversation id']);
        }

        $chatService = $this->getModuleService('chatService');
        $memberId = $this->getAuthUserId();

        if ($chatService->findConversationForMember($conversationId, $memberId) === false) {
            $this->response->setStatusCode(403);
            return $this->json(['error' => 'Forbidden']);
        }

        $before = (int) $this->request->getQuery('before', 0);

        if ($before > 0) {
            $messages = $chatService->fetchBefore($conversationId, $memberId, $before);
        } else {
            $messages = $chatService->fetchThreadAndMarkRead($conversationId, $memberId);
        }

        return $this->json([
            'data' => $this->presenter()->messages($messages, $chatService->resolveSenders($messages)),
        ]);
    }

    /**
     * POST /api/v1/members/messages/send
     *
     * Accepts conversation_id, body, and optional client_id.
     *
     * @return string JSON
     */
    public function sendAction()
    {
        $validator = $this->createValidation();

        $validator->field('conversation_id')
                  ->required();

        $validator->field('body')
                  ->required();

        if (!$validator->isPassed()) {
            $this->response->setStatusCode(422);
            return $this->json(['errors' => $validator->getErrors()]);
        }

        $chatService = $this->getModuleService('chatService');
        $memberId = $this->getAuthUserId();
        $conversationId = (int) $this->request->getPost('conversation_id');
        $body = (string) $this->request->getPost('body');
        $clientId = $this->request->getPost('client_id');

        if ($chatService->findConversationForMember($conversationId, $memberId) === false) {
            $this->response->setStatusCode(403);
            return $this->json(['error' => 'Forbidden']);
        }

        try {
            $messageId = $chatService->sendMessage($conversationId, $memberId, $body, $clientId);
        } catch (InvalidArgumentException $exception) {
            $this->response->setStatusCode(403);
            return $this->json(['error' => 'Forbidden']);
        } catch (RuntimeException $exception) {
            $this->response->setStatusCode(422);
            return $this->json(['error' => $exception->getMessage()]);
        }

        $this->response->setStatusCode(201);
        return $this->json(['id' => $messageId]);
    }

    /**
     * POST /api/v1/members/messages/start
     *
     * Accepts member_id and resolves or creates a direct conversation.
     *
     * @return string JSON
     */
    public function startAction()
    {
        $otherId = (int) $this->request->getPost('member_id', 0);

        if ($otherId <= 0) {
            $this->response->setStatusCode(400);
            return $this->json(['error' => 'Missing member id']);
        }

        $chatService = $this->getModuleService('chatService');
        $memberId = $this->getAuthUserId();

        try {
            $conversationId = $chatService->findOrCreateDirect($memberId, $otherId);
        } catch (InvalidArgumentException $exception) {
            $this->response->setStatusCode(422);
            return $this->json(['error' => 'You cannot start a conversation with yourself.']);
        }

        return $this->json(['conversationId' => $conversationId]);
    }

    /**
     * GET /api/v1/members/messages/poll?id=42&since=100
     *
     * @return string JSON
     */
    public function pollAction()
    {
        $conversationId = (int) $this->request->getQuery('id', 0);
        $sinceId = (int) $this->request->getQuery('since', 0);

        if ($conversationId <= 0) {
            $this->response->setStatusCode(400);
            return $this->json(['error' => 'Missing conversation id']);
        }

        $chatService = $this->getModuleService('chatService');
        $memberId = $this->getAuthUserId();

        if ($chatService->findConversationForMember($conversationId, $memberId) === false) {
            $this->response->setStatusCode(403);
            return $this->json(['error' => 'Forbidden']);
        }

        $messages = $chatService->fetchThreadAndMarkRead($conversationId, $memberId, $sinceId);

        return $this->json([
            'data' => $this->presenter()->messages($messages, $chatService->resolveSenders($messages)),
        ]);
    }

    /**
     * GET /api/v1/members/messages/unread
     *
     * @return string JSON
     */
    public function unreadAction()
    {
        $chatService = $this->getModuleService('chatService');
        $count = $chatService->countUnreadForMember($this->getAuthUserId());

        return $this->json(['count' => $count]);
    }

    /**
     * Builds the chat presenter used for shaping message and conversation rows.
     *
     * @return \Members\View\ChatPresenter
     */
    private function presenter()
    {
        return new ChatPresenter(
            $this->request->getBaseUrl(),
            '/data/uploads/module/members/avatars'
        );
    }
}