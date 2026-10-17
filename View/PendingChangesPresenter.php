<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\View;

use Members\Service\ChangesDiff;
use Members\Service\MemberEntity;

/**
 * Shapes raw pending submissions into plain arrays for the view
 *
 * This class owns the data decisions for the pending changes grid:
 * which fields exist, what their raw values are, and which language
 * each translation group belongs to. It produces no HTML and
 * performs no translation or URL building — both are the template's
 * job.
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
     * State initialization
     *
     * @param callable $labelResolver Receives a field name, returns a label
     * @return void
     */
    public function __construct(callable $labelResolver)
    {
        $this->labelResolver = $labelResolver;
    }

    /**
     * Shapes a collection of raw submissions into view arrays
     *
     * @param array $submissions Raw rows from ProfileChangeService::fetchPending()
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
            'hasPayload' => $hasPayload,
            'hasBaseChanges' => false,
            'hasTranslationChanges' => false,
            'baseChanges' => [],
            'translationChanges' => []
        ];

        if (!$hasPayload) {
            return $row;
        }

        $live = isset($submission['live']) && $submission['live'] instanceof MemberEntity
            ? $submission['live']
            : null;

        $diff = new ChangesDiff($live, $payload);

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
     * The output keeps the raw values so that the template can escape
     * them at the point of output.
     *
     * @param array $change
     * @return array
     */
    private function shapeChange(array $change)
    {
        return [
            'label' => call_user_func($this->labelResolver, $change['field']),
            'old' => $change['old'],
            'new' => $change['new'],
            'oldEmpty' => $change['oldEmpty'],
            'newEmpty' => $change['newEmpty'],
            'unchanged' => $change['unchanged']
        ];
    }

    /**
     * Returns the human-readable language name for the given language id
     *
     * Falls back to a numeric placeholder when the id is unknown.
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
     * Falls back to login when name is empty, then to a numeric id,
     * then to a placeholder when the submission row is malformed.
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