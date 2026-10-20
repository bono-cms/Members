<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Api;

final class Register extends AbstractApiController
{
    /**
     * POST /api/v1/members/register
     *
     * @return string JSON
     */
    public function saveAction()
    {
        $memberManager = $this->getModuleService('memberManager');

        $validator = $this->createValidation();

        $validator->setFieldRule('uniqueEmail', function ($email) use ($memberManager) {
            return $memberManager->isFree($email);
        }, $this->translator->translate('This email is already taken'));

        $validator->field('translation.*.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('member.email')
                  ->required()
                  ->addRule('email')
                  ->addRule('uniqueEmail');

        $validator->field('member.login')
                  ->required();

        $validator->field('member.password')
                  ->required()
                  ->addRule('minlength', null, ['min' => 6]);

        if (!$validator->isPassed()) {
            $this->response->setStatusCode(422);
            return $this->json(['errors' => $validator->getErrors()]);
        }

        $input = $this->request->getAll();
        $key = $memberManager->generateActivationKey();
        $input['data']['member']['activation_key'] = $key;

        if (!$memberManager->add($input)) {
            $this->response->setStatusCode(500);
            return $this->json(['error' => 'An unexpected error occurred during saving']);
        }

        $this->sendConfirmationEmail(
            $input['data']['member']['email'],
            $input['data']['member']['name'],
            $key
        );

        $this->response->setStatusCode(201);
        return $this->json([
            'message' => 'Registration successful. Please check your email to confirm your account.',
        ]);
    }

    /**
     * POST /api/v1/members/register/resend
     *
     * @return string JSON
     */
    public function resendAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $member = $memberManager->fetchById($this->getAuthUserId());

        if ($member === false) {
            $this->response->setStatusCode(401);
            return $this->json(['error' => 'Unauthorized']);
        }

        if ($member->getConfirmed()) {
            $this->response->setStatusCode(409);
            return $this->json(['error' => 'Your account is already confirmed.']);
        }

        $key = $memberManager->refreshActivationKey($member->getId());
        $this->sendConfirmationEmail($member->getEmail(), $member->getName(), $key);

        return $this->json([
            'message' => 'A new confirmation email has been sent.'
        ]);
    }

    /**
     * Renders and sends the registration confirmation email.
     *
     * The link points at the web confirmation route so the recipient can
     * activate their account from a browser. The API deliberately does not
     * expose a confirmation-by-key endpoint — that flow belongs to the web
     * (or to admins via the member form).
     *
     * @param string $email
     * @param string $name
     * @param string $key
     * @return bool
     */
    private function sendConfirmationEmail($email, $name, $key)
    {
        $body = $this->renderMessage('register', [
            'name' => $name,
            'link' => $this->request->getBaseUrl() . $this->createUrl('Members:Register@confirmAction', [$key]),
        ]);

        $subject = $this->translator->translate('Please confirm your account');
        $mailer = $this->getService('Cms', 'mailer');

        return $mailer->sendTo($email, $subject, $body);
    }
}