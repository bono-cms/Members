<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Members\View\ChatPresenter;
use Krystal\Stdlib\VirtualEntity;
use InvalidArgumentException;

/**
 * Site-side chat controller.
 *
 * Authentication is enforced by AuthRequiredMiddleware on every
 * route (see Config/routes.php), so every action here can assume a
 * logged-in member is present.
 */
final class Chat extends AbstractController
{
    /**
     * Renders the member's inbox
     *
     * @return string
     */
    public function inboxAction()
    {
        $chatService = $this->getModuleService('chatService');
        $memberManager = $this->getModuleService('memberManager');

        $memberId = (int) $memberManager->getMember('id');
        $rows = $chatService->fetchInbox($memberId);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Messages'))
             ->setSeo(false);

        return $this->view->render('member-inbox', [
            'page' => $page,
            'conversations' => $this->createPresenter()->conversations($rows)
        ]);
    }

    /**
     * Resolves or creates a direct conversation with another member,
     * then redirects to the thread.
     *
     * This is the target of the "Message" button on a public profile.
     *
     * @param string $otherId The other member's id
     * @return void
     */
    public function withAction($otherId)
    {
        $chatService = $this->getModuleService('chatService');
        $memberManager = $this->getModuleService('memberManager');

        $memberId = (int) $memberManager->getMember('id');

        try {
            $conversationId = $chatService->findOrCreateDirect($memberId, (int) $otherId);

        } catch (InvalidArgumentException $exception) {
            $this->flashBag->set('error', $this->translator->translate('You cannot start a conversation with yourself.'));
            $this->response->redirect($this->createUrl('Members:Directory@indexAction'));
        }

        $this->response->redirect($this->createUrl('Members:Chat@conversationAction', [$conversationId]));
    }

    /**
     * Renders a single conversation thread
     *
     * @param string $conversationId Conversation id
     * @return string|boolean
     */
    public function conversationAction($conversationId)
    {
        $chatService = $this->getModuleService('chatService');
        $memberManager = $this->getModuleService('memberManager');

        $memberId = (int) $memberManager->getMember('id');

        $conversation = $chatService->findConversationForMember((int) $conversationId, $memberId);

        if ($conversation === false) {
            return false;
        }

        $messages = $chatService->fetchThread((int) $conversationId, $memberId);

        if (!empty($messages)) {
            $lastMessageId = (int) end($messages)['id'];
            $chatService->markReadUpTo((int) $conversationId, $memberId, $lastMessageId);
        }

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Conversation'))
             ->setSeo(false);

        return $this->view->render('member-conversation', [
            'page' => $page,
            'conversationId' => (int) $conversationId,
            'messages' => $messages,
            'senders' => $this->collectSenders($messages),
            'meId' => $memberId
        ]);
    }

    /**
     * Handles a send from the web UI
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
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        $chatService = $this->getModuleService('chatService');
        $memberManager = $this->getModuleService('memberManager');

        $memberId = (int) $memberManager->getMember('id');
        $conversationId = (int) $this->request->getPost('conversation_id');
        $body = (string) $this->request->getPost('body');
        $clientId = $this->request->getPost('client_id');

        if ($chatService->findConversationForMember($conversationId, $memberId) === false) {
            return $this->json(['error' => 'Forbidden']);
        }

        try {
            $messageId = $chatService->sendMessage(
                $conversationId,
                $memberId,
                $body,
                $clientId !== null && $clientId !== '' ? (string) $clientId : null
            );

        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => 'Forbidden']);

        } catch (\RuntimeException $exception) {
            return $this->json(['error' => $exception->getMessage()]);
        }

        return $this->json([
            'id' => $messageId
        ]);
    }

    /**
     * Polls for new messages in a conversation
     *
     * @return string JSON
     */
    public function pollAction()
    {
        $chatService = $this->getModuleService('chatService');
        $memberManager = $this->getModuleService('memberManager');

        $memberId = (int) $memberManager->getMember('id');
        $conversationId = (int) $this->request->getQuery('conversation_id');
        $sinceId = (int) $this->request->getQuery('since', 0);

        if ($conversationId <= 0) {
            return $this->json(['error' => 'Missing conversation id']);
        }

        if ($chatService->findConversationForMember($conversationId, $memberId) === false) {
            return $this->json(['error' => 'Forbidden']);
        }

        try {
            $messages = $chatService->fetchThread($conversationId, $memberId, $sinceId);

        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => 'Forbidden']);
        }

        if (!empty($messages)) {
            $lastMessageId = (int) end($messages)['id'];
            $chatService->markReadUpTo($conversationId, $memberId, $lastMessageId);
        }

        return $this->json([
            'messages' => $this->createPresenter()->messages($messages, $this->collectSenders($messages))
        ]);
    }

    /**
     * Builds the chat presenter with the site's root URL and the
     * avatar directory used by the image manager.
     *
     * @return \Members\View\ChatPresenter
     */
    private function createPresenter()
    {
        return new ChatPresenter(
            $this->request->getBaseUrl(),
            '/data/uploads/module/members/avatars'
        );
    }

    /**
     * Collects sender rows for a set of messages, indexed by member id.
     *
     * A member row may be missing when the sender has been deleted;
     * the presenter already handles that case by returning null for
     * the "user" field.
     *
     * @param array $messages Message rows
     * @return array
     */
    private function collectSenders(array $messages)
    {
        $ids = [];

        foreach ($messages as $message) {
            if (!empty($message['sender_id'])) {
                $ids[(int) $message['sender_id']] = true;
            }
        }

        $result = [];

        if (empty($ids)) {
            return $result;
        }

        $memberManager = $this->getModuleService('memberManager');

        foreach (array_keys($ids) as $senderId) {
            $member = $memberManager->fetchById($senderId);

            if ($member !== false) {
                $result[$senderId] = [
                    'id' => (int) $member->getId(),
                    'name' => $member->getName(),
                    'login' => $member->getLogin(),
                    'avatar' => $member->getAvatar()
                ];
            }
        }

        return $result;
    }
}