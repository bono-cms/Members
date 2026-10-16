<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Krystal\Stdlib\VirtualEntity;

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

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Login'))
             ->setSeo(false);

        return $this->view->render('member-login', [
            'page' => $page
        ]);
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
     * Performs a login
     * 
     * @return string
     */
    private function loginAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $identifier = $this->request->getPost('identifier');

        $validator = $this->createValidation();

        // Register a custom field rule that authenticates the submitted credentials against the member manager.
        $validator->setFieldRule('auth', function ($password) use ($memberManager, $identifier) {
            return $memberManager->login($identifier, $password);
        }, 'Invalid email or password');

        $validator->field('identifier')
                  ->required();

        $validator->field('password')
                  ->required()
                  ->addRule('auth');

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        // Honor the intended destination stashed by AuthRequiredMiddleware, falling back to the profile page on a direct login.
        $redirect = $this->sessionBag->has('login_redirect') ? $this->sessionBag->getFlashed('login_redirect') : $this->createUrl('Members:Profile@indexAction');

        return $this->json([
            'redirect' => $redirect
        ]);
    }
}