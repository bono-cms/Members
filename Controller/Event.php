<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */
namespace Members\Controller;

use Krystal\Stdlib\VirtualEntity;
use Members\Service\EventEntity;

/**
 * Site-side events controller.
 *
 * Handles both public catalog/detail pages (no auth required) and
 * member dashboard pages (auth required via middleware).
 */
final class Event extends AbstractController
{
    /**
     * Renders the public catalog of approved events
     *
     * @return string
     */
    public function catalogAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);
        $eventService = $this->getModuleService('eventService');
        $events = $eventService->fetchApproved($pageNumber, 20);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Events'))
             ->setSeo(true);

        return $this->view->render('events-catalog', [
            'page'      => $page,
            'events'    => $events,
            'paginator' => $eventService->getPaginator(),
        ]);
    }

    /**
     * Renders the detail page of a single event
     *
     * @param  string $id Event id
     * @return string|bool
     */
    public function viewAction($id)
    {
        $eventService = $this->getModuleService('eventService');
        $event = $eventService->findById((int) $id);

        if ($event === false || $event->getStatus() !== EventEntity::STATUS_APPROVED) {
            return false;
        }

        $analyticsService = $this->getModuleService('eventAnalyticsService');
        $memberManager    = $this->getModuleService('memberManager');
        $memberId         = $memberManager->isLoggedIn() ? (int) $memberManager->getMember('id') : null;

        $analyticsService->recordView((int) $id, $memberId, 'card', $this->request->getClientIP(), $this->request->getUserAgent());

        $page = new VirtualEntity();
        $page->setTitle($event['title'])
            ->setSeo(true);

        return $this->view->render('event', [
            'page'  => $page,
            'event' => $event,
        ]);
    }

    /**
     * Redirects to the event's registration link and records the click
     *
     * @param string $id Event id
     * @return void
     */
    public function clickAction($id)
    {
        $eventService = $this->getModuleService('eventService');
        $event = $eventService->findById((int) $id);

        if ($event === false || $event->getStatus() !== EventEntity::STATUS_APPROVED) {
            return false;
        }

        if (!$event->hasLink()) {
            return false;
        }

        $analyticsService = $this->getModuleService('eventAnalyticsService');
        $memberManager    = $this->getModuleService('memberManager');
        $memberId         = $memberManager->isLoggedIn() ? (int) $memberManager->getMember('id') : null;

        $analyticsService->recordClick((int) $id, $memberId, 'card',
            $this->request->getClientIP(),
            $this->request->getUserAgent()
        );

        $this->response->redirect($event['link']);
    }

    /**
     * Renders the member's "My events" dashboard
     *
     * @return string
     */
    public function indexAction()
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberId = (int) $memberManager->getMember('id');
        $pageNumber = (int) $this->request->getQuery('page', 1);
        $eventService = $this->getModuleService('eventService');
        $analyticsService = $this->getModuleService('eventAnalyticsService');

        $events = $eventService->fetchByCreatorId($memberId, $pageNumber, 20);
        $dashboard = $analyticsService->fetchForMemberDashboard($memberId);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('My events'))
            ->setSeo(false);

        return $this->view->render('member-events', [
            'page'      => $page,
            'events'    => $events,
            'paginator' => $eventService->getPaginator(),
            'dashboard' => $dashboard,
        ]);
    }

    /**
     * Renders the form for creating a new event
     *
     * @return string
     */
    public function createAction()
    {
        $event = new VirtualEntity();
        $event->setStatus('pending');

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Create event'))
             ->setSeo(false);

        return $this->view->render('member-event-form', [
            'page'  => $page,
            'event' => $event,
            'isNew' => true
        ]);
    }

    /**
     * Persists a new event created by a member
     *
     * @return string JSON
     */
    public function storeAction()
    {
        $validator = $this->createEventValidator($this->request->getPost('event'));

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors(),
            ]);
        }

        $memberManager = $this->getModuleService('memberManager');
        $memberId      = (int) $memberManager->getMember('id');
        $eventService  = $this->getModuleService('eventService');
        $data          = $this->prepareEventData($this->request->getPost('event'));

        try {
            $eventId = $eventService->createForUser($memberId, $data);
            $this->flashBag->set('success', $this->translator->translate('The event has been created and is pending review'));

            return $this->json([
                'redirect' => $this->createUrl('Members:Event@indexAction'),
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'errors' => ['event' => [$exception->getMessage()]],
            ]);
        }
    }

    /**
     * Renders the form for editing an existing event
     *
     * @param  string $id Event id
     * @return string|bool
     */
    public function editAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberId = (int) $memberManager->getMember('id');
        $eventService = $this->getModuleService('eventService');
        $event = $eventService->findByIdForOwner((int) $id, $memberId);

        if ($event === false) {
            return false;
        }

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Edit event'))
             ->setSeo(false);

        return $this->view->render('member-event-form', [
            'page'  => $page,
            'event' => $event,
            'isNew' => false,
        ]);
    }

    /**
     * Updates an existing event
     *
     * @param  string $id Event id
     * @return string JSON
     */
    public function updateAction($id)
    {
        $validator = $this->createEventValidator($this->request->getPost('event'));

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors(),
            ]);
        }

        $memberManager = $this->getModuleService('memberManager');
        $memberId = (int) $memberManager->getMember('id');
        $eventService = $this->getModuleService('eventService');
        $data = $this->prepareEventData($this->request->getPost('event'));

        try {
            $eventService->update((int) $id, $data, $memberId, false);
            $this->flashBag->set('success', $this->translator->translate('The event has been updated successfully'));

            return $this->json([
                'refresh' => true,
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'errors' => ['event' => [$exception->getMessage()]],
            ]);
        }
    }

    /**
     * Deletes an event
     *
     * @param  string $id Event id
     * @return string JSON
     */
    public function deleteAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberId = (int) $memberManager->getMember('id');
        $eventService = $this->getModuleService('eventService');

        try {
            $eventService->delete((int) $id, $memberId, false);
            $this->flashBag->set('success', $this->translator->translate('The event has been removed successfully'));

            return $this->json([
                'refresh' => true,
            ]);
        } catch (\InvalidArgumentException $exception) {
            return $this->json([
                'errors' => ['event' => [$exception->getMessage()]],
            ]);
        }
    }

    /**
     * Renders detailed analytics for a member's own event
     *
     * @param  string $id Event id
     * @return string|bool
     */
    public function analyticsAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberId = (int) $memberManager->getMember('id');
        $analyticsService = $this->getModuleService('eventAnalyticsService');
        $analytics = $analyticsService->fetchForMemberEvent((int) $id, $memberId);

        if ($analytics === null) {
            return false;
        }

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Event analytics'))
             ->setSeo(false);

        return $this->view->render('member-event-analytics', [
            'page'      => $page,
            'event'     => $analytics['event'],
            'analytics' => $analytics,
        ]);
    }

    /**
     * Parses a map URL and returns coordinates
     *
     * @return string JSON
     */
    public function parseLocationAction()
    {
        $url = $this->request->getPost('url');
        $eventService = $this->getModuleService('eventService');
        $coords = $eventService->parseLocation($url);

        if ($coords === null) {
            return $this->json([
                'error' => $this->translator->translate('Could not parse the map URL'),
            ]);
        }

        return $this->json([
            'lat'    => $coords['lat'],
            'lng'    => $coords['lng'],
            'source' => $coords['source'],
        ]);
    }

    /**
     * Checks whether the Events extension is enabled
     *
     * @return bool
     */
    private function isExtensionEnabled()
    {
        $config = $this->moduleManager->getModule($this->moduleName)->getConfig('extensions');

        return !empty($config['events']['enabled']);
    }

    /**
     * Creates a validator for the event form
     *
     * @param  array $data Form data
     * @return \Krystal\Validation\Validator
     */
    private function createEventValidator($data)
    {
        $validator = $this->createValidation();

        $validator->field('event.title')
            ->required()
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

    /**
     * Prepares event data from the form payload
     *
     * @param  array $data Form data
     * @return array
     */
    private function prepareEventData($data)
    {
        return [
            'title'           => isset($data['title']) ? $data['title'] : '',
            'description'     => isset($data['description']) ? $data['description'] : null,
            'link'            => isset($data['link']) ? $data['link'] : null,
            'location_name'   => isset($data['location_name']) ? $data['location_name'] : '',
            'latitude'        => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            'longitude'       => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            'location_source' => isset($data['location_source']) ? $data['location_source'] : 'none',
            'starts_at'       => isset($data['starts_at']) ? $data['starts_at'] : '',
            'ends_at'         => isset($data['ends_at']) && $data['ends_at'] !== '' ? $data['ends_at'] : null,
        ];
    }
}