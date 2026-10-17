<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Krystal\Http\FileTransfer\FileEntity;
use Krystal\Image\Tool\ImageManagerInterface;
use Members\Storage\MySQL\MemberMapper;
use Members\Storage\MySQL\MemberChangeMapper;
use Throwable;

/**
 * Handles the profile-change workflow: submitting edits for review,
 * approving or rejecting pending submissions, and applying approved
 * changes to the live member row.
 *
 * Read-only member access stays in MemberManager. This service owns
 * the write side of the moderation pipeline.
 */
final class ProfileChangeService
{
    /**
     * Relative path to the avatar staging directory
     *
     * @const string
     */
    const STAGING_DIR = '/data/uploads/module/members/pending/';

    /**
     * Member mapper instance
     *
     * @var \Members\Storage\MySQL\MemberMapper
     */
    private $memberMapper;

    /**
     * Pending change mapper instance
     *
     * @var \Members\Storage\MySQL\MemberChangeMapper
     */
    private $changeMapper;

    /**
     * Avatar image manager instance
     *
     * @var \Krystal\Image\Tool\ImageManagerInterface
     */
    private $avatarImageManager;

    /**
     * Absolute path to the project root directory
     *
     * @var string
     */
    private $projectRoot;

    /**
     * Raw moderation config from the module configuration
     *
     * @var array
     */
    private $moderationConfig = [];

    /**
     * Normalized moderation config, cached after first read
     *
     * @var array|null
     */
    private $resolvedConfig = null;

    /**
     * State initialization
     *
     * @param \Members\Storage\MySQL\MemberMapper $memberMapper
     * @param \Members\Storage\MySQL\MemberChangeMapper $changeMapper
     * @param \Krystal\Image\Tool\ImageManagerInterface $avatarImageManager
     * @param array $moderationConfig
     * @param string|null $projectRoot Absolute project root; defaults to cwd
     * @return void
     */
    public function __construct(
        MemberMapper $memberMapper,
        MemberChangeMapper $changeMapper,
        ImageManagerInterface $avatarImageManager,
        array $moderationConfig = [],
        $projectRoot = null
    ) {
        $this->memberMapper = $memberMapper;
        $this->changeMapper = $changeMapper;
        $this->avatarImageManager = $avatarImageManager;
        $this->moderationConfig = $moderationConfig;
        $this->projectRoot = $projectRoot ?: getcwd();
    }

    /**
     * Returns the normalized moderation config
     *
     * @return array
     */
    private function getModeration()
    {
        if ($this->resolvedConfig !== null) {
            return $this->resolvedConfig;
        }

        $enabled = !empty($this->moderationConfig['enabled']);

        $fields = isset($this->moderationConfig['fields'])
            ? $this->moderationConfig['fields']
            : [];

        $reserved = isset($fields['reserved']) ? (array) $fields['reserved'] : [];
        $skip = isset($fields['skip']) ? (array) $fields['skip'] : [];
        $base = isset($fields['base']) ? (array) $fields['base'] : [];
        $translatable = isset($fields['translatable']) ? (array) $fields['translatable'] : [];

        return $this->resolvedConfig = [
            'enabled' => $enabled,
            'reserved' => array_map('strtolower', $reserved),
            'skip' => array_map('strtolower', $skip),
            'base' => array_map('strtolower', $base),
            'translatable' => array_map('strtolower', $translatable)
        ];
    }

    /**
     * Checks whether moderation is currently active
     *
     * @return boolean
     */
    public function isModerationEnabled()
    {
        $config = $this->getModeration();

        return $config['enabled'];
    }

    /**
     * Returns the raw moderation field buckets
     *
     * @return array
     */
    public function getModerationFields()
    {
        $config = $this->getModeration();

        return [
            'reserved' => $config['reserved'],
            'skip' => $config['skip'],
            'base' => $config['base'],
            'translatable' => $config['translatable']
        ];
    }

