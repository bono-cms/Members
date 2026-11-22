/**
* Members event form — map URL parser.
*
* Auto-initializes on DOM ready against any form carrying the
* `data-event-form` attribute. To skip auto-init, set
* window.EventForm.autoInit = false before this file loads.
*
* The form must carry the following data-* attributes:
*   - data-parse-url     Endpoint that accepts a map URL and returns {lat, lng, source}
*
* Optional data-* attributes:
*   - data-url-input     Selector for the map URL input (default: [data-role="map-url"])
*   - data-lat-input     Selector for the latitude input (default: [data-role="latitude"])
*   - data-lng-input     Selector for the longitude input (default: [data-role="longitude"])
*   - data-source-input  Selector for the source select (default: [data-role="location-source"])
*   - data-parse-button  Selector for the parse button (default: [data-action="parse-location"])
*   - data-csrf-meta     Selector for the CSRF meta tag (default: meta[name="csrf-token"])
*   - data-translations  JSON-encoded object with translation keys
*
* @see EventForm
*/
(function (window, document, $) {
'use strict';

var DEFAULT_SELECTORS = {
    urlInput: '[data-role="map-url"]',
    latInput: '[data-role="latitude"]',
    lngInput: '[data-role="longitude"]',
    sourceInput: '[data-role="location-source"]',
    parseButton: '[data-action="parse-location"]',
    csrfMeta: 'meta[name="csrf-token"]',
    form: '[data-event-form]'
};

var DEFAULT_TRANSLATIONS = {
    extracting: 'Extracting…',
    extract: 'Extract',
    couldNotParse: 'Could not parse the map URL'
};

/**
* Resolves a jQuery object, DOM element, or selector string
* into a jQuery object.
*
* @param {jQuery|Element|string|null|undefined} source
* @return {jQuery}
*/
function resolve(source) {
    if (!source) {
        return $();
    }
    if (source instanceof $) {
        return source;
    }
    if (source.nodeType) {
        return $(source);
    }
    if (typeof source === 'string') {
        return $(source);
    }
    return $();
}

/**
* Creates an event form instance bound to a form element.
*
* @constructor
* @param {Object} options
* @param {jQuery|Element|string} options.form Form element or selector
* @param {Object} [options.selectors] Overrides for DEFAULT_SELECTORS
* @param {Object} [options.translations] Overrides for DEFAULT_TRANSLATIONS
* @param {boolean} [options.debug] Enable console logging
*/
function EventForm(options) {
    options = options || {};
    this.selectors = $.extend({}, DEFAULT_SELECTORS, options.selectors || {});
    this.translations = $.extend({}, DEFAULT_TRANSLATIONS, options.translations || {});
    this.debug = options.debug === true;

    this.$form = resolve(options.form || this.selectors.form);
    if (!this.$form.length) {
        throw new Error('EventForm: form element not found');
    }

    this.parseUrl = this.$form.data('parse-url');
    if (!this.parseUrl) {
        throw new Error('EventForm: data-parse-url attribute is required');
    }

    this.$urlInput = this.$form.find(this.resolveSelector('urlInput'));
    this.$latInput = this.$form.find(this.resolveSelector('latInput'));
    this.$lngInput = this.$form.find(this.resolveSelector('lngInput'));
    this.$sourceInput = this.$form.find(this.resolveSelector('sourceInput'));
    this.$parseButton = this.$form.find(this.resolveSelector('parseButton'));

    this.isParsing = false;
    this.originalButtonHtml = this.$parseButton.html();

    this.attachEvents();
}

/**
* Resolves a selector, allowing per-form overrides via data-* attributes.
*
* @param {string} key Selector key from DEFAULT_SELECTORS
* @return {string}
*/
EventForm.prototype.resolveSelector = function (key) {
    var override = this.$form.data(key.replace(/([A-Z])/g, '-$1').toLowerCase());
    return override || this.selectors[key];
};

/**
* Wires the parse button click handler.
*
* @return {void}
*/
EventForm.prototype.attachEvents = function () {
    var self = this;
    this.$parseButton.on('click', function (event) {
        event.preventDefault();
        self.parse();
    });
};

/**
* Reads the CSRF token from the page's meta tag.
*
* @return {string|null}
*/
EventForm.prototype.getCsrfToken = function () {
    var selector = this.$form.data('csrf-meta') || this.selectors.csrfMeta;
    var $meta = $(selector);
    return $meta.length ? $meta.attr('content') : null;
};

/**
* Looks up a translated string from the form's data-translations attribute.
*
* @param {string} key
* @param {string} fallback
* @return {string}
*/
EventForm.prototype.translate = function (key, fallback) {
    var map = this.$form.data('translations');
    if (map) {
        if (typeof map === 'string') {
            try {
                map = JSON.parse(map);
            } catch (e) {
                map = null;
            }
        }
        if (map && map[key]) {
            return map[key];
        }
    }
    if (this.translations[key]) {
        return this.translations[key];
    }
    return fallback;
};

/**
* Sends the map URL to the server for parsing and populates the
* latitude, longitude and source fields on success.
*
* @return {void}
*/
EventForm.prototype.parse = function () {
    if (this.isParsing) {
        return;
    }

    var url = $.trim(this.$urlInput.val());
    if (!url) {
        return;
    }

    var self = this;
    var token = this.getCsrfToken();
    var payload = { url: url };
    if (token) {
        payload['csrf-token'] = token;
    }

    this.isParsing = true;
    this.$parseButton.prop('disabled', true);
    this.$parseButton.html('<i class="bi bi-arrow-clockwise spin"></i> ' + this.translate('extracting', 'Extracting…'));

    this.log('parsing', url);

    $.ajax({
        url: this.parseUrl,
        method: 'POST',
        dataType: 'json',
        data: payload,
        xhrFields: {
            withCredentials: true
        }
    })
    .done(function (data) {
        if (data && data.error) {
            alert(data.error);
            return;
        }
        if (data && typeof data.lat !== 'undefined' && typeof data.lng !== 'undefined') {
            self.$latInput.val(data.lat);
            self.$lngInput.val(data.lng);
            if (data.source) {
                self.$sourceInput.val(data.source);
            }
        }
    })
    .fail(function (xhr, status, error) {
        self.log('parse failed', status, error);
        alert(self.translate('couldNotParse', 'Could not parse the map URL'));
    })
    .always(function () {
        self.isParsing = false;
        self.$parseButton.prop('disabled', false);
        self.$parseButton.html(self.originalButtonHtml);
    });
};

/**
* Writes a debug message to the console when `debug` is on.
*
* @return {void}
*/
EventForm.prototype.log = function () {
    if (this.debug && window.console && console.log) {
        console.log.apply(console, arguments);
    }
};

EventForm.DEFAULT_SELECTORS = DEFAULT_SELECTORS;
EventForm.DEFAULT_TRANSLATIONS = DEFAULT_TRANSLATIONS;
EventForm.autoInit = true;
window.EventForm = EventForm;

$(function () {
    if (!EventForm.autoInit) {
        return;
    }
    var $forms = $(DEFAULT_SELECTORS.form);
    if (!$forms.length) {
        return;
    }
    $forms.each(function () {
        var $form = $(this);
        if ($form.data('event-form-initialized')) {
            return;
        }
        $form.data('event-form-initialized', true);
        new EventForm({ form: $form });
    });
});
})(window, document, jQuery);