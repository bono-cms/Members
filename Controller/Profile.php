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
        $changeService = $this->getModuleService('profileChangeService');

        $memberId = (int) $memberManager->getMember('id');
        $member = $memberManager->fetchById($memberId, true);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Edit profile'))
             ->setSeo(false);

        return $this->view->render('member-profile', [
            'page' => $page,
            'member' => $member,
            'pending' => $changeService->fetchPendingForMember($memberId)
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
        $changeService = $this->getModuleService('profileChangeService');

        $input = $this->request->getAll();
        $password = isset($input['data']['member']['password']) ? $input['data']['member']['password'] : '';

        unset($input['data']['member']['password']);

        $messageKey = $changeService->updateProfile($memberManager->getMember('id'), $input, $password);

        $this->flashBag->set('success', $this->translator->translate($messageKey));

        return $this->json([
            'refresh' => true
        ]);
    }
}