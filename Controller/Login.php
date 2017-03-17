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

use Site\Controller\AbstractController;
use Krystal\Validate\Pattern;

final class Login extends AbstractController
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
     * Renders the form
     * 
     * @return string
     */
    private function formAction()
    {
        return $this->view->render('member-login');
    }

    /**
     * Performs a login
     * 
     * @return string
     */
    private function loginAction()
    {
        $input = $this->request->getPost();

        $formValidator = $this->createValidator(array(
            'input' => array(
                'source' => $input,
                'definition' => array(
                    'email' => new Pattern\Email(),
                    'password' => new Pattern\Password()
                )
            )
        ));

        if ($formValidator->isValid()) {

            $memberManager = $this->getModuleService('memberManager');
            return $memberManager->login($input['email'], $input['password']) ? 1 : 0;

        } else {
            return $formValidator->getErrors();
        }
    }
}
