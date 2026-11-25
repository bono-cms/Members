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
 *
 * Every read joins the translation table for the active language,
 * so an event that has no translation there is treated as not found.
 */
final class Event extends AbstractController
{
    /**
     * Renders the public catalog of actual (non-expired) events
     *
     * @return string
     */
    public function catalogAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);
        $eventService = $this->getModuleService('eventService');

        $events = $eventService->fetchUpcoming($pageNumber, 20);

        // Fetch ALL non-expired events with coordinates for the map
        $mapPoints = $eventService->fetchMapPoints(['period' => 'upcoming'], 1000);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Events'))
             ->setSeo(true);

        return $this->view->render('events-catalog', [
            'page' => $page,
            'events' => $events,
            'paginator' => $eventService->getPaginator(),
            'mapPoints' => $mapPoints
        ]);
    }

    /**
     * Renders the archive of expired events.
     *
     * No map is rendered here: the archive is a historical list, and
     * the map endpoint only serves actual events.
     *
     * @return string
     */
    public function archiveAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);
        $eventService = $this->getModuleService('eventService');
        $events = $eventService->fetchPast($pageNumber, 20);

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Past events'))
            ->setSeo(true);

        return $this->view->render('events-archive', [
            'page' => $page,
            'events' => $events,
            'paginator' => $eventService->getPaginator(),
        ]);
    }

    /**
     * Renders the detail page of a single event
     *
     * @param string $id Event id
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
        $memberManager = $this->getModuleService('memberManager');
        $memberId = $memberManager->isLoggedIn() ? (int) $memberManager->getMember('id') : null;

        $analyticsService->recordView(
            (int) $id,
            $memberId,
            'card',
            $this->request->getClientIP(),
            $this->request->getUserAgent()
        );

        $page = new VirtualEntity();
        $page->setTitle($event->getTitle())
            ->setSeo(true);

        return $this->view->render('event', [
            'page' => $page,
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
        $memberManager = $this->getModuleService('memberManager');
        $memberId = $memberManager->isLoggedIn() ? (int) $memberManager->getMember('id') : null;

        $analyticsService->recordClick(
            (int) $id,
            $memberId,
            'card',
            $this->request->getClientIP(),
            $this->request->getUserAgent()
        );

        $this->response->redirect($event->getLink());
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
            'page' => $page,
            'events' => $events,
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
            'page' => $page,
            'event' => $event,
            'isNew' => true,
        ]);
    }

    /**
     * Persists a new event created by a member
     *
     * @return string JSON
     */
    public function storeAction()
    {
        $validator = $this->createEventValidator();

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors(),
            ]);
        }

        $memberManager = $this->getModuleService('memberManager');
        $memberId = (int) $memberManager->getMember('id');
        $eventService = $this->getModuleService('eventService');

        try {
            $eventService->createForUser($memberId, $this->request->getAll());
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
     * @param string $id Event id
     * @return string|bool
     */
    public function editAction($id)
    {
        $memberManager = $this->getModuleService('memberManager');
        $memberId = (int) $memberManager->getMember('id');
        $eventService = $this->getModuleService('eventService');
        $event = $eventService->fetchByIdForOwner((int) $id, $memberId, true);

        if ($event === false) {
            return false;
        }

        $page = new VirtualEntity();
        $page->setTitle($this->translator->translate('Edit event'))
            ->setSeo(false);

        return $this->view->render('member-event-form', [
            'page' => $page,
            'event' => $event,
            'isNew' => false,
        ]);
    }

    /**
     * Updates an existing event
     *
     * @param string $id Event id
     * @return string JSON
     */
    public function updateAction($id)
    {
        $validator = $this->createEventValidator();

        if (!$validator->isPassed()) {
            return $this->json([
                'errors' => $validator->getErrors(),
            ]);
        }

        $memberManager = $this->getModuleService('memberManager');
        $memberId = (int) $memberManager->getMember('id');
        $eventService = $this->getModuleService('eventService');

        try {
            $eventService->update((int) $id, $this->request->getAll(), $memberId, false);
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
     * @param string $id Event id
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
     * @param string $id Event id
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
            'page' => $page,
            'event' => $analytics['event'],
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
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'source' => $coords['source'],
        ]);
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