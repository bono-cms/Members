<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Site\Controller\AbstractController;

final class Auth extends AbstractController
{
    /**
     * Renders login page
     * 
     * @return string
     */
    public function indexAction()
    {
        if ($this->request->isPost()) {
            return $this->loginAction();
        } else {
            return $this->formAction();
        }
    }

    /**
     * Log outs a member
     * 
     * @return void
     */
    public function logoutAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberManager->logout();

        $this->response->redirect('/');
    }

    /**
     * Renders the form
     * 
     * @return string
     */
    private function formAction()
    {
        return $this->view->render('member-login', [
            'languages' => $this->getService('Cms', 'languageManager')->fetchAll(true),
        ]);
    }

    /**
     * Performs a login
     * 
     * @return string
     */
    private function loginAction()
    {
        $validator = $this->createValidation();

        $validator->field('email')
                  ->required()
                  ->addRule('email');

        $validator->field('password')
                  ->required();

        if ($validator->isPassed()) {
            $input = $this->request->getPost();

            $memberManager = $this->getModuleService('memberManager');

            $success = $memberManager->login($input['email'], $input['password']);

            return $success ? $this->json([
                'refresh' => true
            ]) : $this->json([
                'errors' => [
                    'email' => 'Invalid email or password'
                ]
            ]);

        } else {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }
    }
}
