<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Members\Storage\MySQL\TagMapper;
use Krystal\Stdlib\VirtualEntity;
use Krystal\Stdlib\ArrayUtils;

/**
 * Read/write layer for tags.
 */
final class TagService
{
    /**
     * Tag mapper instance
     *
     * @var \Members\Storage\MySQL\TagMapper
     */
    private $mapper;

    /**
     * State initialization
     *
     * @param \Members\Storage\MySQL\TagMapper $mapper
     * @return void
     */
    public function __construct(TagMapper $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Converts a database row into a VirtualEntity
     *
     * @param array $row
     * @return \Krystal\Stdlib\VirtualEntity
     */
    private function toEntity(array $row)
    {
        $entity = new VirtualEntity();

        $entity->setId((int) $row['id'])
               ->setGroupId((int) $row['group_id'])
               ->setCode($row['code'])
               ->setSortOrder((int) $row['sort_order'])
               ->setActive((bool) $row['is_active'])
               ->setCreatedAt($row['created_at'])
               ->setLangId((int) $row['lang_id'])
               ->setLabel($row['label']);

        return $entity;
    }

    /**
     * Converts a collection of rows into entities
     *
     * @param array $rows
     * @return array
     */
    private function toEntities(array $rows)
    {
        $result = [];

        foreach ($rows as $row) {
            $result[] = $this->toEntity($row);
        }

        return $result;
    }

    /**
     * Returns the last inserted tag id
     *
     * @return integer
     */
    public function getLastId()
    {
        return (int) $this->mapper->getLastId();
    }

    /**
     * Returns the active tags of a group (member-facing picker)
     *
     * @param int $groupId
     * @return array
     */
    public function fetchActiveByGroup($groupId)
    {
        return $this->toEntities($this->mapper->fetchActiveByGroup($groupId));
    }

    /**
     * Returns all tags of a group, including inactive (admin listing)
     *
     * @param int $groupId
     * @return array
     */
    public function fetchAllByGroup($groupId)
    {
        return $this->toEntities($this->mapper->fetchAllByGroupForLang($groupId));
    }

    /**
     * Returns tags by their ids
     *
     * @param array $ids
     * @return array
     */
    public function fetchByIds(array $ids)
    {
        return $this->toEntities($this->mapper->fetchByIds($ids));
    }

    /**
     * Returns a [id => label] list, useful for select boxes
     *
     * @param int $groupId
     * @return array
     */
    public function fetchList($groupId)
    {
        return ArrayUtils::arrayList($this->fetchAllByGroup($groupId), 'id', 'label');
    }

    /**
     * Returns a single tag with its translations
     *
     * Shape: ['tag' => entity, 'translations' => [langId => row]]
     *
     * @param int $id
     * @return array|boolean
     */
    public function fetchById($id)
    {
        $rows = $this->mapper->fetchByIds([(int) $id]);

        if (empty($rows)) {
            return false;
        }

        return [
            'tag' => $this->toEntity($rows[0]),
            'translations' => $this->mapper->fetchTranslations($id),
        ];
    }

    /**
     * Narrows a list of tag ids to those that are active
     *
     * Delegates to the mapper so no caller can skip the check.
     *
     * @param array $tagIds
     * @return array
     */
    public function filterActiveIds(array $tagIds)
    {
        return $this->mapper->fetchActiveIds($tagIds);
    }

    /**
     * Persists a tag and its translations
     *
     * @param array $base Base fields: ['id'?, 'group_id', 'code', 'sort_order', 'is_active']
     * @param array $translations [langId => ['label' => ...]]
     * @return boolean
     */
    public function save(array $base, array $translations)
    {
        $options = [
            'group_id' => (int) $base['group_id'],
            'code' => isset($base['code']) && $base['code'] !== '' ? $base['code'] : null,
            'sort_order' => (int) $base['sort_order'],
            'is_active' => !empty($base['is_active']) ? 1 : 0,
        ];

        if (!empty($base['id'])) {
            $options['id'] = (int) $base['id'];
        } else {
            $options['created_at'] = date('Y-m-d H:i:s');
        }

        $prepared = [];

        foreach ($translations as $langId => $fields) {
            $prepared[(int) $langId] = [
                'lang_id' => (int) $langId,
                'label' => $fields['label'],
            ];
        }

        return $this->mapper->saveEntity($options, $prepared);
    }

    /**
     * Deletes a tag by its id
     *
     * Cascades to translations and member_tags.
     *
     * @param int $id
     * @return boolean
     */
    public function deleteById($id)
    {
        return $this->mapper->deleteById((int) $id);
    }
}