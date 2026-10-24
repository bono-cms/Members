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
        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Registration'))
             ->setSeo(false);

        return $this->view->render('member-register-form', [
            'page' => $page,
            'defaultLangId' => $this->getService('Cms', 'languageManager')->getDefaultId()
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
     * Re-sends the confirmation email to the currently logged-in member
     *
     * @return void
     */
    public function resendAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $member = $memberManager->fetchById($memberManager->getMember('id'));

        if ($member->getConfirmed()) {
            $this->flashBag->set('info', $this->translator->translate('Your account is already confirmed.'));
            return $this->response->redirect($this->createUrl('Members:Profile@indexAction'));
        }

        $key = $memberManager->refreshActivationKey($member->getId());

        $this->sendConfirmationEmail(
            $member->getEmail(),
            $member->getName(),
            $key
        );

        $this->flashBag->set('success', $this->translator->translate('A new confirmation email has been sent.'));
        return $this->response->redirect($this->createUrl('Members:Profile@indexAction'));
    }

    /**
     * Handles member registration submission
     *
     * @return string
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

        $validator->field('member.password')
                  ->required()
                  ->addRule('minlength', null, ['min' => 6]);

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        $input = $this->request->getAll();

        $key = $memberManager->generateActivationKey();
        $input['data']['member']['activation_key'] = $key;

        if (!$memberManager->add($input)) {
            return $this->json([
                'error' => 'An unexpected error occurred during saving'
            ]);
        }

        $this->sendConfirmationEmail(
            $input['data']['member']['email'],
            $input['data']['member']['name'],
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