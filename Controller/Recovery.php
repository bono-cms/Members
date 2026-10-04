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
     * Renders recovery page
     * 
     * @return string
     */
    public function indexAction()
    {
        if ($this->request->isPost()) {
            return $this->recoveryAction();
        } else {
            return $this->formAction();
        }
    }

    /**
     * Resets a password
     * 
     * @param string $key
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
     * Renders the form
     * 
     * @return string
     */
    private function formAction()
    {
        return $this->view->render('recovery-form', [
            'languages' => $this->getService('Cms', 'languageManager')->fetchAll(true),
        ]);
    }

    /**
     * Sends confirmation code
     * 
     * @param string $email
     * @param string $key
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
     * Renders recovery
     * 
     * @return string
     */
    private function recoveryAction()
    {
        $validator = $this->createValidation();

        $validator->field('email')
                  ->required()
                  ->addRule('email');

        if ($validator->isPassed()) {
            $input = $this->request->getPost();

            $memberManager = $this->getModuleService('memberManager');
            $key = $memberManager->resetKey($input['email']);

            if ($key !== false) {
                $this->sendConfirmationLink($input['email'], $key);
                return $this->json([
                    'refresh' => true
                ]);
            } else {
                return $this->json([
                    'errors' => [
                        'email' => 'No account was found with this email'
                    ]
                ]);
            }

        } else {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }
    }
}