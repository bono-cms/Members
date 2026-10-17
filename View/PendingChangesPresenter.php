<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\View;

use Members\Service\ChangesDiff;
use Members\Service\ProfileChangeService;

/**
 * Shapes raw submission rows into plain arrays for the view
 *
 * The presenter is payload-driven: the submission row carries both
 * the old and new values, so the same presenter works for pending
 * submissions and for the full review history. It produces no HTML
 * and performs no translation or URL building.
 */
final class PendingChangesPresenter
{
    /**
     * Field label resolver
     *
     * Receives a raw field name and returns a display label
     *
     * @var callable
     */
    private $labelResolver;

    /**
     * Absolute path to the avatar staging directory
     *
     * Used only to verify that the staged file exists before exposing
     * a preview URL to the reviewer.
     *
     * @var string
     */
    private $stagingDir;

    /**
     * State initialization
     *
     * @param callable $labelResolver Receives a field name, returns a label
     * @param string $stagingDir Absolute path to the avatar staging directory
     * @return void
     */
    public function __construct(callable $labelResolver, $stagingDir)
    {
        $this->labelResolver = $labelResolver;
        $this->stagingDir = rtrim($stagingDir, '/') . '/';
    }

    /**
     * Shapes a collection of raw submissions into view arrays
     *
     * @param array $submissions Raw rows from ProfileChangeService
     * @param array $languages Language entities
     * @return array<int, array>
     */
    public function present(array $submissions, array $languages)
    {
        $rows = [];

        foreach ($submissions as $submission) {
            $rows[] = $this->presentOne($submission, $languages);
        }

        return $rows;
    }

    /**
     * Shapes a single raw submission into a view array
     *
     * @param array $submission
     * @param array $languages
     * @return array
     */
    private function presentOne(array $submission, array $languages)
    {
        $payload = json_decode(isset($submission['payload']) ? $submission['payload'] : '', true);
        $hasPayload = is_array($payload);

        $row = [
            'id' => (int) $submission['id'],
            'memberId' => (int) $submission['member_id'],
            'memberName' => $this->memberName($submission),
            'memberEmail' => isset($submission['member_email']) ? (string) $submission['member_email'] : '',
            'createdAt' => isset($submission['created_at']) ? $submission['created_at'] : '',
            'reviewedAt' => isset($submission['reviewed_at']) ? $submission['reviewed_at'] : null,
            'status' => isset($submission['status']) ? $submission['status'] : 'pending',
            'rejectionReason' => isset($submission['rejection_reason']) ? $submission['rejection_reason'] : null,
            'hasPayload' => $hasPayload,
            'hasBaseChanges' => false,
            'hasTranslationChanges' => false,
            'baseChanges' => [],
            'translationChanges' => []
        ];

        if (!$hasPayload) {
            return $row;
        }

        $diff = new ChangesDiff($payload);

        $row['hasBaseChanges'] = $diff->hasBaseChanges();
        $row['hasTranslationChanges'] = $diff->hasTranslationChanges();

        foreach ($diff->baseChanges() as $change) {
            $row['baseChanges'][] = $this->shapeChange($change);
        }

        foreach ($diff->translationChanges() as $langId => $changes) {
            $shaped = [];

            foreach ($changes as $change) {
                $shaped[] = $this->shapeChange($change);
            }

            $row['translationChanges'][] = [
                'language' => $this->languageName($languages, $langId),
                'changes' => $shaped
            ];
        }

        return $row;
    }

    /**
     * Adds the resolved label to a change descriptor
     *
     * @param array $change
     * @return array
     */
    private function shapeChange(array $change)
    {
        $shaped = [
            'label' => call_user_func($this->labelResolver, $change['field']),
            'old' => $change['old'],
            'new' => $change['new'],
            'oldEmpty' => $change['oldEmpty'],
            'newEmpty' => $change['newEmpty'],
            'unchanged' => $change['unchanged'],
            'isImage' => false,
            'previewUrl' => null
        ];

        if ($change['field'] === 'avatar' && !$change['newEmpty']) {
            if (is_file($this->stagingDir . $change['new'])) {
                $shaped['isImage'] = true;
                $shaped['previewUrl'] = ProfileChangeService::STAGING_DIR . rawurlencode($change['new']);
            }
        }

        return $shaped;
    }

    /**
     * Returns the human-readable language name for the given language id
     *
     * @param array $languages Collection of language entities
     * @param int $langId
     * @return string
     */
    private function languageName(array $languages, $langId)
    {
        foreach ($languages as $language) {
            if ((int) $language->getId() === (int) $langId) {
                return $language->getName();
            }
        }

        return '#' . (int) $langId;
    }

    /**
     * Resolves the display name for a member row
     *
     * @param array $submission Submission row with optional member_* keys
     * @return string
     */
    private function memberName(array $submission)
    {
        if (!empty($submission['member_name'])) {
            return $submission['member_name'];
        }

        if (!empty($submission['member_login'])) {
            return $submission['member_login'];
        }

        if (isset($submission['member_id'])) {
            return '#' . (int) $submission['member_id'];
        }

        return '#?';
    }
}