<?php

/**
 * This file is part of the Bono CMS
 *
 * For the full copyright and license information, please view
 * the license file that was distributed with this source code
 */

namespace Members\View\Widget;

use Krystal\Application\InputInterface;
use Krystal\InstanceManager\DependencyInjectionContainerInterface;
use Krystal\Widget\WidgetInterface;
use Members\Service\EventEntity;

final class EventsMapWidget implements WidgetInterface
{
    /**
     * Event entities collection
     *
     * @var array
     */
    private $events;

    /**
     * Default latitude when no events have coordinates
     *
     * @var float
     */
    private $defaultLatitude;

    /**
     * Default longitude when no events have coordinates
     *
     * @var float
     */
    private $defaultLongitude;

    /**
     * Default map zoom level
     *
     * @var int
     */
    private $defaultZoom;

    /**
     * Default map container height in pixels
     *
     * @var int
     */
    private $defaultHeight;

    /**
     * State initialization
     *
     * @param array $events Event entities collection
     * @param float $defaultLatitude Default latitude
     * @param float $defaultLongitude Default longitude
     * @param int $defaultZoom Default map zoom level
     * @param int $defaultHeight Default map container height
     * @return void
     */
    public function __construct(array $events, $defaultLatitude, $defaultLongitude, $defaultZoom = 10, $defaultHeight = 420)
    {
        $this->events = $events;
        $this->defaultLatitude = $defaultLatitude;
        $this->defaultLongitude = $defaultLongitude;
        $this->defaultZoom = $defaultZoom;
        $this->defaultHeight = $defaultHeight;
    }

    /**
     * Renders widget output as string for WidgetInterface compliance
     *
     * @param \Krystal\InstanceManager\DependencyInjectionContainerInterface $container
     * @param \Krystal\Application\InputInterface $input
     * @return string
     */
    public function render(DependencyInjectionContainerInterface $container, InputInterface $input)
    {
        $urlBuilder = $container->get('urlBuilder');
        $translator = $container->get('translator');
        $viewEventLabel = $translator->translate('View event');

        $markers = [];
        $centerLatitude = $this->defaultLatitude;
        $centerLongitude = $this->defaultLongitude;
        $hasCenter = false;

        /** @var EventEntity $event */
        foreach ($this->events as $event) {
            if (!$event->hasCoordinates()) {
                continue;
            }

            if (!$hasCenter) {
                $centerLatitude = $event->getLatitude();
                $centerLongitude = $event->getLongitude();
                $hasCenter = true;
            }

            $eventUrl = (string) $urlBuilder->build('Members:Event@viewAction', [$event->getId()]);

            $markers[] = [
                'lat' => $event->getLatitude(),
                'lng' => $event->getLongitude(),
                'description' => $this->buildPopupHtml($event, $eventUrl, $viewEventLabel),
                'popup' => false,
            ];
        }

        return [
            'driver' => 'osm',
            'lat' => $centerLatitude,
            'lng' => $centerLongitude,
            'zoom' => $this->defaultZoom,
            'height' => $this->defaultHeight,
            'clustering' => true,
            'markers' => $markers,
        ];
    }

    /**
     * Builds popup HTML for a single map marker
     *
     * @param \Members\Service\EventEntity $event
     * @param string $eventUrl
     * @param string $viewEventLabel
     * @return string
     */
    private function buildPopupHtml(EventEntity $event, $eventUrl, $viewEventLabel)
    {
        $locationHtml = '';

        if ($event->hasLocation()) {
            $locationName = $event->getLocationName();

            $locationHtml = <<<HTML
<div class="d-flex align-items-center gap-1 text-body-secondary">
    <i class="bi bi-geo-alt flex-shrink-0" aria-hidden="true"></i>
    <span class="text-truncate">{$locationName}</span>
</div>
HTML;
        }

        $title = $event->getTitle();
        $dateTime = trim($event->getFormattedDate() . ' ' . $event->getFormattedTime());

        return <<<HTML
<div class="d-flex flex-column gap-1 small">
    <div class="fw-semibold fs-6">{$title}</div>
    {$locationHtml}
    <div class="d-flex align-items-center gap-1 text-body-secondary">
        <i class="bi bi-calendar-event flex-shrink-0" aria-hidden="true"></i>
        <span>{$dateTime}</span>
    </div>
    <div class="mt-2">
        <a href="{$eventUrl}" class="fw-medium text-decoration-none">{$viewEventLabel}</a>
    </div>
</div>
HTML;
    }
}