<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */
namespace Members\Storage\MySQL;

use Cms\Storage\MySQL\AbstractMapper;
use Krystal\Db\Sql\RawSqlFragment;
use Members\Service\EventEntity;

/**
 * Persists event rows and their translations.
 *
 * Text content (title, description, organizer) lives in the
 * translation table; every read joins it for the active language.
 * An event with no translation for that language is not returned,
 * exactly as for members and tags.
 *
 * The mapper never applies business rules (status transitions,
 * ownership checks, moderation) — those live in EventService.
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
    public static function getTranslationTable()
    {
        return EventTranslationMapper::getTableName();
    }

    /**
     * Returns shared columns to be selected
     *
     * @return array
     */
    private function getColumns()
    {
        return [
            self::column('id'),
            self::column('creator_id'),
            self::column('cover'),
            self::column('link'),
            self::column('location_name'),
            self::column('latitude'),
            self::column('longitude'),
            self::column('location_source'),
            self::column('starts_at'),
            self::column('ends_at'),
            self::column('status'),
            self::column('rejection_reason'),
            self::column('views_count'),
            self::column('clicks_count'),
            self::column('created_at'),
            EventTranslationMapper::column('lang_id'),
            EventTranslationMapper::column('title'),
            EventTranslationMapper::column('description'),
            EventTranslationMapper::column('organizer'),
        ];
    }

    /**
     * Returns the shared columns plus the author's display name
     *
     * @return array
     */
    private function getRichColumns()
    {
        return array_merge($this->getColumns(), [
            MemberTranslationMapper::column('name') => 'creator_name',
            MemberMapper::column('avatar') => 'creator_avatar',
        ]);
    }

    /**
     * Attaches the translation join for the active language
     *
     * @param \Krystal\Db\Sql\Db $db
     * @return \Krystal\Db\Sql\Db
     */
    private function joinTranslation($db)
    {
        $db->innerJoin(EventTranslationMapper::getTableName(), [
            EventTranslationMapper::column('id') => self::getRawColumn('id'),
            EventTranslationMapper::column('lang_id') => $this->getLangId(),
        ]);

        return $db;
    }

    /**
     * Attaches the author join to a query builder.
     *
     * A LEFT JOIN is used deliberately: association events have
     * creator_id = NULL, and we still want the event row returned
     * with NULL author fields rather than dropped.
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
                MemberTranslationMapper::column('id') => MemberMapper::getRawColumn('id'),
                MemberTranslationMapper::column('lang_id') => $this->getLangId(),
            ]);

        return $db;
    }

    /**
     * SQL expression for the moment an event stops being actual.
     *
     * Mirrors EventEntity::isPast(): ends_at when present, else starts_at.
     *
     * @return string
     */
    private function getExpiryExpression()
    {
        return sprintf('COALESCE(%s, %s)', self::column('ends_at'), self::column('starts_at'));
    }

    /**
     * Current time in the application timezone.
     *
     * date() is used instead of NOW() so the cut-off matches the
     * timestamps written by EventService and the analytics mapper.
     *
     * @return string
     */
    private function getNow()
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Base query for the public catalog: approved + content + author
     *
     * @return \Krystal\Db\Sql\Db
     */
    private function createApprovedQuery()
    {
        $db = $this->db->select($this->getRichColumns())
            ->from(self::getTableName());

        $this->joinTranslation($db);
        $this->joinAuthor($db);

        $db->whereEquals(self::column('status'), EventEntity::STATUS_APPROVED);

        return $db;
    }

    /**
     * Fetches event data by its associated id
     *
     * With $withTranslations = true, returns one row per language
     * (feeds the multilingual edit form). Otherwise returns the row
     * for the active language only.
     *
     * @param int $id Event id
     * @param bool $withTranslations
     * @return array|bool
     */
    public function fetchById($id, $withTranslations)
    {
        return $this->findEntity($this->getColumns(), $id, $withTranslations);
    }

    /**
     * Returns one event with its active-language content and author
     *
     * Used by the detail page and the analytics payloads, which need
     * the author's display name alongside the content.
     *
     * @param int $id
     * @return array|bool
     */
    public function fetchRichById($id)
    {
        $db = $this->db->select($this->getRichColumns())
            ->from(self::getTableName());

        $this->joinTranslation($db);
        $this->joinAuthor($db);

        $db->whereEquals(self::column('id'), (int) $id);

        return $db->query();
    }

    /**
     * Updates base columns of an existing event.
     *
     * Never touches translations — used by the moderation path,
     * which only writes status and rejection_reason.
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
     * Translations and analytics rows are removed by their
     * ON DELETE CASCADE foreign keys.
     *
     * @param int $id
     * @return bool
     */
    public function deleteEvent($id)
    {
        return $this->deleteByPk((int) $id);
    }

    /**
     * Atomically increments one of the denormalized counters.
     *
     * @param int $id
     * @param string $type "views" or "clicks"
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
     * Sorted by event date, newest first.
     *
     * Supported filters: status.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @param array $filters
     * @return array
     */
    public function fetchAllForAdmin($pageNumber, $itemsPerPage, array $filters = [])
    {
        $status = isset($filters['status']) && $filters['status'] !== ''
            ? $filters['status']
            : null;

        $db = $this->db->select($this->getRichColumns())
            ->from(self::getTableName());

        $this->joinTranslation($db);
        $this->joinAuthor($db);

        if ($status !== null) {
            $db->whereEquals(self::column('status'), $status);
        }

        $db->orderBy(self::column('starts_at'))
            ->desc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Fetches a paginated list of events pending moderation.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
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
     * Association events (creator_id = NULL) are never returned here.
     *
     * @param int $creatorId
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchByCreatorId($creatorId, $pageNumber, $itemsPerPage)
    {
        $db = $this->db->select($this->getRichColumns())
            ->from(self::getTableName());

        $this->joinTranslation($db);
        $this->joinAuthor($db);

        $db->whereEquals(self::column('creator_id'), (int) $creatorId)
            ->orderBy(self::column('starts_at'))
            ->desc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Fetches a paginated list of actual (not yet expired) events,
     * nearest first.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchUpcoming($pageNumber, $itemsPerPage)
    {
        $db = $this->createApprovedQuery();

        $db->andWhere($this->getExpiryExpression(), '>=', $this->getNow())
            ->orderBy(self::column('starts_at'))
            ->asc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Fetches a paginated list of expired events, most recently ended first.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchPast($pageNumber, $itemsPerPage)
    {
        $db = $this->createApprovedQuery();

        $db->andWhere($this->getExpiryExpression(), '<', $this->getNow())
            ->orderBy(self::column('starts_at'))
            ->desc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Fetches a paginated list of approved events for the public catalog.
     *
     * @param int $pageNumber
     * @param int $itemsPerPage
     * @return array
     */
    public function fetchApproved($pageNumber, $itemsPerPage)
    {
        $db = $this->createApprovedQuery();

        $db->orderBy(self::column('starts_at'))
            ->asc()
            ->paginate($pageNumber, $itemsPerPage);

        return $db->queryAll();
    }

    /**
     * Fetches approved events with valid coordinates for map rendering.
     *
     * @param array $filters Optional filters: from, to, bbox, period
     * @param int $limit Maximum number of points (max 1000)
     * @return array
     */
    public function fetchMapPoints(array $filters = [], $limit = 500)
    {
        $limit = max(1, min((int) $limit, 1000));

        $columns = [
            self::column('id'),
            EventTranslationMapper::column('title'),
            self::column('latitude'),
            self::column('longitude'),
            self::column('location_name'),
            self::column('starts_at'),
            self::column('ends_at'),
        ];

        $db = $this->db->select($columns)
                       ->from(self::getTableName());

        $this->joinTranslation($db);

        $db->whereEquals(self::column('status'), EventEntity::STATUS_APPROVED)
           ->andWhere(self::column('latitude'), 'IS NOT', new RawSqlFragment('NULL'))
           ->andWhere(self::column('longitude'), 'IS NOT', new RawSqlFragment('NULL'))
           ->andWhere(self::column('latitude'), '>=', -90)
           ->andWhere(self::column('latitude'), '<=', 90)
           ->andWhere(self::column('longitude'), '>=', -180)
           ->andWhere(self::column('longitude'), '<=', 180);

        // Period filter: upcoming (default) | past | all
        $period = isset($filters['period']) ? (string) $filters['period'] : 'upcoming';
        $expiryExpression = sprintf('COALESCE(%s, %s)', self::column('ends_at'), self::column('starts_at'));
        $now = date('Y-m-d H:i:s');

        if ($period === 'upcoming') {
            $db->append(sprintf(" AND %s >= '%s'", $expiryExpression, $now));
        } elseif ($period === 'past') {
            $db->append(sprintf(" AND %s < '%s'", $expiryExpression, $now));
        }

        // Date filters
        if (!empty($filters['from'])) {
            $db->andWhere(self::column('starts_at'), '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $db->andWhere(self::column('starts_at'), '<=', $filters['to']);
        }

        // Bounding box filters
        if (!empty($filters['bbox']) && count($filters['bbox']) === 4) {
            $lat1 = (float) $filters['bbox'][0];
            $lng1 = (float) $filters['bbox'][1];
            $lat2 = (float) $filters['bbox'][2];
            $lng2 = (float) $filters['bbox'][3];
            $minLat = min($lat1, $lat2);
            $maxLat = max($lat1, $lat2);
            $minLng = min($lng1, $lng2);
            $maxLng = max($lng1, $lng2);

            if ($minLat >= -90 && $maxLat <= 90 && $minLng >= -180 && $maxLng <= 180) {
                $db->andWhere(self::column('latitude'), '>=', $minLat)
                   ->andWhere(self::column('latitude'), '<=', $maxLat)
                   ->andWhere(self::column('longitude'), '>=', $minLng)
                   ->andWhere(self::column('longitude'), '<=', $maxLng);
            }
        }

        $db->orderBy(self::column('starts_at'))
           ->asc()
           ->limit($limit);

        return $db->queryAll();
    }

    /**
     * Counts events in a given status.
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
     * @return array
     */
    public function fetchStatusBreakdown()
    {
        $db = $this->db->select(['status'])
            ->count('id', 'count')
            ->from(self::getTableName())
            ->groupBy('status');

        $result = ['pending' => 0, 'approved' => 0, 'rejected' => 0];

        foreach ($db->queryAll() as $row) {
            $result[$row['status']] = (int) $row['count'];
        }

        return $result;
    }
}