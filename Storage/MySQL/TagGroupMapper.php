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
 * Persists tag group base rows and their translations.
 */
final class TagGroupMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_tag_groups');
    }

    /**
     * {@inheritDoc}
     */
    public static function getTranslationTable()
    {
        return TagGroupTranslationMapper::getTableName();
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
            self::column('code'),
            self::column('sort_order'),
            self::column('created_at'),
            TagGroupTranslationMapper::column('lang_id'),
            TagGroupTranslationMapper::column('name'),
            TagGroupTranslationMapper::column('description'),
        ];
    }

    /**
     * Returns one row per group, with the translation for the
     * active language joined in.
     *
     * @return array
     */
    public function fetchAll()
    {
        $db = $this->db->select($this->getColumns())
                       ->from(self::getTableName())
                       ->innerJoin(self::getTranslationTable(), [
                           TagGroupTranslationMapper::column('id') => self::getRawColumn('id'),
                           TagGroupTranslationMapper::column('lang_id') => $this->getLangId(),
                       ])
                       ->orderBy(['sort_order' => 'ASC', 'name' => 'ASC']);

        return $db->queryAll();
    }

    /**
     * Returns a single group with the translation for the active
     * language joined in.
     *
     * @param int $id
     * @return array|boolean
     */
    public function fetchById($id)
    {
        $db = $this->db->select($this->getColumns())
                       ->from(self::getTableName())
                       ->innerJoin(self::getTranslationTable(), [
                           TagGroupTranslationMapper::column('id') => self::getRawColumn('id'),
                           TagGroupTranslationMapper::column('lang_id') => $this->getLangId(),
                       ])
                       ->whereEquals(self::column('id'), (int) $id);

        return $db->query();
    }

    /**
     * Returns all translation rows for a group, indexed by lang_id.
     *
     * @param int $id
     * @return array
     */
    public function fetchTranslations($id)
    {
        $db = $this->db->select('*')
                       ->from(self::getTranslationTable())
                       ->whereEquals(TagGroupTranslationMapper::column('id'), (int) $id);

        $result = [];
        foreach ($db->queryAll() as $row) {
            $result[(int) $row['lang_id']] = $row;
        }

        return $result;
    }

    /**
     * Deletes a group and all its translations (cascades to tags).
     *
     * @param int $id
     * @return boolean
     */
    public function deleteById($id)
    {
        return $this->deleteByPk($id);
    }
}