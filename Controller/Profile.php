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
        $memberTagService = $this->getModuleService('memberTagService');

        $id = $memberManager->getMember('id');

        $member = $memberManager->fetchByIdForCurrentLang($id);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('My account'))
             ->setSeo(false);

        return $this->view->render('member-dashboard', [
            'page' => $page,
            'member' => $member,
            'tagGroups' => $memberTagService->fetchGroupedByMember((int) $id)
        ]);
    }

    /**
     * Renders the profile edit form
     *
     * The form combines the editable profile fields and the tag
     * picker in a single page: the profile page is the single place
     * a member edits their account, so tags live there too.
     *
     * @return string
     */
    public function editAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $changeService = $this->getModuleService('profileChangeService');
        $memberTagService = $this->getModuleService('memberTagService');

        $memberId = (int) $memberManager->getMember('id');
        $member = $memberManager->fetchById($memberId, true);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Edit profile'))
             ->setSeo(false);

        return $this->view->render('member-profile', [
            'page' => $page,
            'member' => $member,
            'pending' => $changeService->fetchPendingForMember($memberId),
            'payload' => $memberTagService->fetchPickerPayload(),
            'selected' => $memberTagService->fetchSelectedIds($memberId)
        ]);
    }

    /**
     * Handles the profile edit form submission
     *
     * Persists both the profile changes (which may go through the
     * moderation pipeline) and the member's tag selection (which
     * never goes through moderation — it's a direct write, same as
     * the admin path in Members::saveAction()).
     *
     * @return string|void
     */
    public function updateAction()
    {
        $validator = $this->createValidation();

        $validator->field('translation.*.name')
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
        $memberTagService = $this->getModuleService('memberTagService');

        $memberId = (int) $memberManager->getMember('id');

        $input = $this->request->getAll();
        $password = isset($input['data']['member']['password']) ? $input['data']['member']['password'] : '';

        unset($input['data']['member']['password']);

        $messageKey = $changeService->updateProfile($memberId, $input, $password);

        // Persist the tag selection. Sanitizer drops any id that is
        // not an active tag, so the member cannot attach an inactive
        // or foreign tag by tampering with the form.
        $submittedTags = (array) $this->request->getPost('tags', []);
        $safeTags = $memberTagService->sanitizeSelection($submittedTags);
        $memberTagService->saveSelection($memberId, $safeTags);

        $this->flashBag->set('success', $this->translator->translate($messageKey));

        return $this->json([
            'refresh' => true
        ]);
    }
}