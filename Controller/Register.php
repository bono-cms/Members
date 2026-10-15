<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Krystal\Stdlib\VirtualEntity;

final class Register extends AbstractController
{
    /**
     * Renders registration page or handles submission
     * 
     * @return string
     */
    public function indexAction()
    {
        if ($this->request->isPost()) {
            return $this->saveAction();
        }

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Registration'))
             ->setSeo(false);

        $this->loadSitePlugins();

        return $this->view->render('member-register', [
            'page' => $page,
            'languages' => $this->getService('Cms', 'languageManager')->fetchAll(true)
        ]);
    }

    /**
     * Confirms user account by activation key
     * 
     * @param string $key
     * @return string
     */
    public function confirmAction($key)
    {
        $memberManager = $this->getModuleService('memberManager');
        $confirmed = $memberManager->confirm($key);

        return $this->renderMessage('confirmation', [
            'confirmed' => $confirmed
        ]);
    }

    /**
     * Handles member registration submission
     * 
     * @return string
     */
    private function saveAction()
    {
        $validator = $this->createValidation();

        $validator->field('member.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('member.email')
                  ->required()
                  ->addRule('email');

        $validator->field('member.login')
                  ->required();

        $validator->field('member.password')
                  ->required()
                  ->addRule('minlength', null, ['min' => 6]);

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        $input = $this->request->getPost();
        $memberManager = $this->getModuleService('memberManager');

        if (!$memberManager->isFree($input['member']['email'])) {
            return $this->json([
                'errors' => [
                    'member.email' => 'This email is already taken'
                ]
            ]);
        }

        // New registrations default to unconfirmed
        $input['member']['confirmed'] = '0';
        $input['member']['subscriber'] = '0';

        // Pass the whole payload so translations are persisted too
        $memberManager->save($input);

        return $this->json([
            'success' => 'Registration successful. Please check your email to confirm your account.'
        ]);
    }
}