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

final class EventLocationMapWidget implements WidgetInterface
{
    /**
     * Event entity instance
     *
     * @var \Members\Service\EventEntity
     */
    private $event;

    /**
     * Map zoom level
     *
     * @var int
     */
    private $zoom;

    /**
     * Map container height in pixels
     *
     * @var int
     */
    private $height;

    /**
     * State initialization
     *
     * @param \Members\Service\EventEntity $event Event entity instance
     * @param int $zoom Map zoom level
     * @param int $height Map container height in pixels
     * @return void
     */
    public function __construct(EventEntity $event, $zoom = 15, $height = 350)
    {
        $this->event = $event;
        $this->zoom = $zoom;
        $this->height = $height;
    }

    /**
     * Builds map configuration array for the event location
     *
     * @param \Krystal\InstanceManager\DependencyInjectionContainerInterface $container
     * @param \Krystal\Application\InputInterface $input
     * @return array
     */
    public function render(DependencyInjectionContainerInterface $container, InputInterface $input)
    {
        if (!$this->event->hasCoordinates()) {
            return [];
        }

        $latitude = $this->event->getLatitude();
        $longitude = $this->event->getLongitude();
        $locationName = (string) $this->event->getLocationName();

        return [
            'driver' => 'osm',
            'lat' => $latitude,
            'lng' => $longitude,
            'zoom' => $this->zoom,
            'height' => $this->height,
            'markers' => [
                [
                    'lat' => $latitude,
                    'lng' => $longitude,
                    'description' => '<strong>' . $locationName . '</strong>',
                    'popup' => true,
                ]
            ]
        ];
    }
}