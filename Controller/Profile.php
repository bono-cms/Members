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

        $pending = $changeService->isModerationEnabled()
            ? $changeService->fetchPendingForMember($memberId)
            : [];

        return $this->view->render('member-profile', [
            'page' => $page,
            'member' => $member,
            'pending' => $pending
        ]);
    }

    /**
     * Handles the profile edit form submission
     *
     * Fields that are classified as "auto" (skip bucket or moderation
     * disabled) are applied to the live row immediately. Fields that
     * are classified as "moderate" are staged for admin review.
     *
     * The password is handled separately: it never enters the staging
     * pipeline and is always applied to the live row.
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

        // Force the logged-in member's id so a caller can't hijack another account
        $input['data']['member']['id'] = $memberManager->getMember('id');

        // The password is never staged. Extract it before the moderation
        // pipeline sees it so it isn't accidentally persisted as plain text.
        $password = isset($input['data']['member']['password']) ? $input['data']['member']['password'] : '';

        unset($input['data']['member']['password']);

        if ($changeService->isModerationEnabled()) {
            $result = $changeService->submit($input);

            if ($result['applied'] > 0 && $result['staged'] > 0) {
                $message = $this->translator->translate('Some changes were applied immediately; the rest are pending review.');
            } elseif ($result['staged'] > 0) {
                $message = $this->translator->translate('Your changes were submitted for review.');
            } else {
                $message = $this->translator->translate('Your profile has been updated.');
            }
        } else {
            $memberManager->update($input);
            $message = $this->translator->translate('Your profile has been updated');
        }

        if ($password !== '') {
            $memberManager->updatePassword($memberManager->getMember('id'), $password);
        }

        $this->flashBag->set('success', $message);

        return $this->json([
            'refresh' => true
        ]);
    }
}