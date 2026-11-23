
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
     * Calculate distance between two geographic points
     * Returns distance in kilometers
     */
    function calculateDistance(lat1, lon1, lat2, lon2) {
        var earthRadius = 6371;

        var dLat = (lat2 - lat1) * Math.PI / 180;
        var dLon = (lon2 - lon1) * Math.PI / 180;

        var a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(lat1 * Math.PI / 180) *
            Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);

        var c = 2 * Math.atan2(
            Math.sqrt(a),
            Math.sqrt(1 - a)
        );

        return earthRadius * c;
    }

    /**
     * Format distance
     */
    function formatDistance(distance) {
        if (distance < 1) {
            return Math.round(distance * 1000) + ' m';
        }

        return distance.toFixed(1) + ' km';
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

        this.userMarker = null;
        this.accuracyCircle = null;
        this.userCoordinates = null;

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

        this.addGeolocationControl();

        this.loadEvents();
    };

    /**
     * Load events from API
     */
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

    /**
     * Build event popup
     */
    EventMap.prototype.buildPopup = function (event) {
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

        if (this.userCoordinates) {
            var distance = calculateDistance(
                this.userCoordinates.latitude,
                this.userCoordinates.longitude,
                parseFloat(event.latitude),
                parseFloat(event.longitude)
            );

            popupContent += '<div class="mt-2 text-primary">';
            popupContent += '<i class="bi bi-signpost-2"></i> ';
            popupContent += '<strong>';
            popupContent += formatDistance(distance);
            popupContent += ' from you';
            popupContent += '</strong>';
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

        return popupContent;
    };

    /**
     * Render event markers
     */
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

            event.latitude = latitude;
            event.longitude = longitude;

            var marker = L.marker([latitude, longitude]);

            marker.bindPopup(self.buildPopup(event));

            self.markerCluster.addLayer(marker);

            self.markers.push({
                marker: marker,
                event: event
            });

            bounds.push([latitude, longitude]);
        });

        if (bounds.length > 0) {
            this.map.fitBounds(bounds, {
                padding: [40, 40],
                maxZoom: 12
            });
        }
    };

    /**
     * Update event popup distances
     */
    EventMap.prototype.updateDistances = function () {
        var self = this;

        this.markers.forEach(function (item) {
            item.marker.setPopupContent(
                self.buildPopup(item.event)
            );
        });
    };

    /**
     * =========================================================
     * USER GEOLOCATION
     * =========================================================
     */

    EventMap.prototype.addGeolocationControl = function () {
        var self = this;

        var control = L.control({
            position: 'topright'
        });

        control.onAdd = function () {
            var button = L.DomUtil.create(
                'button',
                'event-map-location-control'
            );

            button.type = 'button';
            button.title = 'My location';
            button.innerHTML = '<i class="bi bi-crosshair"></i>';

            button.style.width = '40px';
            button.style.height = '40px';
            button.style.background = '#fff';
            button.style.border = 'none';
            button.style.borderRadius = '4px';
            button.style.boxShadow = '0 1px 5px rgba(0,0,0,0.3)';
            button.style.cursor = 'pointer';
            button.style.fontSize = '18px';

            L.DomEvent.disableClickPropagation(button);

            L.DomEvent.on(button, 'click', function (e) {
                L.DomEvent.preventDefault(e);
                self.locateUser();
            });

            return button;
        };

        control.addTo(this.map);
    };

    /**
     * Find user location
     */
    EventMap.prototype.locateUser = function () {
        var self = this;

        if (!navigator.geolocation) {
            alert('Geolocation is not supported by your browser.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function (position) {
                var latitude = position.coords.latitude;
                var longitude = position.coords.longitude;
                var accuracy = position.coords.accuracy;

                var userLocation = [latitude, longitude];

                self.userCoordinates = {
                    latitude: latitude,
                    longitude: longitude
                };

                if (self.userMarker) {
                    self.map.removeLayer(self.userMarker);
                }

                if (self.accuracyCircle) {
                    self.map.removeLayer(self.accuracyCircle);
                }

                self.userMarker = L.marker(userLocation)
                    .addTo(self.map)
                    .bindPopup('<strong>Your location</strong>')
                    .openPopup();

                self.accuracyCircle = L.circle(userLocation, {
                    radius: accuracy,
                    color: '#3388ff',
                    fillColor: '#3388ff',
                    fillOpacity: 0.12,
                    weight: 1
                }).addTo(self.map);

                self.map.setView(userLocation, 15);

                self.updateDistances();
            },
            function (error) {
                var message = 'Unable to determine your location.';

                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        message = 'You denied access to your location.';
                        break;

                    case error.POSITION_UNAVAILABLE:
                        message = 'Your location is currently unavailable.';
                        break;

                    case error.TIMEOUT:
                        message = 'Location request timed out.';
                        break;
                }

                alert(message);
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    };

    /**
     * Destroy catalog map
     */
    EventMap.prototype.destroy = function () {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }

        this.markerCluster = null;
        this.markers = [];
        this.userMarker = null;
        this.accuracyCircle = null;
        this.userCoordinates = null;

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

    /**
     * Destroy single event map
     */
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
