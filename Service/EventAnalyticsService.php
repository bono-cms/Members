<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */

namespace Members\Service;

use Members\Storage\MySQL\EventAnalyticsMapper;
use Members\Storage\MySQL\EventMapper;

/**
 * Owns the analytics recording and aggregation rules.
 *
 * Controllers never write to the analytics table directly — they
 * call recordView() or recordClick(), and this service decides
 * whether the hit should actually be persisted (anti-spam) and
 * which denormalized counter to bump.
 *
 * The IP hash is the only visitor identifier we keep. It is
 * SHA1(ip + salt), so the raw IP never touches the database.
 *
 * Counters are language-independent, so existence checks read the
 * base row only. Payloads that carry an EventEntity need the
 * translation join and use fetchRichById().
 */
final class EventAnalyticsService
{
    /**
     * Look-back window for the anti-spam check, in minutes.
     *
     * @const int
     */
    const ANTI_SPAM_MINUTES = 10;

    /**
     * Analytics type constants. Mirror the ENUM in the schema.
     *
     * @const string
     */
    const TYPE_VIEW  = 'view';
    const TYPE_CLICK = 'click';

    /**
     * Analytics mapper instance
     *
     * @var \Members\Storage\MySQL\EventAnalyticsMapper
     */
    private $analyticsMapper;

    /**
     * Event mapper instance
     *
     * @var \Members\Storage\MySQL\EventMapper
     */
    private $eventMapper;

    /**
     * Salt used to hash the visitor IP.
     *
     * @var string
     */
    private $ipHashSalt;

    /**
     * State initialization
     *
     * @param \Members\Storage\MySQL\EventAnalyticsMapper $analyticsMapper
     * @param \Members\Storage\MySQL\EventMapper $eventMapper
     * @param string $ipHashSalt
     * @return void
     */
    public function __construct(EventAnalyticsMapper $analyticsMapper, EventMapper $eventMapper, $ipHashSalt = '')
    {
        $this->analyticsMapper = $analyticsMapper;
        $this->eventMapper     = $eventMapper;
        $this->ipHashSalt      = (string) $ipHashSalt;
    }

    /**
     * Records a page view for an event.
     *
     * @param  int         $eventId
     * @param  int|null    $memberId  Null for anonymous visitors
     * @param  string      $source    "card" | "direct" | "admin" | "api"
     * @param  string|null $ip
     * @param  string|null $userAgent
     * @return bool True when the hit was actually recorded
     */
    public function recordView($eventId, $memberId, $source, $ip = null, $userAgent = null)
    {
        return $this->recordEvent($eventId, $memberId, self::TYPE_VIEW, $source, 'views_count', $ip, $userAgent);
    }

    /**
     * Records a click on the event's registration link.
     *
     * @param  int         $eventId
     * @param  int|null    $memberId
     * @param  string      $source
     * @param  string|null $ip
     * @param  string|null $userAgent
     * @return bool
     */
    public function recordClick($eventId, $memberId, $source, $ip = null, $userAgent = null)
    {
        return $this->recordEvent($eventId, $memberId, self::TYPE_CLICK, $source, 'clicks_count', $ip, $userAgent);
    }

    /**
     * Returns the detailed analytics for one event, owned by a
     * specific member.
     *
     * @param  int $eventId
     * @param  int $memberId
     * @return array|null Null when the event does not exist or is not owned by the member
     */
    public function fetchForMemberEvent($eventId, $memberId)
    {
        $event = $this->eventMapper->fetchRichById((int) $eventId);

        if ($event === false) {
            return null;
        }

        if ((int) $event['creator_id'] !== (int) $memberId) {
            return null;
        }

        return $this->buildEventAnalytics($event);
    }

    /**
     * Returns the detailed analytics for one event, without ownership check.
     *
     * @param int $eventId
     * @return array|null Null when the event does not exist
     */
    public function fetchForAdminEvent($eventId)
    {
        $event = $this->eventMapper->fetchRichById((int) $eventId);

        if ($event === false) {
            return null;
        }

        return $this->buildEventAnalytics($event);
    }

    /**
     * Returns an aggregated dashboard for a member across all
     * their events.
     *
     * @param  int $memberId
     * @return array
     */
    public function fetchForMemberDashboard($memberId)
    {
        $memberId = (int) $memberId;

        // Fetch all events of this member (no pagination — we need totals)
        $events = $this->eventMapper->fetchByCreatorId($memberId, 1, 10000);

        $totalViews  = 0;
        $totalClicks = 0;
        $eventCount  = count($events);

        foreach ($events as $event) {
            $totalViews  += (int) $event['views_count'];
            $totalClicks += (int) $event['clicks_count'];
        }

        $conversion = $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0.0;

        return [
            'eventCount'  => $eventCount,
            'totalViews'  => $totalViews,
            'totalClicks' => $totalClicks,
            'conversion'  => $conversion,
        ];
    }

