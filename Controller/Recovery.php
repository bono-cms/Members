<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Krystal\Stdlib\VirtualEntity;

final class Recovery extends AbstractController
{
    /**
     * Renders recovery page or handles recovery submission
     * 
     * @return string|void
     */
    public function indexAction()
    {
        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Password recovery'))
             ->setSeo(false);

        return $this->view->render('recovery-form', [
            'page' => $page
        ]);
    }

    /**
     * Resets a password by activation key and displays the new one
     * 
     * @param string $key Activation key
     * @return string
     */
    public function resetAction($key)
    {
        $memberManager = $this->getModuleService('memberManager');
        $password = $memberManager->resetPassword($key);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Password reset'))
             ->setSeo(false);

        return $this->view->render('member-recovery-reset', [
            'page' => $page,
            'password' => $password
        ]);
    }

    /**
     * Sends a confirmation link to the email associated with the account
     * 
     * @param string $email
     * @param string $key Activation key
     * @return boolean
     */
    private function sendConfirmationLink($email, $key)
    {
        $body = $this->renderMessage('recovery-confirm', [
            'link' => $this->request->getBaseUrl() . $this->createUrl('Members:Recovery@resetAction', [$key])
        ]);

        $subject = $this->translator->translate('Please confirm password recovery');
        $mailer = $this->getService('Cms', 'mailer');

        return $mailer->sendTo($email, $subject, $body);
    }

    /**
     * Handles an API password recovery request
     * 
     * The response is deliberately identical whether or not the
     * email matches an existing account, to prevent account
     * enumeration.
     * 
     * @return string JSON response
     */
    public function processAction()
    {
        $validator = $this->createValidation();

        $validator->field('email')
                  ->required()
                  ->addRule('email');

        if (!$validator->isPassed()) {
            return $this->json([
                'success' => false,
                'errors' => $validator->getErrors()
            ]);
        }

        $email = $this->request->getPost('email');

        $memberManager = $this->getModuleService('memberManager');
        $key = $memberManager->resetKey($email);

        // Vague success in both branches
        if ($key !== false) {
            $this->sendConfirmationLink($email, $key);
        }

        return $this->json([
            'redirect' => $this->createUrl('Members:Recovery@successAction')
        ]);
    }

    /**
     * Renders the "check your email" page shown after a recovery request
     * 
     * @return string
     */
    public function successAction()
    {
        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Recovery email sent'))
             ->setSeo(false);

        return $this->view->render('member-recovery-success', [
            'page' => $page
        ]);
    }   
}