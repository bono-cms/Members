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
 * Persists member ↔ tag associations.
 */
final class MemberTagMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_member_tags');
    }

    /**
     * {@inheritDoc}
     */
    protected function getPk()
    {
        return 'member_id';
    }

    /**
     * Returns [tag_id, ...] for a member.
     *
     * @param int $memberId
     * @return array
     */
    public function fetchTagIdsByMember($memberId)
    {
        $db = $this->db->select('tag_id')
                       ->from(self::getTableName())
                       ->whereEquals('member_id', (int) $memberId);

        return $db->queryAll('tag_id');
    }

    /**
     * Returns [member_id => [tag_id, ...], ...] for a batch of
     * members. Used to attach picked tags to a list of rows without
     * issuing one query per member.
     *
     * @param array $memberIds
     * @return array
     */
    public function fetchByMemberIds(array $memberIds)
    {
        $memberIds = array_values(array_filter(array_map('intval', $memberIds)));

        if (empty($memberIds)) {
            return [];
        }

        $db = $this->db->select(['member_id', 'tag_id'])
                       ->from(self::getTableName())
                       ->whereIn('member_id', $memberIds);

        $result = [];
        foreach ($db->queryAll() as $row) {
            $result[(int) $row['member_id']][] = (int) $row['tag_id'];
        }

        return $result;
    }

    /**
     * Returns member ids that carry any of the given tag ids.
     *
     * @param array $tagIds
     * @return array
     */
    public function fetchMemberIdsByTags(array $tagIds)
    {
        $tagIds = array_values(array_filter(array_map('intval', $tagIds)));

        if (empty($tagIds)) {
            return [];
        }

        $db = $this->db->select('member_id')
                       ->from(self::getTableName())
                       ->whereIn('tag_id', $tagIds);

        return $db->queryAll('member_id');
    }

    /**
     * Replaces a member's selections with the given set of tag ids.
     *
     * Runs inside a transaction because the caller may be writing
     * several groups at once.
     *
     * @param int $memberId
     * @param array $tagIds
     * @return boolean
     */
    public function syncMemberTags($memberId, array $tagIds)
    {
        $memberId = (int) $memberId;
        $tagIds = array_values(array_unique(array_map('intval', $tagIds)));

        return $this->wrapTransaction(function () use ($memberId, $tagIds) {
            $this->db->delete()
                     ->from(self::getTableName())
                     ->whereEquals('member_id', $memberId)
                     ->execute();

            if (empty($tagIds)) {
                return true;
            }

            $this->db->insertIntoJunction(
                          self::getTableName(),
                          ['member_id', 'tag_id'],
                          $memberId,
                          $tagIds
                      )
                     ->execute();

            return true;
        });
    }
}