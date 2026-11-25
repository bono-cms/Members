<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */

namespace Members\Controller\Api;

use Members\Service\EventEntity;

/**
 * API controller for the Events extension.
 *
 * Provides JSON endpoints for the mobile app. Public endpoints
 * (catalog, detail, click, map) require no auth. Protected endpoints
 * (create, my events, analytics) require JWT.
 *
 * Content is resolved for the language selected by ?lang=. An event
 * with no translation in that language is treated as not found.
 */
final class Event extends AbstractApiController
{
    /**
     * GET /api/v1/members/events
     *
     * Returns a paginated list of approved events.
     *
     * @return string JSON
     */
    public function indexAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);
        $eventService = $this->getModuleService('eventService');

        $events = $eventService->fetchApproved($pageNumber, 20);
        $paginator = $eventService->getPaginator();

        return $this->json([
            'data' => $this->presentEvents($events),
            'meta' => [
                'page'       => $paginator->getCurrentPage(),
                'perPage'    => $paginator->getItemsPerPage(),
                'total'      => $paginator->getTotalAmount(),
                'totalPages' => $paginator->getLastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/members/events/render/map
     *
     * Returns approved events with valid coordinates for map rendering.
     *
     * @return string JSON
     */
    public function mapAction()
    {
        $eventService = $this->getModuleService('eventService');
        $filters = [];

        // Date filters
        $from = $this->request->getQuery('from');
        $to = $this->request->getQuery('to');

        if ($from) {
            $filters['from'] = $from;
        }

        if ($to) {
            $filters['to'] = $to;
        }

        // Bounding box: minLat,minLng,maxLat,maxLng
        $bboxRaw = $this->request->getQuery('bbox');

        if ($bboxRaw) {
            $bbox = explode(',', $bboxRaw);

            if (count($bbox) === 4) {
                $bbox = array_map('floatval', $bbox);

                $minLat = min($bbox[0], $bbox[2]);
                $maxLat = max($bbox[0], $bbox[2]);
                $minLng = min($bbox[1], $bbox[3]);
                $maxLng = max($bbox[1], $bbox[3]);

                if (
                    $minLat >= -90 && $maxLat <= 90 &&
                    $minLng >= -180 && $maxLng <= 180
                ) {
                    $filters['bbox'] = [
                        $minLat,
                        $minLng,
                        $maxLat,
                        $maxLng,
                    ];
                }
            }
        }

        // Limit: 1-1000
        $limit = (int) $this->request->getQuery('limit', 500);
        $limit = max(1, min($limit, 1000));

        $events = $eventService->fetchMapPoints($filters, $limit);

        foreach ($events as &$event) {
            $event['id'] = (int) $event['id'];
            $event['latitude'] = (float) $event['latitude'];
            $event['longitude'] = (float) $event['longitude'];

            // Link to the public event page
            $event['url'] = $this->createUrl(
                'Members:Event@viewAction',
                [$event['id']]
            );
        }

        unset($event);

        return $this->json([
            'data' => $events,
            'meta' => [
                'total' => count($events),
                'limit' => $limit,
                'clipped' => count($events) >= $limit,
            ],
        ]);
    }

    /**
     * GET /api/v1/members/events/view/:id
     *
     * Returns a single event and records a view.
     *
     * @param string $id Event id
     * @return string JSON
     */
    public function viewAction($id)
    {
        $eventService = $this->getModuleService('eventService');
        $event = $eventService->findById((int) $id);

        if ($event === false || $event->getStatus() !== EventEntity::STATUS_APPROVED) {
            $this->response->setStatusCode(404);
            return $this->json(['error' => 'Event not found']);
        }

        $analyticsService = $this->getModuleService('eventAnalyticsService');
        $memberId         = $this->getAuthUserId();

        $analyticsService->recordView(
            (int) $id,
            $memberId > 0 ? $memberId : null,
            'api',
            $this->request->getClientIP(),
            $this->request->getUserAgent()
        );

        return $this->json([
            'data' => $this->presentEvent($event),
        ]);
    }

    /**
     * POST /api/v1/members/events/store
     *
     * Creates a new event on behalf of the authenticated member.
     *
     * Content is posted as translation[<langId>][title|description|organizer],
     * with translation[<langId>][lang_id] alongside it, exactly as the web
     * form does. Every posted row is written.
     *
     * @return string JSON
     */
    public function storeAction()
    {
        $validator = $this->createEventValidator();

        if (!$validator->isPassed()) {
            $this->response->setStatusCode(422);
            return $this->json(['errors' => $validator->getErrors()]);
        }

        $memberId = $this->getAuthUserId();

        if ($memberId <= 0) {
            $this->response->setStatusCode(401);
            return $this->json(['error' => 'Unauthorized']);
        }

        $eventService = $this->getModuleService('eventService');

        try {
            $eventId = $eventService->createForUser($memberId, $this->request->getAll());

            $this->response->setStatusCode(201);

            return $this->json([
                'id'      => $eventId,
                'message' => 'The event has been created and is pending review',
            ]);
        } catch (\InvalidArgumentException $exception) {
            $this->response->setStatusCode(422);
            return $this->json(['error' => $exception->getMessage()]);
        }
    }

    /**
     * GET /api/v1/members/me/events
     *
     * Returns the authenticated member's own events with a summary.
     *
     * @return string JSON
     */
    public function myAction()
    {
        $memberId = $this->getAuthUserId();

        if ($memberId <= 0) {
            $this->response->setStatusCode(401);
            return $this->json(['error' => 'Unauthorized']);
        }

        $pageNumber = (int) $this->request->getQuery('page', 1);

        $eventService = $this->getModuleService('eventService');
        $analyticsService = $this->getModuleService('eventAnalyticsService');

        $events = $eventService->fetchByCreatorId($memberId, $pageNumber, 20);
        $dashboard = $analyticsService->fetchForMemberDashboard($memberId);
        $paginator = $eventService->getPaginator();

        return $this->json([
            'data'    => $this->presentEvents($events),
            'summary' => $dashboard,
            'meta'    => [
                'page' => $paginator->getCurrentPage(),
                'perPage' => $paginator->getItemsPerPage(),
                'total' => $paginator->getTotalAmount(),
                'totalPages' => $paginator->getLastPage(),
            ]
        ]);
    }

    /**
     * GET /api/v1/members/me/events/:id/analytics
     *
     * Returns detailed analytics for the member's own event.
     *
     * @param string $id Event id
     * @return string JSON
     */
    public function analyticsAction($id)
    {
        $memberId = $this->getAuthUserId();

        if ($memberId <= 0) {
            $this->response->setStatusCode(401);
            return $this->json(['error' => 'Unauthorized']);
        }

        $analyticsService = $this->getModuleService('eventAnalyticsService');
        $analytics = $analyticsService->fetchForMemberEvent((int) $id, $memberId);

        if ($analytics === null) {
            $this->response->setStatusCode(404);
            return $this->json(['error' => 'Event not found or access denied']);
        }

        return $this->json([
            'data' => $this->presentAnalytics($analytics),
        ]);
    }

    /**
     * GET /api/v1/members/events/:id/click
     *
     * Returns the registration link and records a click.
     *
     * @param string $id Event id
     * @return string JSON
     */
    public function clickAction($id)
    {
        $eventService = $this->getModuleService('eventService');
        $event = $eventService->findById((int) $id);

        if ($event === false || $event->getStatus() !== EventEntity::STATUS_APPROVED) {
            $this->response->setStatusCode(404);
            return $this->json(['error' => 'Event not found']);
        }

        if (!$event->hasLink()) {
            $this->response->setStatusCode(404);
            return $this->json(['error' => 'No registration link available']);
        }

        $analyticsService = $this->getModuleService('eventAnalyticsService');
        $memberId = $this->getAuthUserId();

        $analyticsService->recordClick(
            (int) $id,
            $memberId > 0 ? $memberId : null,
            'api',
            $this->request->getClientIP(),
            $this->request->getUserAgent()
        );

        return $this->json([
            'link' => $event->getLink()
        ]);
    }

    /**
     * Presents a collection of events for API output
     *
     * @param  array $events Event entities
     * @return array
     */
    private function presentEvents(array $events)
    {
        $result = [];

        foreach ($events as $event) {
            $result[] = $this->presentEvent($event);
        }

        return $result;
    }

    /**
     * Presents a single event for API output
     *
     * @param  \Members\Service\EventEntity $event
     * @return array
     */
    private function presentEvent($event)
    {
        return [
            'id'           => $event->getId(),
            'cover'        => $event->getCoverUrl(EventEntity::COVER_CARD),
            'title'        => $event->getTitle(),
            'description'  => $event->getDescription(),
            'organizer'    => $event->getOrganizer(),
            'link'         => $event->getLink(),
            'locationName' => $event->getLocationName(),
            'latitude'     => $event->getLatitude(),
            'longitude'    => $event->getLongitude(),
            'startsAt'     => $event->getStartsAt(),
            'endsAt'       => $event->getEndsAt(),
            'status'       => $event->getStatus(),
            'creatorName'  => $event->getCreatorName(),
            'viewsCount'   => $event->getViewsCount(),
            'clicksCount'  => $event->getClicksCount(),
        ];
    }

    /**
     * Presents analytics data for API output
     *
     * @param  array $analytics Analytics payload
     * @return array
     */
    private function presentAnalytics(array $analytics)
    {
        return [
            'event'       => $this->presentEvent($analytics['event']),
            'views'       => $analytics['views'],
            'clicks'      => $analytics['clicks'],
            'conversion'  => $analytics['conversion'],
            'dailyStats'  => $analytics['dailyStats'],
            'sourceStats' => $analytics['sourceStats'],
        ];
    }

    /**
     * Creates a validator for the event form
     *
     * @return \Krystal\Validation\Validator
     */
    private function createEventValidator()
    {
        $validator = $this->createValidation();

        $validator->field('translation.*.title')
                  ->addRule('maxlength', null, ['max' => 200]);

        $validator->field('translation.*.organizer')
                  ->addRule('maxlength', null, ['max' => 200]);

        $validator->field('event.location_name')
                  ->required()
                  ->addRule('maxlength', null, ['max' => 255]);

        $validator->field('event.starts_at')
                  ->required();

        $validator->field('event.link')
                  ->addRule('maxlength', null, ['max' => 500]);

        return $validator;
    }
}