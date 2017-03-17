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

final class Register extends AbstractController
{
    /**
     * Renders register page
     * 
     * @return string
     */
    public function indexAction()
    {
        if ($this->request->isPost()) {
            return $this->registerAction();
        } else {
            return $this->formAction();
        }
    }

    /**
     * Confirms an account
     * 
     * @param string $key Confirmation key
     * @return string
     */
    public function confirmAction($key)
    {
        $memberManager = $this->getModuleService('memberManager');

        return $this->renderMessage('confirmation', array(
            'confirmed' => $memberManager->confirm($key)
        ));
    }

    /**
     * Renders the form
     * 
     * @return string
     */
    private function formAction()
    {
        return $this->view->render('member-registration');
    }

    /**
     * Sends confirmation code
     * 
     * @param array $vars
     * @return boolean
     */
    private function sendConfirmationCode(array $vars)
    {
        // Append confirmation URL
        $vars['link'] = $this->request->getBaseUrl() . $this->createUrl('Members:Register@confirmAction', array($vars['key']));

        $body = $this->renderMessage('register', $vars);
        $subject = $this->translator->translate('Please confirm your account');

        $mailer = $this->getService('Cms', 'mailer');

        return $mailer->sendTo($vars['email'], $subject, $body);
    }

    /**
     * Performs a registration
     * 
     * @return string
     */
    private function registerAction()
    {
        $input = $this->request->getPost();

        $formValidator = $this->createValidator(array(
            'input' => array(
                'source' => $input,
                'definition' => array(
                    'name' => new Pattern\Name(),
                    'email' => new Pattern\Email(),
                    'password' => new Pattern\Password(),
                    'password_confirm' => new Pattern\PasswordConfirmation($input['password'])
                )
            )
        ));

        if ($formValidator->isValid()) {
            $memberManager = $this->getModuleService('memberManager');
            $member = $memberManager->register($input);

            if ($member !== false) {
                $this->sendConfirmationCode($member);
                return 1;
            } else {
                return 0;
            }

        } else {
            return $formValidator->getErrors();
        }
    }
}