    /**
     * Checks whether a field is reserved (never enters the pipeline)
     *
     * @param string $field
     * @return boolean
     */
    private function isReserved($field)
    {
        $config = $this->getModeration();

        return in_array(strtolower($field), $config['reserved'], true);
    }

    /**
     * Checks whether a base field should be staged for approval
     *
     * @param string $field
     * @return boolean
     */
    private function shouldStageBase($field)
    {
        $config = $this->getModeration();

        return $config['enabled'] && in_array(strtolower($field), $config['base'], true);
    }

    /**
     * Checks whether a translation field should be staged for approval
     *
     * @param string $field
     * @return boolean
     */
    private function shouldStageTranslation($field)
    {
        $config = $this->getModeration();

        return $config['enabled'] && in_array(strtolower($field), $config['translatable'], true);
    }

    /**
     * Checks whether a field belongs to the skip bucket (never staged)
     *
     * @param string $field
     * @return boolean
     */
    private function isSkipped($field)
    {
        $config = $this->getModeration();

        return in_array(strtolower($field), $config['skip'], true);
    }

    /**
     * Processes a profile edit
     *
     * Fields classified as "auto" (skip bucket or moderation off) are
     * applied to the live row immediately. Fields classified as
     * "moderate" are staged for approval with both their old and new
     * values captured in the payload.
     *
     * @param array $input Input data in the standard profile form shape
     * @return array
     */
    public function submit(array $input)
    {
        $memberId = (int) $input['data']['member']['id'];
        $liveRow = $this->memberMapper->findByPk($memberId);

        if (!$liveRow) {
            return ['applied' => 0, 'staged' => 0];
        }

        $submitted = $input['data']['member'];

        $stagedAvatar = null;
        $file = isset($input['files']['file']) ? $input['files']['file'] : false;

        if ($file instanceof FileEntity) {
            $stagedAvatar = $this->stageAvatar($file);
        }

        $autoFields = [];
        $stagedBase = [];
        $stagedTrans = [];

        foreach ($submitted as $field => $value) {
            if ($this->isReserved($field)) {
                continue;
            }

            if ($field === 'avatar') {
                if ($stagedAvatar !== null) {
                    $value = $stagedAvatar;
                } elseif (!empty($submitted['remove_avatar'])) {
                    $value = '';
                } else {
                    continue;
                }
            }

            $currentValue = isset($liveRow[$field]) ? $liveRow[$field] : '';

            if ((string) $currentValue === (string) $value) {
                continue;
            }

            if ($this->isSkipped($field) || !$this->shouldStageBase($field)) {
                $autoFields[$field] = $value;
            } else {
                $stagedBase[$field] = [
                    'old' => $currentValue,
                    'new' => $value
                ];
            }
        }

        $translations = isset($input['data']['translation'])
            ? $input['data']['translation']
            : [];

        $config = $this->getModeration();

        foreach ($translations as $langId => $row) {
            $liveTrans = $this->memberMapper->fetchTranslationByLang($memberId, $langId);
            $liveTrans = $liveTrans ? $liveTrans : [];

            foreach ($row as $field => $value) {
                if ($field === 'lang_id') {
                    continue;
                }

                if ($this->isReserved($field)) {
                    continue;
                }

                $currentValue = isset($liveTrans[$field]) ? $liveTrans[$field] : '';

                if ((string) $currentValue === (string) $value) {
                    continue;
                }

                if ($this->shouldStageTranslation($field)) {
                    $stagedTrans[$langId][$field] = [
                        'old' => $currentValue,
                        'new' => $value
                    ];
                } elseif (in_array(strtolower($field), $config['base'], true)
                       || in_array(strtolower($field), $config['skip'], true)) {
                    // Auto-apply only known, non-staged fields
                    $this->memberMapper->updateTranslationFieldByLang($memberId, (int) $langId, $field, $value);
                }
            }
        }

        if (!empty($autoFields)) {
            $this->applyBaseFields($memberId, $autoFields, $liveRow);
        }

        $stagedCount = 0;

        if (!empty($stagedBase) || !empty($stagedTrans)) {
            $this->changeMapper->insertSubmission($memberId, [
                'base' => $stagedBase,
                'translations' => $stagedTrans
            ]);

            $stagedCount = count($stagedBase) + array_sum(array_map('count', $stagedTrans));
        }

        return [
            'applied' => count($autoFields),
            'staged' => $stagedCount
        ];
    }