    /**
     * Returns the global admin dashboard.
     *
     * @return array
     */
    public function fetchAdminDashboard()
    {
        $globalStats       = $this->analyticsMapper->fetchGlobalStats();
        $statusBreakdown   = $this->eventMapper->fetchStatusBreakdown();
        $topEventsByViews  = $this->analyticsMapper->fetchTopEvents(10, 'views');
        $topEventsByClicks = $this->analyticsMapper->fetchTopEvents(5, 'clicks');
        $topAuthors        = $this->analyticsMapper->fetchTopAuthors(5);
        $dailyStats        = $this->analyticsMapper->fetchGlobalDailyStats(14);

        $totalViews  = isset($globalStats['total_views']) ? (int) $globalStats['total_views'] : 0;
        $totalClicks = isset($globalStats['total_clicks']) ? (int) $globalStats['total_clicks'] : 0;

        $conversion = $totalViews > 0 ? round(($totalClicks / $totalViews) * 100, 2) : 0.0;

        return [
            'totalEvents'       => isset($globalStats['total_events']) ? (int) $globalStats['total_events'] : 0,
            'totalViews'        => $totalViews,
            'totalClicks'       => $totalClicks,
            'uniqueMembers'     => isset($globalStats['unique_members']) ? (int) $globalStats['unique_members'] : 0,
            'conversion'        => $conversion,
            'statusBreakdown'   => $statusBreakdown,
            'topEventsByViews'  => $topEventsByViews,
            'topEventsByClicks' => $topEventsByClicks,
            'topAuthors'        => $topAuthors,
            'dailyStats'        => $dailyStats,
        ];
    }

    /**
     * Records a single analytics event with anti-spam protection.
     *
     * The existence check reads the base row only: no content is
     * needed to decide whether a hit may be recorded.
     *
     * @param  int         $eventId
     * @param  int|null    $memberId
     * @param  string      $type
     * @param  string      $source
     * @param  string      $counterColumn
     * @param  string|null $ip
     * @param  string|null $userAgent
     * @return bool
     */
    private function recordEvent($eventId, $memberId, $type, $source, $counterColumn, $ip, $userAgent)
    {
        $eventId = (int) $eventId;
        $event   = $this->eventMapper->findByPk($eventId);

        if ($event === false) {
            return false;
        }

        $ipHash = $this->computeIpHash($ip);

        // Anti-spam: skip if this visitor already hit the same
        // event with the same type within the look-back window.
        // When no IP is available we cannot deduplicate, so we
        // always record — the counter may be slightly inflated
        // in that case, but we never lose a legitimate hit.
        if ($ipHash !== null && $this->analyticsMapper->hasRecentEvent($eventId, $ipHash, $type, self::ANTI_SPAM_MINUTES)) {
            return false;
        }

        return $this->analyticsMapper->wrapTransaction(function () use ($eventId, $memberId, $type, $source, $counterColumn, $ipHash, $userAgent) {
            $ok = $this->analyticsMapper->recordEvent(
                $eventId,
                $memberId === null ? null : (int) $memberId,
                $type,
                $source,
                $ipHash,
                $userAgent
            );

            if (!$ok) {
                return false;
            }

            $counterType = $counterColumn === 'clicks_count' ? 'clicks' : 'views';

            $this->eventMapper->incrementCounter($eventId, $counterType);

            return true;
        });
    }

    /**
     * Computes the SHA1 hash of the visitor IP with the salt.
     *
     * @param  string|null $ip
     * @return string|null
     */
    private function computeIpHash($ip)
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return sha1((string) $ip . $this->ipHashSalt);
    }

    /**
     * Builds the detailed analytics payload for a single event.
     *
     * Expects a rich row from EventMapper::fetchRichById(), so the
     * translated content and the author fields are present.
     *
     * @param  array $event Event row
     * @return array
     */
    private function buildEventAnalytics(array $event)
    {
        $entity = new EventEntity();

        $entity->setId((int) $event['id'])
               ->setCreatorId(isset($event['creator_id']) && $event['creator_id'] !== null ? (int) $event['creator_id'] : null)
               ->setLangId(isset($event['lang_id']) ? (int) $event['lang_id'] : null)
               ->setTitle(isset($event['title']) ? $event['title'] : '')
               ->setDescription(isset($event['description']) ? $event['description'] : '')
               ->setOrganizer(isset($event['organizer']) ? $event['organizer'] : '')
               ->setLink(isset($event['link']) ? $event['link'] : '')
               ->setLocationName(isset($event['location_name']) ? $event['location_name'] : '')
               ->setLatitude(isset($event['latitude']) && $event['latitude'] !== null ? (float) $event['latitude'] : null)
               ->setLongitude(isset($event['longitude']) && $event['longitude'] !== null ? (float) $event['longitude'] : null)
               ->setLocationSource(isset($event['location_source']) ? $event['location_source'] : EventEntity::LOCATION_SOURCE_NONE)
               ->setStartsAt(isset($event['starts_at']) ? $event['starts_at'] : '')
               ->setEndsAt(isset($event['ends_at']) ? $event['ends_at'] : '')
               ->setStatus(isset($event['status']) ? $event['status'] : EventEntity::STATUS_PENDING)
               ->setRejectionReason(isset($event['rejection_reason']) ? $event['rejection_reason'] : '')
               ->setViewsCount((int) $event['views_count'])
               ->setClicksCount((int) $event['clicks_count'])
               ->setCreatedAt(isset($event['created_at']) ? $event['created_at'] : '')
               ->setCreatorName(isset($event['creator_name']) ? $event['creator_name'] : null)
               ->setCreatorAvatar(isset($event['creator_avatar']) ? $event['creator_avatar'] : null);

        $views      = $entity->getViewsCount();
        $clicks     = $entity->getClicksCount();
        $conversion = $views > 0 ? round(($clicks / $views) * 100, 2) : 0.0;

        $dailyStats  = $this->analyticsMapper->fetchDailyStats($entity->getId(), 14);
        $sourceStats = $this->analyticsMapper->fetchSourceStats($entity->getId());

        return [
            'event'       => $entity,
            'views'       => $views,
            'clicks'      => $clicks,
            'conversion'  => $conversion,
            'dailyStats'  => $dailyStats,
            'sourceStats' => $sourceStats,
        ];
    }
}