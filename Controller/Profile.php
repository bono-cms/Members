<?php

/**
 * This file is part of the Bono CMS
 * 
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller;

use Krystal\Stdlib\VirtualEntity;

final class Profile extends AbstractController
{
    /**
     * Renders the dashboard of the currently logged in member
     * 
     * @return string
     */
    public function indexAction()
    {
        $memberManager = $this->getModuleService('memberManager');

        if (!$memberManager->isLoggedIn()) {
            $this->sessionBag->set('login_redirect', $this->request->getUri());
            $this->response->redirect($this->createUrl('Members:Auth@indexAction'));
            return null;
        }

        $memberId = $memberManager->getMember('id');
        $member = $memberManager->fetchById($memberId);

        if ($member === false) {
            $memberManager->logout();
            $this->response->redirect($this->createUrl('Members:Auth@indexAction'));
            return null;
        }

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('My account'))
             ->setSeo(false);

        return $this->view->render('member-dashboard', [
            'page' => $page,
            'member' => $member
        ]);
    }

    /**
     * Renders the profile edit form
     * 
     * @return string
     */
    public function editAction()
    {
        $memberManager = $this->getModuleService('memberManager');

        if (!$memberManager->isLoggedIn()) {
            $this->sessionBag->set('login_redirect', $this->request->getUri());
            $this->response->redirect($this->createUrl('Members:Auth@indexAction'));
            return null;
        }

        $memberId = $memberManager->getMember('id');
        $member = $memberManager->fetchById($memberId, true);

        if ($member === false) {
            $memberManager->logout();
            $this->response->redirect($this->createUrl('Members:Auth@indexAction'));
            return null;
        }

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Edit profile'))
             ->setSeo(false);

        return $this->view->render('member-profile', [
            'page' => $page,
            'member' => $member
        ]);
    }

    /**
     * Handles the profile edit form submission
     * 
     * @return string|void
     */
    public function updateAction()
    {
        if (!$this->request->isPost()) {
            return $this->response->redirect($this->createUrl('Members:Profile@editAction'));
        }

        $memberManager = $this->getModuleService('memberManager');

        if (!$memberManager->isLoggedIn()) {
            $this->flashBag->set('danger', $this->translator->translate('You must be logged in to update your profile'));
            $this->response->redirect($this->createUrl('Members:Auth@indexAction'));
            return null;
        }

        $input = $this->request->getPost();

        $validator = $this->createValidation();

        $validator->field('member.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('member.password')
                  ->addRule('minlength', null, ['min' => 6]);

        if (!$validator->isPassed()) {
            $this->flashBag->set('danger', $this->translator->translate('Please correct the errors in the form'));
            $this->response->redirect($this->createUrl('Members:Profile@editAction'));
            return null;
        }

        // Force the logged-in member's id so a caller can't hijack another account
        $input['member']['id'] = $memberManager->getMember('id');

        $memberManager->save($input);

        $this->flashBag->set('success', $this->translator->translate('Your profile has been updated'));
        $this->response->redirect($this->createUrl('Members:Profile@indexAction'));
        return null;
    }
}