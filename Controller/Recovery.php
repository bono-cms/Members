<?php

/**
 * This file is part of the Bono CMS
 * 
 * Copyright (c) No Global State Lab
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Krystal\Validate\Pattern;

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

        return $this->renderMessage('recovery-reset', array(
            'password' => $password
        ));
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
        $body = $this->renderMessage('recovery-confirm', array(
            'link' => $this->request->getBaseUrl() . $this->createUrl('Members:Recovery@resetAction', array($key))
        ));

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
        $input = $this->request->getPost();

        $formValidator = $this->createValidator(array(
            'input' => array(
                'source' => $input,
                'definition' => array(
                    'email' => new Pattern\Email()
                )
            )
        ));

        if ($formValidator->isValid()) {
            $memberManager = $this->getModuleService('memberManager');
            $key = $memberManager->resetKey($input['email']);

            if ($key !== false) {
                $this->sendConfirmationLink($input['email'], $key);
                return 1;
            } else {
                return 0;
            }

        } else {
            return $formValidator->getErrors();
        }
    }
}
