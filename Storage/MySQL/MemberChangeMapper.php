<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code.
 */

namespace Members\Storage\MySQL;

use Cms\Storage\MySQL\AbstractMapper;

final class MemberChangeMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_changes');
    }

    /**
     * {@inheritDoc}
     */
    protected function getPk()
    {
        return 'id';
    }

    /**
     * Inserts a new pending submission
     *
     * @param int $memberId
     * @param array $payload Structure: ['base' => [...], 'translations' => [langId => [...]]]
     * @return boolean
     */
    public function insertSubmission($memberId, array $payload)
    {
        $db = $this->db->insert(self::getTableName(), [
            'member_id' => (int) $memberId,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $db->execute();
    }

    /**
     * Marks a submission as approved
     *
     * @param int $id
     * @return boolean
     */
    public function markApproved($id)
    {
        $db = $this->db->update(self::getTableName(), [
            'status' => 'approved',
            'reviewed_at' => date('Y-m-d H:i:s')
        ])->whereEquals('id', (int) $id);

        return $db->execute();
    }

    /**
     * Marks a submission as rejected
     *
     * @param int $id
     * @param string|null $reason
     * @return boolean
     */
    public function markRejected($id, $reason = null)
    {
        $db = $this->db->update(self::getTableName(), [
            'status' => 'rejected',
            'reviewed_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason
        ])->whereEquals('id', (int) $id);

        return $db->execute();
    }

    /**
     * Returns a paginated list of pending submissions
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchAllPending($pageNumber, $itemsPerPage)
    {
        $db = $this->db->select([
                self::column('*'),
                MemberTranslationMapper::column('name') => 'member_name',
                MemberMapper::column('email') => 'member_email',
                MemberMapper::column('login') => 'member_login',
                MemberMapper::column('avatar') => 'member_avatar',
            ])
            ->from(self::getTableName())
            ->innerJoin(MemberMapper::getTableName(), [
                MemberMapper::column('id') => self::getRawColumn('member_id')
            ])
            ->innerJoin(MemberTranslationMapper::getTableName(), [
                MemberTranslationMapper::column('id')      => MemberMapper::getRawColumn('id'),
                MemberTranslationMapper::column('lang_id') => $this->getLangId(),
            ])
            ->whereEquals(self::column('status'), 'pending')
            ->orderBy(self::column('created_at'))
            ->desc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Returns all pending submissions for a member
     *
     * @param int $memberId
     * @return array
     */
    public function fetchPendingByMember($memberId)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('member_id', (int) $memberId)
                       ->andWhereEquals('status', 'pending')
                       ->orderBy('created_at')
                       ->desc();

        return $db->queryAll();
    }

    /**
     * Returns the review history for a member
     *
     * @param int $memberId
     * @param int $limit
     * @return array
     */
    public function fetchHistoryByMember($memberId, $limit = 50)
    {
        $db = $this->db->select('*')
                       ->from(self::getTableName())
                       ->whereEquals('member_id', (int) $memberId)
                       ->orderBy('created_at')
                       ->desc()
                       ->limit((int) $limit);

        return $db->queryAll();
    }

    /**
     * Counts submissions in a given status
     *
     * @param string $status
     * @return int
     */
    public function countByStatus($status)
    {
        $db = $this->db->select()
                       ->count('id', 'count')
                       ->from(self::getTableName())
                       ->whereEquals('status', $status);

        return (int) $db->query('count');
    }
}