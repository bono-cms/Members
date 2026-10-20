<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Controller\Api;

final class Profile extends AbstractApiController
{
    /**
     * GET /api/v1/members/profile
     *
     * Returns own profile plus the tag picker payload.
     *
     * @return string JSON
     */
    public function indexAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberTagService = $this->getModuleService('memberTagService');
        $changeService = $this->getModuleService('profileChangeService');

        $memberId = $this->getAuthUserId();
        $member = $memberManager->fetchByIdForCurrentLang($memberId);

        if ($member === false) {
            $this->response->setStatusCode(404);
            return $this->json(['error' => 'Member not found']);
        }

        return $this->json([
            'user' => $this->presentMember($member),
            'tags' => [
                'selected' => $memberTagService->fetchSelectedIds($memberId),
                'available' => $memberTagService->fetchPickerPayload(),
            ],
            'pendingChanges' => $changeService->fetchPendingForMember($memberId),
        ]);
    }

    /**
     * POST /api/v1/members/profile/update
     *
     * Mirrors the web profile edit form. Fields go through moderation.
     *
     * @return string JSON
     */
    public function updateAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $changeService = $this->getModuleService('profileChangeService');
        $memberTagService = $this->getModuleService('memberTagService');
        $languageManager = $this->getService('Cms', 'languageManager');

        $memberId = $this->getAuthUserId();

        $validator = $this->createValidation();

        $validator->field('translation.*.name')
                  ->required()
                  ->addRule('minlength', null, ['min' => 2]);

        $validator->file('file')
                  ->addRule('image');

        if (!$validator->isPassed()) {
            $this->response->setStatusCode(422);
            return $this->json(['errors' => $validator->getErrors()]);
        }

        $input = $this->request->getAll();

        // The client may key translation rows by id (translation[1]) or
        // by language code (translation[en]). Normalize to id-keyed rows
        // and keep only the language requested via ?lang=.
        $requestLangId = $this->getRequestLanguageId();

        $translations = isset($input['data']['translation'])
            ? (array) $input['data']['translation']
            : [];

        $normalized = [];

        foreach ($translations as $key => $row) {
            $langId = is_numeric($key)
                ? (int) $key
                : (int) $languageManager->fetchIdByCode((string) $key);

            if ($langId > 0 && $langId === $requestLangId) {
                $normalized[$langId] = $row;
            }
        }

        $input['data']['translation'] = $normalized;

        $messageKey = $changeService->updateProfile($memberId, $input, '');

        $submittedTags = (array) $this->request->getPost('tags', []);
        $safeTags = $memberTagService->sanitizeSelection($submittedTags);
        $memberTagService->saveSelection($memberId, $safeTags);

        $member = $memberManager->fetchByIdForCurrentLang($memberId);

        return $this->json([
            'message' => $this->translator->translate($messageKey),
            'user' => $this->presentMember($member),
            'pendingChanges' => $changeService->fetchPendingForMember($memberId)
        ]);
    }

    /**
     * POST /api/v1/members/profile/avatar
     *
     * Multipart avatar upload through the moderation pipeline.
     *
     * @return string JSON
     */
    public function avatarAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $changeService = $this->getModuleService('profileChangeService');

        $memberId = $this->getAuthUserId();

        $validator = $this->createValidation();
        $validator->file('file')->required()->addRule('image');

        if (!$validator->isPassed()) {
            $this->response->setStatusCode(422);
            return $this->json(['errors' => $validator->getErrors()]);
        }

        $input = [
            'data' => ['member' => ['id' => $memberId]],
            'files' => $this->request->getFiles(),
        ];

        $messageKey = $changeService->updateProfile($memberId, $input, '');
        $member = $memberManager->fetchByIdForCurrentLang($memberId);

        return $this->json([
            'message' => $this->translator->translate($messageKey),
            'avatar' => $member->hasAvatar() ? $member->getAvatarUrl('128x128') : null,
        ]);
    }

    /**
     * POST /api/v1/members/profile/password
     *
     * Direct password update. Bypasses moderation, as on the web.
     *
     * @return string JSON
     */
    public function passwordAction()
    {
        $memberManager = $this->getModuleService('memberManager');

        $validator = $this->createValidation();

        $validator->field('password')
                  ->required()
                  ->addRule('minlength', null, ['min' => 6]);

        if (!$validator->isPassed()) {
            $this->response->setStatusCode(422);
            return $this->json(['errors' => $validator->getErrors()]);
        }

        $memberId = $this->getAuthUserId();
        $password = (string) $this->request->getPost('password');

        $memberManager->updatePassword($memberId, $password);

        $this->response->setStatusCode(204);

        return $this->json([
            'success' => true
        ]);
    }

    /**
     * Shapes a MemberEntity for the owner's own profile view.
     *
     * @param \Members\Service\MemberEntity $member
     * @return array
     */
    private function presentMember($member): array
    {
        return [
            'id' => $member->getId(),
            'name' => $member->getDisplayName(),
            'login' => $member->getLogin(),
            'email' => $member->getEmail(),
            'confirmed' => (bool) $member->getConfirmed(),
            'company' => $member->getCompany(),
            'phone' => $member->getPhone(),
            'country' => $member->getCountry(),
            'address' => $member->getAddress(),
            'title' => $member->getTitle(),
            'bio' => $member->getBio(),
            'avatar' => $member->hasAvatar() ? $member->getAvatarUrl('256x256') : null,
        ];
    }
}