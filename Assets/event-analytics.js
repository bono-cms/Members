/**
* Members event analytics — Chart.js renderer.
*
* Requires Chart.js 4.x. Auto-initializes on DOM ready against
* any element carrying the `data-event-analytics` attribute.
* To skip auto-init, set window.EventAnalytics.autoInit = false
* before this file loads.
*
* The container must carry the following data-* attributes:
*   - data-daily-stats   JSON-encoded array of {date, views, clicks}
*
* Optional data-* attributes:
*   - data-source-stats  JSON-encoded array of {source, views, clicks}
*   - data-daily-chart   Selector for the daily chart canvas (default: [data-role="daily-chart"])
*   - data-source-chart  Selector for the source chart canvas (default: [data-role="source-chart"])
*   - data-translations  JSON-encoded object with translation keys
*
* @see EventAnalytics
*/
(function (window, document, $) {
'use strict';

var DEFAULT_SELECTORS = {
    container: '[data-event-analytics]',
    dailyChart: '[data-role="daily-chart"]',
    sourceChart: '[data-role="source-chart"]'
};

var DEFAULT_TRANSLATIONS = {
    views: 'Views',
    clicks: 'Clicks'
};

var DEFAULT_COLORS = {
    viewsBorder: '#0d6efd',
    viewsBackground: 'rgba(13, 110, 253, 0.1)',
    clicksBorder: '#198754',
    clicksBackground: 'rgba(25, 135, 84, 0.1)',
    sourcePalette: ['#0d6efd', '#198754', '#ffc107', '#dc3545']
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
* Safely parses a JSON string, returning null on failure.
*
* @param {string|null|undefined} value
* @return {*}
*/
function parseJson(value) {
    if (!value) {
        return null;
    }
    if (typeof value === 'object') {
        return value;
    }
    try {
        return JSON.parse(value);
    } catch (e) {
        return null;
    }
}

/**
* Creates an analytics instance bound to a container element.
*
* @constructor
* @param {Object} options
* @param {jQuery|Element|string} options.container Container element or selector
* @param {Object} [options.selectors] Overrides for DEFAULT_SELECTORS
* @param {Object} [options.translations] Overrides for DEFAULT_TRANSLATIONS
* @param {Object} [options.colors] Overrides for DEFAULT_COLORS
* @param {boolean} [options.debug] Enable console logging
*/
function EventAnalytics(options) {
    options = options || {};
    this.selectors = $.extend({}, DEFAULT_SELECTORS, options.selectors || {});
    this.translations = $.extend({}, DEFAULT_TRANSLATIONS, options.translations || {});
    this.colors = $.extend({}, DEFAULT_COLORS, options.colors || {});
    this.debug = options.debug === true;

    this.$container = resolve(options.container || this.selectors.container);
    if (!this.$container.length) {
        throw new Error('EventAnalytics: container element not found');
    }

    this.dailyStats = parseJson(this.$container.attr('data-daily-stats')) || [];
    this.sourceStats = parseJson(this.$container.attr('data-source-stats')) || [];

    this.$dailyChart = this.$container.find(this.selectors.dailyChart);
    this.$sourceChart = this.$container.find(this.selectors.sourceChart);

    this.charts = [];

    this.render();
}

/**
* Looks up a translated string from the container's data-translations attribute.
*
* @param {string} key
* @param {string} fallback
* @return {string}
*/
EventAnalytics.prototype.translate = function (key, fallback) {
    var map = parseJson(this.$container.attr('data-translations'));
    if (map && map[key]) {
        return map[key];
    }
    if (this.translations[key]) {
        return this.translations[key];
    }
    return fallback;
};

/**
* Renders all charts found in the container.
*
* @return {void}
*/
EventAnalytics.prototype.render = function () {
    if (typeof Chart === 'undefined') {
        this.log('Chart.js is not loaded, skipping render');
        return;
    }
    if (this.$dailyChart.length && this.dailyStats.length) {
        this.renderDailyChart();
    }
    if (this.$sourceChart.length && this.sourceStats.length) {
        this.renderSourceChart();
    }
};

/**
* Renders the daily views/clicks line chart.
*
* @return {void}
*/
EventAnalytics.prototype.renderDailyChart = function () {
    var labels = [];
    var views = [];
    var clicks = [];

    for (var i = 0; i < this.dailyStats.length; i++) {
        var row = this.dailyStats[i];
        labels.push(row.date);
        views.push(row.views);
        clicks.push(row.clicks);
    }

    var chart = new Chart(this.$dailyChart[0].getContext('2d'), {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: this.translate('views', 'Views'),
                    data: views,
                    borderColor: this.colors.viewsBorder,
                    backgroundColor: this.colors.viewsBackground,
                    fill: true,
                    tension: 0.3
                },
                {
                    label: this.translate('clicks', 'Clicks'),
                    data: clicks,
                    borderColor: this.colors.clicksBorder,
                    backgroundColor: this.colors.clicksBackground,
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                }
            }
        }
    });

    this.charts.push(chart);
};

/**
* Renders the traffic sources pie chart.
*
* @return {void}
*/
EventAnalytics.prototype.renderSourceChart = function () {
    var labels = [];
    var data = [];
    var palette = this.colors.sourcePalette;

    for (var i = 0; i < this.sourceStats.length; i++) {
        var row = this.sourceStats[i];
        labels.push(row.source);
        data.push(row.views + row.clicks);
    }

    var chart = new Chart(this.$sourceChart[0].getContext('2d'), {
        type: 'pie',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: palette.slice(0, data.length)
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    this.charts.push(chart);
};

/**
* Destroys all rendered charts and releases canvas references.
*
* @return {void}
*/
EventAnalytics.prototype.destroy = function () {
    for (var i = 0; i < this.charts.length; i++) {
        this.charts[i].destroy();
    }
    this.charts = [];
};

/**
* Writes a debug message to the console when `debug` is on.
*
* @return {void}
*/
EventAnalytics.prototype.log = function () {
    if (this.debug && window.console && console.log) {
        console.log.apply(console, arguments);
    }
};

EventAnalytics.DEFAULT_SELECTORS = DEFAULT_SELECTORS;
EventAnalytics.DEFAULT_TRANSLATIONS = DEFAULT_TRANSLATIONS;
EventAnalytics.DEFAULT_COLORS = DEFAULT_COLORS;
EventAnalytics.autoInit = true;
window.EventAnalytics = EventAnalytics;

$(function () {
    if (!EventAnalytics.autoInit) {
        return;
    }
    var $containers = $(DEFAULT_SELECTORS.container);
    if (!$containers.length) {
        return;
    }
    $containers.each(function () {
        var $container = $(this);
        if ($container.data('event-analytics-initialized')) {
            return;
        }
        $container.data('event-analytics-initialized', true);
        new EventAnalytics({ container: $container });
    });
});
})(window, document, jQuery);