<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

final class Recovery extends AbstractController
{
    /**
     * Renders recovery page or handles recovery submission
     * 
     * @return string
     */
    public function indexAction()
    {
        if ($this->request->isPost()) {
            return $this->recoveryAction();
        }

        return $this->formAction();
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

        return $this->renderMessage('recovery-reset', [
            'password' => $password
        ]);
    }

    /**
     * Renders the recovery form
     * 
     * @return string
     */
    private function formAction()
    {
        return $this->view->render('recovery-form', [
            'languages' => $this->getService('Cms', 'languageManager')->fetchAll(true)
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
     * Handles password recovery request
     * 
     * @return string
     */
    private function recoveryAction()
    {
        $validator = $this->createValidation();

        $validator->field('email')
                  ->required()
                  ->addRule('email');

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        $input = $this->request->getPost();

        $memberManager = $this->getModuleService('memberManager');
        $key = $memberManager->resetKey($input['email']);

        if ($key === false) {
            return $this->json([
                'errors' => [
                    'email' => 'No account was found with this email'
                ]
            ]);
        }

        $this->sendConfirmationLink($input['email'], $key);

        return $this->json([
            'refresh' => true
        ]);
    }
}