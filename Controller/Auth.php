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
     * Renders login page or handles login submission
     * 
     * @return string
     */
    public function indexAction()
    {
        if ($this->request->isPost()) {
            return $this->loginAction();
        }

        return $this->formAction();
    }

    /**
     * Logs out the current member and redirects to home
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
     * Renders the login form
     * 
     * @return string
     */
    private function formAction()
    {
        return $this->view->render('member-login', [
            'languages' => $this->getService('Cms', 'languageManager')->fetchAll(true)
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

        $validator->field('identifier')
                  ->required();

        $validator->field('password')
                  ->required();

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        $input = $this->request->getPost();

        $memberManager = $this->getModuleService('memberManager');
        $success = $memberManager->login($input['identifier'], $input['password']);

        if (!$success) {
            return $this->json([
                'errors' => [
                    'identifier' => 'Invalid email or password'
                ]
            ]);
        }

        return $this->json([
            'redirect' => $this->createUrl('Members:Profile@indexAction')
        ]);
    }
}