    /**
     * Approves a submission and applies its fields atomically
     *
     * @param int $submissionId
     * @return boolean
     */
    public function approve($submissionId)
    {
        $submission = $this->changeMapper->findByPk($submissionId);

        if (!$submission || $submission['status'] !== 'pending') {
            return false;
        }

        $payload = json_decode($submission['payload'], true);

        if (!is_array($payload)) {
            return false;
        }

        $memberId = (int) $submission['member_id'];
        $liveRow = $this->memberMapper->findByPk($memberId);

        if (!$liveRow) {
            return false;
        }

        try {
            $this->changeMapper->wrapTransaction(function () use ($memberId, $payload, $liveRow, $submissionId) {
                if (!empty($payload['base'])) {
                    $this->applyBaseFields($memberId, $this->unwrapPairs($payload['base']), $liveRow);
                }

                if (!empty($payload['translations'])) {
                    foreach ($payload['translations'] as $langId => $fields) {
                        foreach ($this->unwrapPairs($fields) as $field => $value) {
                            $this->memberMapper->updateTranslationFieldByLang($memberId, (int) $langId, $field, $value);
                        }
                    }
                }

                $this->changeMapper->markApproved($submissionId);
            });

            return true;

        } catch (Throwable $e) {
            error_log('Members: approval failed — ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Rejects a submission without applying any of its fields
     *
     * @param int $submissionId
     * @param string|null $reason
     * @return boolean
     */
    public function reject($submissionId, $reason = null)
    {
        $submission = $this->changeMapper->findByPk($submissionId);

        if (!$submission || $submission['status'] !== 'pending') {
            return false;
        }

        $payload = json_decode($submission['payload'], true);

        if (is_array($payload) && !empty($payload['base']['avatar'])) {
            $avatarPair = $payload['base']['avatar'];
            $stagedName = is_array($avatarPair) && array_key_exists('new', $avatarPair) ? $avatarPair['new'] : $avatarPair;

            if (!empty($stagedName)) {
                $this->discardStagedAvatar($stagedName);
            }
        }

        return $this->changeMapper->markRejected($submissionId, $reason);
    }

    /**
     * Fetches a paginated list of pending submissions
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchPending($pageNumber, $itemsPerPage)
    {
        return $this->changeMapper->fetchAllPending($pageNumber, $itemsPerPage);
    }

    /**
     * Fetches all pending submissions for a single member
     *
     * @param int $memberId
     * @return array
     */
    public function fetchPendingForMember($memberId)
    {
        return $this->changeMapper->fetchPendingByMember($memberId);
    }

    /**
     * Counts the number of pending submissions
     *
     * @return int
     */
    public function countPending()
    {
        return $this->changeMapper->countByStatus('pending');
    }

    /**
     * Fetches the review history for a member
     *
     * @param int $memberId
     * @param int $limit
     * @return array
     */
    public function fetchHistoryForMember($memberId, $limit = 50)
    {
        return $this->changeMapper->fetchHistoryByMember($memberId, $limit);
    }

    /**
     * Returns the paginator instance
     *
     * @return mixed
     */
    public function getPaginator()
    {
        return $this->changeMapper->getPaginator();
    }

    /**
     * Unwraps ['old' => ..., 'new' => ...] pairs into bare new values
     *
     * Supports both the current payload shape and the legacy shape
     * (each value is a bare new value).
     *
     * @param array $pairs
     * @return array
     */
    private function unwrapPairs(array $pairs)
    {
        $result = [];

        foreach ($pairs as $field => $pair) {
            $result[$field] = is_array($pair) && array_key_exists('new', $pair) ? $pair['new'] : $pair;
        }

        return $result;
    }

    /**
     * Applies base fields to the live member row
     *
     * Handles the avatar file move when the avatar field is included.
     * The final filename returned by the image manager is written back
     * into the "avatar" column so that the live member row points at
     * the file that actually exists on disk.
     *
     * @param int $memberId
     * @param array $fields
     * @param array $liveRow
     * @return void
     */
    private function applyBaseFields($memberId, array $fields, array $liveRow)
    {
        if (array_key_exists('avatar', $fields)) {
            $newAvatar = $fields['avatar'];
            $oldAvatar = isset($liveRow['avatar']) ? $liveRow['avatar'] : '';

            if ($newAvatar === '') {
                if (!empty($oldAvatar)) {
                    $this->avatarImageManager->delete($memberId, $oldAvatar);
                }
                $fields['avatar'] = '';
            } else {
                $promotedName = $this->promoteStagedAvatar($memberId, $newAvatar);

                if ($promotedName !== false) {
                    if (!empty($oldAvatar) && $oldAvatar !== $promotedName) {
                        $this->avatarImageManager->delete($memberId, $oldAvatar);
                    }
                    $fields['avatar'] = $promotedName;
                } else {
                    error_log(sprintf(
                        'Members: staged avatar %s missing for member %d; skipping',
                        $newAvatar,
                        $memberId
                    ));
                    unset($fields['avatar']);
                }
            }
        }

        if (!empty($fields)) {
            $this->memberMapper->updateProfile($memberId, $fields);
        }
    }

    /**
     * Moves an uploaded avatar file into the staging directory
     *
     * @param \Krystal\Http\FileTransfer\FileEntity $file
     * @return string|false Staged filename, or false on failure
     */
    private function stageAvatar(FileEntity $file)
    {
        $stagingDir = $this->getStagingDir();

        if (!is_dir($stagingDir)) {
            mkdir($stagingDir, 0755, true);
        }

        $name = $file->getUniqueName();

        if (!$file->move($stagingDir, $name)) {
            return false;
        }

        return $name;
    }

    /**
     * Promotes a staged avatar into the live avatars directory
     *
     * The image manager writes files under FileEntity::getUniqueName(),
     * which may differ from the staged filename. The staged file is
     * renamed to that final name before upload so that the filename
     * returned here matches what actually landed on disk. The caller
     * must persist this value into the member row.
     *
     * @param int $memberId
     * @param string $stagedName
     * @return string|false Final filename, or false on failure
     */
    private function promoteStagedAvatar($memberId, $stagedName)
    {
        $source = $this->getStagingDir() . $stagedName;

        if (!is_file($source)) {
            return false;
        }

        $entity = new FileEntity();
        $entity->setName($stagedName)
               ->setTmpName($source)
               ->setType(mime_content_type($source))
               ->setSize(filesize($source))
               ->setError(UPLOAD_ERR_OK);

        $finalName = $entity->getUniqueName();
        $promotedSource = $source;

        if ($finalName !== $stagedName) {
            $promotedSource = $this->getStagingDir() . $finalName;

            if (!@rename($source, $promotedSource)) {
                return false;
            }

            $entity->setTmpName($promotedSource);
        }

        $this->avatarImageManager->upload($memberId, $entity);
        @unlink($promotedSource);

        return $finalName;
    }

    /**
     * Deletes an orphaned staged avatar file
     *
     * @param string $stagedName
     * @return void
     */
    private function discardStagedAvatar($stagedName)
    {
        $path = $this->getStagingDir() . $stagedName;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Returns the absolute path to the avatar staging directory
     *
     * @return string
     */
    private function getStagingDir()
    {
        return $this->projectRoot . self::STAGING_DIR;
    }
}