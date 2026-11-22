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

/**
 * Persists event analytics rows (views and clicks) and provides
 * the aggregation queries consumed by the analytics dashboards.
 *
 * The mapper never decides whether a hit should be recorded —
 * that rule (anti-spam, source detection, member lookup) lives
 * in EventAnalyticsService. The mapper only stores and reads.
 */
final class EventAnalyticsMapper extends AbstractMapper
{
    /**
     * {@inheritDoc}
     */
    public static function getTableName()
    {
        return self::getWithPrefix('bono_module_members_event_analytics');
    }

    /**
     * {@inheritDoc}
     */
    protected function getPk()
    {
        return 'id';
    }

    /**
     * Records a single view or click event.
     * 
     * @param  int         $eventId
     * @param  int|null    $memberId  Null for anonymous visitors
     * @param  string      $type      "view" or "click"
     * @param  string      $source    "card" | "direct" | "admin" | "api"
     * @param  string|null $ipHash    SHA1(ip + salt), or null
     * @param  string|null $userAgent Truncated user agent, or null
     * @return bool
     */
    public function recordEvent($eventId, $memberId, $type, $source, $ipHash = null, $userAgent = null)
    {
        $db = $this->db->insert(self::getTableName(), [
            'event_id'   => (int) $eventId,
            'member_id'  => $memberId === null ? null : (int) $memberId,
            'type'       => $type,
            'source'     => $source,
            'ip_hash'    => $ipHash,
            'user_agent' => $userAgent === null ? null : mb_substr((string) $userAgent, 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $db->execute();
    }

    /**
     * Checks whether the same ip_hash already recorded an event of
     * the same type for the same event within the look-back window.
     *
     * Returns false when no hash is supplied: without a hash there
     * is nothing to deduplicate on, and "= NULL" would never match
     * anyway. Timestamps use date() (server timezone) because that
     * is what recordEvent() writes.
     *
     * @param  int         $eventId
     * @param  string|null $ipHash
     * @param  string      $type
     * @param  int         $minutes
     * @return bool        True when a recent duplicate exists
     */
    public function hasRecentEvent($eventId, $ipHash, $type, $minutes = 10)
    {
        if ($ipHash === null || $ipHash === '') {
            return false;
        }

        $since = date('Y-m-d H:i:s', time() - (int) $minutes * 60);

        $db = $this->db->select()
            ->count('id', 'count')
            ->from(self::getTableName())
            ->whereEquals('event_id', (int) $eventId)
            ->andWhereEquals('ip_hash', $ipHash)
            ->andWhereEquals('type', $type)
            ->andWhere('created_at', '>=', $since);

        return (int) $db->query('count') > 0;
    }

    /**
     * Returns daily view/click counts for one event.
     *
     * Days with no hits are filled with zeros so the chart always
     * has a continuous time axis.
     *
     * @param  int $eventId
     * @param  int $days    Number of days including today
     * @return array
     */
    public function fetchDailyStats($eventId, $days = 14)
    {
        $days  = max(1, (int) $days);
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        // RawSqlFragment must be a VALUE, not an array key.
        // groupBy() takes a plain string because it does not quote it.
        $db = $this->db->select(['type', new RawSqlFragment('DATE(created_at) AS date')])
            ->count('id', 'count')
            ->from(self::getTableName())
            ->whereEquals('event_id', (int) $eventId)
            ->andWhere('created_at', '>=', $since)
            ->groupBy('DATE(created_at), type')
            ->orderBy('date')
            ->asc();

        return $this->normalizeDailyRows($db->queryAll(), $days);
    }

    /**
     * Returns daily view/click counts across all events.
     *
     * @param  int $days
     * @return array
     */
    public function fetchGlobalDailyStats($days = 14)
    {
        $days  = max(1, (int) $days);
        $since = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        $db = $this->db->select(['type', new RawSqlFragment('DATE(created_at) AS date')])
            ->count('id', 'count')
            ->from(self::getTableName())
            ->where('created_at', '>=', $since)
            ->groupBy('DATE(created_at), type')
            ->orderBy('date')
            ->asc();

        return $this->normalizeDailyRows($db->queryAll(), $days);
    }

    /**
     * Fills missing days with zeros and flattens the type column.
     *
     * @param  array $rows Raw grouped rows
     * @param  int $days
     * @return array
     */
    private function normalizeDailyRows(array $rows, $days)
    {
        $byDate = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $byDate[$date] = ['views' => 0, 'clicks' => 0];
        }

        foreach ($rows as $row) {
            $date = $row['date'];

            if (!isset($byDate[$date])) {
                continue;
            }

            $key = $row['type'] === 'click' ? 'clicks' : 'views';
            $byDate[$date][$key] = (int) $row['count'];
        }

        $result = [];

        foreach ($byDate as $date => $counts) {
            $result[] = [
                'date'   => $date,
                'views'  => $counts['views'],
                'clicks' => $counts['clicks'],
            ];
        }

        return $result;
    }

    /**
     * Returns view/click counts grouped by source for one event.
     *
     * @param  int $eventId
     * @return array
     */
    public function fetchSourceStats($eventId)
    {
        $db = $this->db->select(['source', 'type'])
            ->count('id', 'count')
            ->from(self::getTableName())
            ->whereEquals('event_id', (int) $eventId)
            ->groupBy(['source', 'type']);

        $bySource = [];

        foreach ($db->queryAll() as $row) {
            $source = $row['source'];

            if (!isset($bySource[$source])) {
                $bySource[$source] = ['source' => $source, 'views' => 0, 'clicks' => 0];
            }

            $key = $row['type'] === 'click' ? 'clicks' : 'views';
            $bySource[$source][$key] = (int) $row['count'];
        }

        return array_values($bySource);
    }

    /**
     * Returns the top N events by views or clicks.
     *
     * Reads the denormalized counters on the events table, so no
     * aggregation over the analytics log is needed here.
     *
     * @param  int    $limit
     * @param  string $type "views" or "clicks"
     * @return array
     */
    public function fetchTopEvents($limit, $type = 'views')
    {
        $column = $type === 'clicks' ? 'clicks_count' : 'views_count';

        $db = $this->db->select([
            EventMapper::column('id'),
            EventMapper::column('title'),
            EventMapper::column('creator_id'),
            EventMapper::column($column)            => 'counter',
            MemberTranslationMapper::column('name') => 'creator_name',
        ])
            ->from(EventMapper::getTableName())
            ->leftJoin(MemberTranslationMapper::getTableName(), [
                MemberTranslationMapper::column('id')      => EventMapper::getRawColumn('creator_id'),
                MemberTranslationMapper::column('lang_id') => $this->getLangId(),
            ])
            ->orderBy(EventMapper::column($column))
            ->desc()
            ->limit((int) $limit);

        return $db->queryAll();
    }

    /**
     * Returns the top N authors by total views across their events.
     *
     * Official events (creator_id IS NULL) are excluded by the
     * INNER JOIN — they have no author to rank.
     *
     * @param  int $limit
     * @return array
     */
    public function fetchTopAuthors($limit)
    {
        $db = $this->db->select([
            EventMapper::column('creator_id'),
            MemberTranslationMapper::column('name') => 'creator_name',
        ])
            ->sum(EventMapper::column('views_count'), 'total_views')
            ->sum(EventMapper::column('clicks_count'), 'total_clicks')
            ->count(EventMapper::column('id'), 'event_count')
            ->from(EventMapper::getTableName())
            ->innerJoin(MemberTranslationMapper::getTableName(), [
                MemberTranslationMapper::column('id')      => EventMapper::getRawColumn('creator_id'),
                MemberTranslationMapper::column('lang_id') => $this->getLangId(),
            ])
            ->groupBy([
                EventMapper::column('creator_id'),
                MemberTranslationMapper::column('name'),
            ])
            ->orderBy('total_views')
            ->desc()
            ->limit((int) $limit);

        return $db->queryAll();
    }

    /**
     * Returns global counters for the admin dashboard.
     *
     * COUNT(DISTINCT ...) is expressed through a raw fragment,
     * because count() only accepts a column and an alias.
     *
     * @return array
     */
    public function fetchGlobalStats()
    {
        $totalsDb = $this->db->select()
            ->count('id', 'total_events')
            ->sum('views_count', 'total_views')
            ->sum('clicks_count', 'total_clicks')
            ->from(EventMapper::getTableName());

        $totals = $totalsDb->query();

        $uniqueDb = $this->db->select()
            ->count(new RawSqlFragment('DISTINCT member_id'), 'count')
            ->from(self::getTableName())
            ->where('member_id', 'IS NOT', new RawSqlFragment('NULL'));

        return [
            'total_views'    => isset($totals['total_views']) ? (int) $totals['total_views'] : 0,
            'total_clicks'   => isset($totals['total_clicks']) ? (int) $totals['total_clicks'] : 0,
            'total_events'   => isset($totals['total_events']) ? (int) $totals['total_events'] : 0,
            'unique_members' => (int) $uniqueDb->query('count'),
        ];
    }
}