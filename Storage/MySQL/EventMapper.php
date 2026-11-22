<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */
namespace Members\Storage\MySQL;

use Cms\Storage\MySQL\AbstractMapper;

/**
 * Persists event rows and provides the read-side queries used by
 * the event service, the admin grid and the public catalog.
 *
 * The mapper never applies business rules (status transitions,
 * ownership checks, moderation) — those live in EventService.
 * The mapper only knows how to read and write rows.
 */
final class EventMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_events');
    }

    /**
     * {@inheritDoc}
     */
    protected function getPk()
    {
        return 'id';
    }

    /**
     * Returns the columns selected for every "rich" event query.
     *
     * Joins the member translation table so callers get the
     * author's display name in the active language without
     * issuing a second query. When creator_id is NULL (official
     * events), the joined columns come back as NULL — callers
     * should treat that as "official / association event".
     *
     * @return array
     */
    private function getRichColumns()
    {
        return [
            self::column('*'),
            MemberTranslationMapper::column('name') => 'creator_name',
            MemberMapper::column('avatar')           => 'creator_avatar',
        ];
    }

    /**
     * Attaches the author join to a query builder.
     *
     * A LEFT JOIN is used deliberately: official events have
     * creator_id = NULL, and we still want the event row to be
     * returned with NULL author fields rather than dropped.
     *
     * @param \Krystal\Db\Sql\Db $db
     * @return \Krystal\Db\Sql\Db
     */
    private function joinAuthor($db)
    {
        $db->leftJoin(MemberMapper::getTableName(), [
                MemberMapper::column('id') => self::getRawColumn('creator_id'),
            ])
            ->leftJoin(MemberTranslationMapper::getTableName(), [
                MemberTranslationMapper::column('id')      => MemberMapper::getRawColumn('id'),
                MemberTranslationMapper::column('lang_id') => $this->getLangId(),
            ]);

        return $db;
    }

    /**
     * Inserts a new event row.
     *
     * The caller is responsible for setting `created_at` and
     * `status` before calling this method. Returns the boolean
     * result of execute(); the new id is available via getLastId().
     *
     * @param  array $data
     * @return bool
     */
    public function insertEvent(array $data)
    {
        $db = $this->db->insert(self::getTableName(), $data);
        return $db->execute();
    }

    /**
     * Updates an existing event row.
     *
     * Only the columns present in $data are written. Callers
     * typically pass the full form payload after validation.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function updateEvent($id, array $data)
    {
        $db = $this->db->update(self::getTableName(), $data)
                       ->whereEquals('id', (int) $id);

        return $db->execute();
    }

    /**
     * Deletes an event by id.
     *
     * The analytics rows are removed by the ON DELETE CASCADE
     * foreign key, so the caller does not need to clean them up.
     *
     * @param  int $id
     * @return bool
     */
    public function deleteEvent($id)
    {
        return $this->deleteByPk((int) $id);
    }

    /**
     * Atomically increments one of the denormalized counters.
     *
     * Uses the shared shortcut so MySQL computes "column + 1" in
     * place instead of writing a stale value read into PHP.
     *
     * @param  int    $id
     * @param  string $type "views" or "clicks"
     * @return bool
     */
    public function incrementCounter($id, $type)
    {
        $column = $type === 'clicks' ? 'clicks_count' : 'views_count';

        return $this->incrementColumnByPk((int) $id, $column);
    }

    /**
     * Fetches a paginated list of all events for the admin grid.
     *
     * Rows are ordered by created_at DESC (newest first) and
     * carry the author's display name in the active language.
     * Official events (creator_id = NULL) are included with
     * NULL author fields.
     *
     * Supported filters (all optional, AND-combined):
     *   - status  string  "pending" | "approved" | "rejected"
     *
     * @param  int   $pageNumber
     * @param  int   $itemsPerPage
     * @param  array $filters
     * @return array
     */
    public function fetchAllForAdmin($pageNumber, $itemsPerPage, array $filters = [])
    {
        $status = isset($filters['status']) && $filters['status'] !== ''
            ? $filters['status']
            : null;

        $db = $this->db->select($this->getRichColumns())
            ->from(self::getTableName());

        $this->joinAuthor($db);

        if ($status !== null) {
            $db->whereEquals(self::column('status'), $status);
        }

        $db->orderBy(self::column('created_at'))
            ->desc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Fetches a paginated list of events pending moderation.
     *
     * Same shape as fetchAllForAdmin() but pre-filtered to
     * status = 'pending'. Used by the admin's "pending events"
     * shortcut button.
     *
     * @param  int $pageNumber
     * @param  int $itemsPerPage
     * @return array
     */
    public function fetchPendingForAdmin($pageNumber, $itemsPerPage)
    {
        return $this->fetchAllForAdmin($pageNumber, $itemsPerPage, [
            'status' => 'pending',
        ]);
    }

    /**
     * Fetches a paginated list of events created by a member.
     *
     * Used to render "My events" in the member's dashboard.
     * Official events (creator_id = NULL) are never returned
     * here — they have no owner to show them to.
     *
     * @param  int $creatorId
     * @param  int $pageNumber
     * @param  int $itemsPerPage
     * @return array
     */
    public function fetchByCreatorId($creatorId, $pageNumber, $itemsPerPage)
    {
        $db = $this->db->select($this->getRichColumns())
            ->from(self::getTableName());

        $this->joinAuthor($db);

        $db->whereEquals(self::column('creator_id'), (int) $creatorId)
            ->orderBy(self::column('starts_at'))
            ->desc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Fetches a paginated list of approved events for the public
     * catalog.
     *
     * Only approved events are returned. Ordering is by starts_at
     * ASC so upcoming events appear first.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchApproved($pageNumber, $itemsPerPage)
    {
        $db = $this->db->select($this->getRichColumns())
                       ->from(self::getTableName());

        $this->joinAuthor($db);

        $db->whereEquals(self::column('status'), 'approved')
            ->orderBy(self::column('starts_at'))
            ->asc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Counts events in a given status.
     *
     * @param  string $status
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

    /**
     * Returns the total number of events (any status).
     *
     * @return int
     */
    public function countAll()
    {
        $db = $this->db->select()
            ->count('id', 'count')
            ->from(self::getTableName());

        return (int) $db->query('count');
    }

    /**
     * Returns aggregate counters grouped by status.
     *
     * Shape: ['pending' => int, 'approved' => int, 'rejected' => int]
     * Statuses with zero events are still present in the result,
     * so callers can render a complete status breakdown without
     * having to fill in missing keys.
     *
     * @return array
     */
    public function fetchStatusBreakdown()
    {
        $db = $this->db->select(['status'])
                       ->count('id', 'count')
                       ->from(self::getTableName())
                       ->groupBy('status');

        $rows = $db->queryAll();

        $result = ['pending' => 0, 'approved' => 0, 'rejected' => 0];

        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['count'];
        }

        return $result;
    }
}