<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Storage\MySQL;

use Cms\Storage\MySQL\AbstractMapper;

/**
 * Persists tag base rows and their translations.
 */
final class TagMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_tags');
    }

    /**
     * {@inheritDoc}
     */
    public static function getTranslationTable()
    {
        return TagTranslationMapper::getTableName();
    }

    /**
     * {@inheritDoc}
     */
    protected function getPk()
    {
        return 'id';
    }

    /**
     * Returns the base + translation columns to be selected
     *
     * @return array
     */
    private function getColumns()
    {
        return [
            self::column('id'),
            self::column('group_id'),
            self::column('code'),
            self::column('sort_order'),
            self::column('is_active'),
            self::column('created_at'),
            TagTranslationMapper::column('lang_id'),
            TagTranslationMapper::column('label'),
        ];
    }

    /**
     * Returns the active tags of a group, with the translation for
     * the active language joined in.
     *
     * @param int $groupId
     * @return array
     */
    public function fetchActiveByGroup($groupId)
    {
        $db = $this->db->select($this->getColumns())
                       ->from(self::getTableName())
                       ->innerJoin(self::getTranslationTable(), [
                           TagTranslationMapper::column('id') => self::getRawColumn('id'),
                           TagTranslationMapper::column('lang_id') => $this->getLangId(),
                       ])
                       ->whereEquals('group_id', (int) $groupId)
                       ->andWhereEquals('is_active', 1)
                       ->orderBy(['sort_order' => 'ASC', 'label' => 'ASC']);

        return $db->queryAll();
    }

    /**
     * Returns all tags of a group (admin listing), including
     * inactive ones, with the translation for the active language
     * joined in.
     *
     * @param int $groupId
     * @return array
     */
    public function fetchAllByGroupForLang($groupId)
    {
        $db = $this->db->select($this->getColumns())
                       ->from(self::getTableName())
                       ->innerJoin(self::getTranslationTable(), [
                           TagTranslationMapper::column('id') => self::getRawColumn('id'),
                           TagTranslationMapper::column('lang_id') => $this->getLangId(),
                       ])
                       ->whereEquals('group_id', (int) $groupId)
                       ->orderBy(['sort_order' => 'ASC', 'id' => 'ASC']);

        return $db->queryAll();
    }

    /**
     * Returns all tags of a group (base rows only), without
     * language filter.
     *
     * @param int $groupId
     * @return array
     */
    public function fetchAllByGroup($groupId)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('group_id', (int) $groupId)
                       ->orderBy(['sort_order' => 'ASC', 'id' => 'ASC']);

        return $db->queryAll();
    }

    /**
     * Returns a set of tags by their ids, with the translation for
     * the active language joined in.
     *
     * @param array $ids
     * @return array
     */
    public function fetchByIds(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if (empty($ids)) {
            return [];
        }

        $db = $this->db->select($this->getColumns())
                       ->from(self::getTableName())
                       ->innerJoin(self::getTranslationTable(), [
                           TagTranslationMapper::column('id') => self::getRawColumn('id'),
                           TagTranslationMapper::column('lang_id') => $this->getLangId(),
                       ])
                       ->whereIn(self::column('id'), $ids);

        return $db->queryAll();
    }

    /**
     * Returns the ids of the given tag ids that are active.
     *
     * Used by MemberTagService to sanitize a member's selection
     * without trusting the submitted ids.
     *
     * @param array $tagIds
     * @return array
     */
    public function fetchActiveIds(array $tagIds)
    {
        $tagIds = array_values(array_filter(array_map('intval', $tagIds)));

        if (empty($tagIds)) {
            return [];
        }

        $rows = $this->db->select('id')
                         ->from(self::getTableName())
                         ->whereIn('id', $tagIds)
                         ->andWhereEquals('is_active', 1)
                         ->queryAll();

        return array_map(function ($row) {
            return (int) $row['id'];
        }, $rows);
    }

    /**
     * Returns all translation rows for a tag, indexed by lang_id.
     *
     * @param int $id
     * @return array
     */
    public function fetchTranslations($id)
    {
        $db = $this->db->select('*')
                       ->from(self::getTranslationTable())
                       ->whereEquals(TagTranslationMapper::column('id'), (int) $id);

        $result = [];
        foreach ($db->queryAll() as $row) {
            $result[(int) $row['lang_id']] = $row;
        }

        return $result;
    }

    /**
     * Deletes a tag and all its translations (cascades to member_tags).
     *
     * @param int $id
     * @return boolean
     */
    public function deleteById($id)
    {
        return $this->deleteByPk($id);
    }
}