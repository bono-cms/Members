<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

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

        return $this->renderMessage('confirmation', [
            'confirmed' => $memberManager->confirm($key)
        ]);
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
        $vars['link'] = $this->request->getBaseUrl() . $this->createUrl('Members:Register@confirmAction', [$vars['key']]);

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

        $validator = $this->createValidation();

        $validator->field('name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('email')
                  ->required()
                  ->addRule('email');

        $validator->field('password')
                  ->required()
                  ->addRule('minlength', null, ['min' => 6]);

        $validator->field('password_confirm')
                  ->required()
                  ->addRule('identity', null, ['value' => $input['password']]);

        if ($validator->isPassed()) {
            $memberManager = $this->getModuleService('memberManager');
            $member = $memberManager->register($input);

            if ($member !== false) {
                $this->sendConfirmationCode($member);
                return $this->json([
                    'refresh' => true
                ]);
            } else {
                return $this->json([
                    'errors' => [
                        'email' => 'An account with this email already exists'
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