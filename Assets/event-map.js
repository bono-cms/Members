
/**
 * Members Event Maps
 *
 * Requires:
 * - Leaflet 1.9.4
 * - Leaflet.markercluster 1.5.3 (catalog map only)
 */
(function (window, document, $) {
    'use strict';

    var CATALOG_SELECTOR = '[data-event-map]';
    var SINGLE_SELECTOR = '[data-event-location-map]';
    var API_URL = '/api/v1/members/events/render/map';

    var DEFAULT_CENTER = [41.2995, 69.2401];
    var DEFAULT_ZOOM = 6;

    /**
     * Escape HTML
     */
    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Create OpenStreetMap layer
     */
    function addTileLayer(map) {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        map.attributionControl.setPrefix('');
    }

    /**
     * =========================================================
     * CATALOG MAP
     * =========================================================
     */

    function EventMap(options) {
        this.container = options.container;
        this.$container = $(this.container);

        this.map = null;
        this.markerCluster = null;
        this.markers = [];

        this.init();
    }

    EventMap.prototype.init = function () {
        if (!this.container) {
            return;
        }

        if (typeof window.L === 'undefined') {
            console.error('EventMap: Leaflet is not loaded.');
            return;
        }

        if (typeof L.markerClusterGroup !== 'function') {
            console.error('EventMap: MarkerCluster plugin is not loaded.');
            return;
        }

        if (this.$container.data('event-map-initialized')) {
            return;
        }

        this.$container.data('event-map-initialized', true);

        this.map = L.map(this.container).setView(
            DEFAULT_CENTER,
            DEFAULT_ZOOM
        );

        addTileLayer(this.map);

        this.markerCluster = L.markerClusterGroup({
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: true,
            zoomToBoundsOnClick: true,
            maxClusterRadius: 80
        });

        this.markerCluster.addTo(this.map);

        this.loadEvents();
    };

    EventMap.prototype.loadEvents = function () {
        var self = this;

        fetch(API_URL)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP error: ' + response.status);
                }

                return response.json();
            })
            .then(function (response) {
                var events = response.data || [];

                self.renderMarkers(events);
            })
            .catch(function (error) {
                console.error(
                    'EventMap: Failed to load events.',
                    error
                );
            });
    };

    EventMap.prototype.renderMarkers = function (events) {
        var self = this;
        var bounds = [];

        if (!Array.isArray(events)) {
            return;
        }

        events.forEach(function (event) {
            var latitude = parseFloat(event.latitude);
            var longitude = parseFloat(event.longitude);

            if (
                !Number.isFinite(latitude) ||
                !Number.isFinite(longitude) ||
                latitude < -90 ||
                latitude > 90 ||
                longitude < -180 ||
                longitude > 180
            ) {
                return;
            }

            var marker = L.marker([latitude, longitude]);

            var title = escapeHtml(event.title);
            var location = escapeHtml(event.location_name);
            var date = escapeHtml(event.starts_at);
            var url = escapeHtml(event.url);

            var popupContent = '<div class="event-map-popup">';
            popupContent += '<strong>' + title + '</strong>';

            if (location) {
                popupContent += '<div class="mt-1">';
                popupContent += '<i class="bi bi-geo-alt"></i> ';
                popupContent += location;
                popupContent += '</div>';
            }

            if (date) {
                popupContent += '<div class="mt-1">';
                popupContent += '<i class="bi bi-calendar-event"></i> ';
                popupContent += date;
                popupContent += '</div>';
            }

            if (url) {
                popupContent += '<div class="mt-2">';
                popupContent += '<a href="' + url + '">';
                popupContent += 'View event';
                popupContent += '</a>';
                popupContent += '</div>';
            }

            popupContent += '</div>';

            marker.bindPopup(popupContent);

            self.markerCluster.addLayer(marker);
            self.markers.push(marker);

            bounds.push([latitude, longitude]);
        });

        if (bounds.length > 0) {
            this.map.fitBounds(bounds, {
                padding: [40, 40],
                maxZoom: 12
            });
        }
    };

    EventMap.prototype.destroy = function () {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }

        this.markerCluster = null;
        this.markers = [];

        this.$container.removeData('event-map-initialized');
    };

    /**
     * =========================================================
     * SINGLE EVENT MAP
     * =========================================================
     */

    function SingleEventMap(options) {
        this.container = options.container;
        this.$container = $(this.container);

        this.map = null;
        this.marker = null;

        this.init();
    }

    SingleEventMap.prototype.init = function () {
        if (!this.container) {
            return;
        }

        if (typeof window.L === 'undefined') {
            console.error('SingleEventMap: Leaflet is not loaded.');
            return;
        }

        if (this.$container.data('event-location-map-initialized')) {
            return;
        }

        var latitude = parseFloat(
            this.container.getAttribute('data-latitude')
        );

        var longitude = parseFloat(
            this.container.getAttribute('data-longitude')
        );

        var locationName =
            this.container.getAttribute('data-location-name') || '';

        if (
            !Number.isFinite(latitude) ||
            !Number.isFinite(longitude) ||
            latitude < -90 ||
            latitude > 90 ||
            longitude < -180 ||
            longitude > 180
        ) {
            console.error(
                'SingleEventMap: Invalid event coordinates.',
                latitude,
                longitude
            );
            return;
        }

        this.$container.data(
            'event-location-map-initialized',
            true
        );

        this.map = L.map(this.container).setView(
            [latitude, longitude],
            15
        );

        addTileLayer(this.map);

        this.marker = L.marker([latitude, longitude]).addTo(this.map);

        var popupContent = '<div class="event-location-popup">';

        if (locationName) {
            popupContent += '<strong>';
            popupContent += escapeHtml(locationName);
            popupContent += '</strong>';
        } else {
            popupContent += '<strong>Event location</strong>';
        }

        popupContent += '</div>';

        this.marker.bindPopup(popupContent);

        this.marker.openPopup();

        /**
         * Google Maps directions URL
         */
        var directionsUrl =
            'https://www.google.com/maps/dir/?api=1&destination=' +
            encodeURIComponent(latitude + ',' + longitude);

        var directionsButton = document.createElement('a');

        directionsButton.href = directionsUrl;
        directionsButton.target = '_blank';
        directionsButton.rel = 'noopener noreferrer';
        directionsButton.className = 'btn btn-primary btn-sm mt-3';
        directionsButton.innerHTML =
            '<i class="bi bi-signpost-2"></i> Get directions';

        this.$container.after(directionsButton);
    };

    SingleEventMap.prototype.destroy = function () {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }

        this.marker = null;

        this.$container.removeData(
            'event-location-map-initialized'
        );
    };

    /**
     * =========================================================
     * AUTO INITIALIZATION
     * =========================================================
     */

    EventMap.autoInit = true;
    SingleEventMap.autoInit = true;

    window.EventMap = EventMap;
    window.SingleEventMap = SingleEventMap;

    $(function () {
        /**
         * Catalog map
         */
        if (EventMap.autoInit) {
            $(CATALOG_SELECTOR).each(function () {
                if ($(this).data('event-map-initialized')) {
                    return;
                }

                new EventMap({
                    container: this
                });
            });
        }

        /**
         * Single event map
         */
        if (SingleEventMap.autoInit) {
            $(SINGLE_SELECTOR).each(function () {
                if (
                    $(this).data(
                        'event-location-map-initialized'
                    )
                ) {
                    return;
                }

                new SingleEventMap({
                    container: this
                });
            });
        }
    });

})(window, document, jQuery);
