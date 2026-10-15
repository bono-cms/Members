<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Site\Controller\AbstractController;
use Krystal\Stdlib\VirtualEntity;

final class Profile extends AbstractController
{
    /**
     * Renders the profile page of the currently logged in member
     * 
     * @return string
     */
    public function indexAction()
    {
        $memberManager = $this->getModuleService('memberManager');

        if (!$memberManager->isLoggedIn()) {
            $this->response->redirect('/login');
            return;
        }

        $memberId = $memberManager->getMember('id');
        $member = $memberManager->fetchById($memberId, true);

        if ($member === false) {
            $this->response->redirect('/login');
            return;
        }

        // Append a breadcrumb
        $this->view->getBreadcrumbBag()
                   ->addOne('Profile');

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('My profile'))
             ->setSeo(false);

        $this->loadSitePlugins();

        return $this->view->render('member-profile', [
            'page' => $page,
            'member' => $member
        ]);
    }

    /**
     * Updates the profile of the currently logged in member
     * 
     * @return string
     */
    public function updateAction()
    {
        if (!$this->request->isPost()) {
            return null;
        }

        $memberManager = $this->getModuleService('memberManager');

        if (!$memberManager->isLoggedIn()) {
            return $this->json([
                'error' => 'You must be logged in to update your profile'
            ]);
        }

        $input = $this->request->getPost();

        $validator = $this->createValidation();

        $validator->field('member.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('member.password')
                  ->addRule('minlength', null, ['min' => 6]);

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        // Force the logged-in member's id so a caller can't hijack another account
        $input['member']['id'] = $memberManager->getMember('id');

        // Route through the same save() the admin uses — persists base fields + translations
        $memberManager->save($input);

        $this->flashBag->set('success', 'Settings have been updated successfully');

        return $this->json([
            'refresh' => true
        ]);
    }
}