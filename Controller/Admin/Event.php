<?php
/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */

namespace Members\Controller\Admin;

use Cms\Controller\Admin\AbstractController;
use Krystal\Stdlib\VirtualEntity;

/**
 * Events created here are always published: they have no author and skip moderation.
 */
final class Event extends AbstractController
{
    /**
     * Renders the admin grid of all events
     *
     * @return string|bool
     */
    public function indexAction()
    {
        $pageNumber = (int) $this->request->getQuery('page', 1);
        $statusFilter = $this->request->getQuery('status', '');

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Events');

        $eventService = $this->getModuleService('eventService');

        $filters = [];

        if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
            $filters['status'] = $statusFilter;
        }

        $events = $eventService->fetchAllForAdmin($pageNumber, $this->getSharedPerPageCount(), $filters);

        return $this->view->render('events', [
            'events'          => $events,
            'paginator'       => $eventService->getPaginator(),
            'statusFilter'    => $statusFilter,
            'statusBreakdown' => $this->getModuleService('eventService')->fetchStatusBreakdown(),
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
        $event->setStatus('approved');

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Events', 'Members:Admin:Event@indexAction')
                   ->addOne('Create event');

        return $this->view->render('event-form', [
            'event' => $event,
            'isNew' => true
        ]);
    }

    /**
     * Persists a new event created by an admin
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

        $eventService = $this->getModuleService('eventService');
        $data = $this->prepareEventData($this->request->getPost('event'));

        try {
            $eventId = $eventService->createByAdmin($data);

            $this->flashBag->set('success', $this->translator->translate('The event has been created successfully'));

            return $this->json([
                'redirect' => $this->createUrl('Members:Admin:Event@editAction', [$eventId]),
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
        $eventService = $this->getModuleService('eventService');
        $event = $eventService->findById((int) $id);

        if ($event === false) {
            return false;
        }

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Events', 'Members:Admin:Event@indexAction')
                   ->addOne('Edit event');

        return $this->view->render('event-form', [
            'event' => $event,
            'isNew' => false
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

        $eventService = $this->getModuleService('eventService');
        $data = $this->prepareEventData($this->request->getPost('event'));

        try {
            $eventService->update((int) $id, $data, 0, true);

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
     * Approves a pending event
     *
     * @param  string $id Event id
     * @return string
     */
    public function approveAction($id)
    {
        $eventService = $this->getModuleService('eventService');

        try {
            if ($eventService->approve((int) $id)) {
                $this->flashBag->set('success', $this->translator->translate('The event has been approved'));
            } else {
                $this->flashBag->set('error', $this->translator->translate('Could not approve the event'));
            }
        } catch (\InvalidArgumentException $exception) {
            $this->flashBag->set('error', $exception->getMessage());
        }

        $this->response->redirect($this->createUrl('Members:Admin:Event@indexAction'));
    }

    /**
     * Rejects a pending event
     *
     * @param  string $id Event id
     * @return string
     */
    public function rejectAction($id)
    {
        $eventService = $this->getModuleService('eventService');
        $reason = $this->request->getPost('reason');

        try {
            if ($eventService->reject((int) $id, $reason)) {
                $this->flashBag->set('success', $this->translator->translate('The event has been rejected'));
            } else {
                $this->flashBag->set('error', $this->translator->translate('Could not reject the event'));
            }
        } catch (\InvalidArgumentException $exception) {
            $this->flashBag->set('error', $exception->getMessage());
        }

        $this->response->redirect($this->createUrl('Members:Admin:Event@indexAction'));
    }

    /**
     * Deletes an event
     *
     * @param  string $id Event id
     * @return string JSON
     */
    public function deleteAction($id)
    {
        $eventService = $this->getModuleService('eventService');

        try {
            $eventService->delete((int) $id, 0, true);

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
     * Renders the global analytics dashboard
     *
     * @return string
     */
    public function dashboardAction()
    {
        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Events', 'Members:Admin:Event@indexAction')
                   ->addOne('Analytics');

        $analyticsService = $this->getModuleService('eventAnalyticsService');
        $dashboard = $analyticsService->fetchAdminDashboard();

        return $this->view->render('event-analytics-dashboard', [
            'dashboard' => $dashboard,
        ]);
    }

    /**
     * Renders detailed analytics for a single event
     *
     * @param string $id Event id
     * @return string|bool
     */
    public function analyticsAction($id)
    {
        $eventService = $this->getModuleService('eventService');
        $event = $eventService->findById((int) $id);

        // Trigger 404, if invalid $id
        if ($event === false) {
            return false;
        }

        $this->view->getBreadcrumbBag()
                   ->addOne('Members', 'Members:Admin:Members@indexAction')
                   ->addOne('Events', 'Members:Admin:Event@indexAction')
                   ->addOne('Analytics');

        $analyticsService = $this->getModuleService('eventAnalyticsService');
        $analytics = $analyticsService->fetchForAdminEvent((int) $id);

        if ($analytics === null) {
            return false;
        }

        return $this->view->render('event-analytics', [
            'event' => $event,
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