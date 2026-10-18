<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Service;

use Members\Storage\MySQL\TagGroupMapper;
use Krystal\Stdlib\VirtualEntity;
use Krystal\Stdlib\ArrayUtils;

/**
 * Read/write layer for tag groups.
 */
final class TagGroupService
{
    /**
     * Tag group mapper instance
     *
     * @var \Members\Storage\MySQL\TagGroupMapper
     */
    private $mapper;

    /**
     * State initialization
     *
     * @param \Members\Storage\MySQL\TagGroupMapper $mapper
     * @return void
     */
    public function __construct(TagGroupMapper $mapper)
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
               ->setCode($row['code'])
               ->setSortOrder((int) $row['sort_order'])
               ->setCreatedAt($row['created_at'])
               ->setLangId((int) $row['lang_id'])
               ->setName($row['name'])
               ->setDescription($row['description']);

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
     * Returns the last inserted group id
     *
     * @return integer
     */
    public function getLastId()
    {
        return (int) $this->mapper->getLastId();
    }

    /**
     * Returns all groups in the active language
     *
     * @return array
     */
    public function fetchAll()
    {
        return $this->toEntities($this->mapper->fetchAll());
    }

    /**
     * Returns a [id => name] list, useful for select boxes
     *
     * @return array
     */
    public function fetchList()
    {
        return ArrayUtils::arrayList($this->fetchAll(), 'id', 'name');
    }

    /**
     * Returns a single group with its translations
     *
     * Shape: ['group' => entity, 'translations' => [langId => row]]
     *
     * @param int $id
     * @return array|boolean
     */
    public function fetchById($id)
    {
        $row = $this->mapper->fetchById($id);

        if ($row === false) {
            return false;
        }

        return [
            'group' => $this->toEntity($row),
            'translations' => $this->mapper->fetchTranslations($id),
        ];
    }

    /**
     * Persists a group and its translations
     *
     * @param array $base Base fields: ['id'?, 'code', 'sort_order']
     * @param array $translations [langId => ['name' => ..., 'description' => ...]]
     * @return boolean
     */
    public function save(array $base, array $translations)
    {
        $options = [
            'code' => $base['code'],
            'sort_order' => (int) $base['sort_order'],
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
                'name' => $fields['name'],
                'description' => isset($fields['description']) ? $fields['description'] : null,
            ];
        }

        return $this->mapper->saveEntity($options, $prepared);
    }

    /**
     * Deletes a group by its id
     *
     * Cascades to translations and tags.
     *
     * @param int $id
     * @return boolean
     */
    public function deleteById($id)
    {
        return $this->mapper->deleteById((int) $id);
    }
}