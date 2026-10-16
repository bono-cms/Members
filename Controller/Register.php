<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Krystal\Stdlib\VirtualEntity;

final class Register extends AbstractController
{
    /**
     * Renders registration page or handles submission
     * 
     * @return string
     */
    public function indexAction()
    {
        if ($this->request->isPost()) {
            return $this->saveAction();
        }

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Registration'))
             ->setSeo(false);

        return $this->view->render('member-register', [
            'page' => $page
        ]);
    }

    /**
     * Confirms user account by activation key
     * 
     * @param string $key
     * @return string
     */
    public function confirmAction($key)
    {
        $memberManager = $this->getModuleService('memberManager');
        $confirmed = $memberManager->confirm($key);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Account confirmation'))
             ->setSeo(false);

        return $this->view->render('member-confirmation', [
            'page' => $page,
            'confirmed' => $confirmed
        ]);
    }

    /**
     * Renders the "check your email" page after successful registration
     * 
     * @return string|void
     */
    public function successAction()
    {
        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Registration complete'))
             ->setSeo(false);

        return $this->view->render('member-register-success', [
            'page' => $page
        ]);
    }

    /**
     * Handles member registration submission
     * 
     * @return string
     */
    private function saveAction()
    {
        $validator = $this->createValidation();

        $validator->field('member.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('member.email')
                  ->required()
                  ->addRule('email');

        $validator->field('member.login')
                  ->required();

        $validator->field('member.password')
                  ->required()
                  ->addRule('minlength', null, ['min' => 6]);

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        $input = $this->request->getPost();
        $memberManager = $this->getModuleService('memberManager');

        if (!$memberManager->isFree($input['member']['email'])) {
            return $this->json([
                'errors' => [
                    'member.email' => 'This email is already taken'
                ]
            ]);
        }

        // New registrations default to unconfirmed and unsubscribed
        $input['member']['confirmed'] = '0';
        $input['member']['subscriber'] = '0';

        // Generate the activation key BEFORE save() so it lands in the same INSERT
        $key = md5(uniqid(rand(), true));
        $input['member']['activation_key'] = $key;

        // Persist member + translations
        if (!$memberManager->save($input)) {
            return $this->json([
                'error' => 'An unexpected error occurred during saving'
            ]);
        }

        // Send the confirmation email using the SAME key
        $this->sendConfirmationEmail(
            $input['member']['email'],
            $input['member']['name'],
            $key
        );

        return $this->json([
            'redirect' => $this->createUrl('Members:Register@successAction')
        ]);
    }

    /**
     * Renders and sends the registration confirmation email
     *
     * @param string $email Recipient address
     * @param string $name Recipient name
     * @param string $key Activation key (already persisted on the member row)
     * @return boolean
     */
    private function sendConfirmationEmail($email, $name, $key)
    {
        $body = $this->renderMessage('register', [
            'name' => $name,
            'link' => $this->request->getBaseUrl() . $this->createUrl('Members:Register@confirmAction', [$key])
        ]);

        $subject = $this->translator->translate('Please confirm your account');
        $mailer = $this->getService('Cms', 'mailer');

        $sent = $mailer->sendTo($email, $subject, $body);

        if (!$sent) {
            error_log(sprintf(
                'Members: failed to send registration email to %s', $email
            ));

            return false;
        }

        return true;
    }
}