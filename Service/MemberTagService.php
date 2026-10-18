<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Members\Storage\MySQL\MemberTagMapper;
use Members\Storage\MySQL\TagGroupMapper;
use Members\Storage\MySQL\TagMapper;

/**
 * Owns the "member picks tags" flow and the payload that feeds the
 * member-facing checkbox form.
 *
 * Reads the picker payload (groups + active tags) and persists the
 * member's selection, always sanitizing submitted ids against the
 * active-tag set so a member cannot attach an inactive or foreign
 * tag.
 */
final class MemberTagService
{
    /**
     * Member-to-tag association mapper
     *
     * @var \Members\Storage\MySQL\MemberTagMapper
     */
    private $memberTagMapper;

    /**
     * Tag group mapper
     *
     * @var \Members\Storage\MySQL\TagGroupMapper
     */
    private $groupMapper;

    /**
     * Tag mapper
     *
     * @var \Members\Storage\MySQL\TagMapper
     */
    private $tagMapper;

    /**
     * State initialization
     *
     * @param \Members\Storage\MySQL\MemberTagMapper $memberTagMapper
     * @param \Members\Storage\MySQL\TagGroupMapper $groupMapper
     * @param \Members\Storage\MySQL\TagMapper $tagMapper
     * @return void
     */
    public function __construct(
        MemberTagMapper $memberTagMapper,
        TagGroupMapper $groupMapper,
        TagMapper $tagMapper
    ) {
        $this->memberTagMapper = $memberTagMapper;
        $this->groupMapper = $groupMapper;
        $this->tagMapper = $tagMapper;
    }

    /**
     * Returns the tags a member has selected, grouped by tag group
     *
     * Only active tags are included: if an admin deactivates a tag,
     * it stops showing on the member's profile. Groups and tags are
     * returned in the active language (the mappers apply getLangId()
     * internally). Groups with no selected active tags are omitted.
     *
     * Shape: [
     *   [
     *     'group' => ['id' => ..., 'name' => ..., 'description' => ...],
     *     'tags' => [
     *       ['id' => ..., 'label' => ...],
     *       ...
     *     ]
     *   ],
     *   ...
     * ]
     *
     * @param int $memberId
     * @return array
     */
    public function fetchGroupedByMember($memberId)
    {
        $selected = array_flip($this->fetchSelectedIds((int) $memberId));

        if (empty($selected)) {
            return [];
        }

        $result = [];

        foreach ($this->fetchPickerPayload() as $block) {
            $matched = [];

            foreach ($block['tags'] as $tag) {
                if (isset($selected[(int) $tag['id']])) {
                    $matched[] = $tag;
                }
            }

            if (!empty($matched)) {
                $result[] = [
                    'group' => $block['group'],
                    'tags' => $matched
                ];
            }
        }

        return $result;
    }

    /**
     * Returns the picker payload for the member-facing form
     *
     * Shape: [ ['group' => row, 'tags' => [row, ...]], ... ]
     * Only active tags are included; empty groups are skipped.
     *
     * @return array
     */
    public function fetchPickerPayload()
    {
        $groups = $this->groupMapper->fetchAll();
        $result = [];

        foreach ($groups as $group) {
            $tags = $this->tagMapper->fetchActiveByGroup((int) $group['id']);

            if (!empty($tags)) {
                $result[] = [
                    'group' => $group,
                    'tags' => $tags,
                ];
            }
        }

        return $result;
    }

    /**
     * Returns the ids of the tags a member has selected
     *
     * @param int $memberId
     * @return array
     */
    public function fetchSelectedIds($memberId)
    {
        $rows = $this->memberTagMapper->fetchTagIdsByMember((int) $memberId);
        $ids = [];

        foreach ($rows as $row) {
            $ids[] = (int) $row;
        }

        return $ids;
    }

    /**
     * Sanitizes a submitted set of tag ids
     *
     * Drops anything that is not an active tag, so a member cannot
     * attach an inactive or foreign tag by tampering with the form.
     *
     * @param array $tagIds Submitted tag ids
     * @return array Safe tag ids
     */
    public function sanitizeSelection(array $tagIds)
    {
        return $this->tagMapper->fetchActiveIds($tagIds);
    }

    /**
     * Persists the member's tag selection
     *
     * Replaces the previous selection with the given set.
     *
     * @param int $memberId
     * @param array $tagIds Safe tag ids (already sanitized)
     * @return boolean
     */
    public function saveSelection($memberId, array $tagIds)
    {
        return $this->memberTagMapper->syncMemberTags((int) $memberId, $tagIds);
    }
}