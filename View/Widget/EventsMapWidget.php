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

/**
 * Builds map configuration for the catalog page.
 *
 * Accepts raw map point rows from EventMapper::fetchMapPoints()
 * so the map can show every non-expired event with coordinates,
 * regardless of which page the user is viewing in the card grid.
 */
final class EventsMapWidget implements WidgetInterface
{
    /**
     * Map points (raw rows from fetchMapPoints)
     *
     * @var array
     */
    private $mapPoints;

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
     * @param array $mapPoints Raw map point rows
     * @param float $defaultLatitude Default latitude
     * @param float $defaultLongitude Default longitude
     * @param int $defaultZoom Default map zoom level
     * @param int $defaultHeight Default map container height
     * @return void
     */
    public function __construct(
        array $mapPoints,
        $defaultLatitude,
        $defaultLongitude,
        $defaultZoom = 10,
        $defaultHeight = 420
    ) {
        $this->mapPoints = $mapPoints;
        $this->defaultLatitude = (float) $defaultLatitude;
        $this->defaultLongitude = (float) $defaultLongitude;
        $this->defaultZoom = (int) $defaultZoom;
        $this->defaultHeight = (int) $defaultHeight;
    }

    /**
     * Builds map configuration array for the catalog page
     *
     * @param \Krystal\InstanceManager\DependencyInjectionContainerInterface $container
     * @param \Krystal\Application\InputInterface $input
     * @return array
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

        foreach ($this->mapPoints as $point) {
            if (empty($point['latitude']) || empty($point['longitude'])) {
                continue;
            }

            if (!$hasCenter) {
                $centerLatitude = (float) $point['latitude'];
                $centerLongitude = (float) $point['longitude'];
                $hasCenter = true;
            }

            $eventUrl = (string) $urlBuilder->build('Members:Event@viewAction', [$point['id']]);

            $markers[] = [
                'lat' => (float) $point['latitude'],
                'lng' => (float) $point['longitude'],
                'description' => $this->buildPopupHtml($point, $eventUrl, $viewEventLabel),
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
     * @param array $point Raw map point row
     * @param string $eventUrl
     * @param string $viewEventLabel
     * @return string
     */
    private function buildPopupHtml(array $point, $eventUrl, $viewEventLabel)
    {
        $locationHtml = '';
        if (!empty($point['location_name'])) {
            $locationName = htmlspecialchars((string) $point['location_name'], ENT_QUOTES, 'UTF-8');
            $locationHtml = <<<HTML
<div class="d-flex align-items-center gap-1 text-body-secondary">
<i class="bi bi-geo-alt flex-shrink-0" aria-hidden="true"></i>
<span class="text-truncate">{$locationName}</span>
</div>
HTML;
        }

        $title = htmlspecialchars((string) (isset($point['title']) ? $point['title'] : ''), ENT_QUOTES, 'UTF-8');

        $dateTime = '';
        if (!empty($point['starts_at'])) {
            $timestamp = strtotime($point['starts_at']);
            if ($timestamp !== false) {
                $date = date('M j, Y', $timestamp);
                $time = date('H:i', $timestamp);
                // Hide time if it's midnight (matches EventEntity logic)
                if ($time === '00:00') {
                    $dateTime = $date;
                } else {
                    $dateTime = $date . ' ' . $time;
                }
            }
        }
        $dateTime = htmlspecialchars($dateTime, ENT_QUOTES, 'UTF-8');

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