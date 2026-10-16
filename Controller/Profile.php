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
        $member = $memberManager->fetchById($memberManager->getMember('id'));

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
        $member = $memberManager->fetchById($memberManager->getMember('id'), true);

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
        $validator = $this->createValidation();

        $validator->field('member.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->field('member.password')
                  ->addRule('minlength', null, ['min' => 6]);

        $validator->file('file')
                  ->addRule('image');

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors()
            ]);
        }

        $memberManager = $this->getModuleService('memberManager');

        $input = $this->request->getPost();

        // Force the logged-in member's id so a caller can't hijack another account
        $input['member']['id'] = $memberManager->getMember('id');

        $memberManager->update($this->request->getAll());

        $this->flashBag->set('success', $this->translator->translate('Your profile has been updated'));

        return $this->json([
            'refresh' => true
        ]);
    }
}