<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Api;

use Krystal\Authentication\JWT;
use Members\Service\MemberEntity;

final class Auth extends AbstractApiController
{
    /**
     * POST /api/v1/members/auth/login
     *
     * Validates credentials and returns a JWT for the mobile client.
     *
     * @return string JSON
     */
    public function loginAction()
    {
        $identifier = (string) $this->request->getPost('identifier');
        $password = (string) $this->request->getPost('password');

        if ($identifier === '' || $password === '') {
            $this->response->setStatusCode(400);
            return $this->json(['error' => 'Missing credentials']);
        }

        $memberManager = $this->getModuleService('memberManager');

        if (!$memberManager->login($identifier, $password)) {
            $this->response->setStatusCode(401);
            return $this->json(['error' => 'Invalid credentials']);
        }

        $member = $memberManager->getMember();
        $config = $this->getApiConfig();

        $token = (new JWT($config['jwt_secret']))->encode([
            'sub' => (int) $member['id'],
            'name' => $member['name'],
        ], $config['jwt_ttl'] ?? 604800);

        // The session written by login() is unused by mobile clients
        $memberManager->logout();

        return $this->json([
            'token' => $token,
            'user' => [
                'id' => (int) $member['id'],
                'name' => $member['name'],
                'email' => $member['email'],
                'confirmed' => (bool) $member['confirmed']
            ],
        ]);
    }

    /**
     * POST /api/v1/members/auth/recovery
     *
     * Sends a recovery email. The response is identical regardless of
     * whether the email matches an account, to prevent enumeration.
     *
     * @return string JSON
     */
    public function recoveryAction()
    {
        $validator = $this->createValidation();

        $validator->field('email')
                  ->required()
                  ->addRule('email');

        if (!$validator->isPassed()) {
            $this->response->setStatusCode(400);
            return $this->json(['error' => 'Invalid email']);
        }

        $email = (string) $this->request->getPost('email');
        $memberManager = $this->getModuleService('memberManager');
        $key = $memberManager->initiateRecovery($email);

        if ($key !== false) {
            $body = $this->renderMessage('recovery-confirm', [
                'link' => $this->request->getBaseUrl() . $this->createUrl('Members:Recovery@resetAction', [$key]),
            ]);

            $subject = $this->translator->translate('Please confirm password recovery');
            $mailer = $this->getService('Cms', 'mailer');

            $mailer->sendTo($email, $subject, $body);
        }

        $this->response->setStatusCode(202);
        return $this->json([
            'message' => 'If an account exists with that email, a reset link has been sent.',
        ]);
    }

    /**
     * GET /api/v1/members/auth/whoami
     *
     * Validates the current token and returns fresh user data.
     *
     * @return string JSON
     */
    public function whoamiAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $member = $memberManager->fetchByIdForCurrentLang($this->getAuthUserId());

        if ($member === false) {
            $this->response->setStatusCode(401);
            return $this->json(['error' => 'Unauthorized']);
        }

        return $this->json([
            'id' => $member->getId(),
            'name' => $member->getDisplayName(),
            'email' => $member->getEmail(),
            'confirmed' => (bool) $member->getConfirmed(),
            'avatar' => $member->hasAvatar() ? $member->getAvatarUrl(MemberEntity::AVATAR_SMALL) : null
        ]);
    }
}