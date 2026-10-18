/**
 * Members chat — adaptive polling and message rendering.
 *
 * Requires jQuery 3.x. Auto-initializes on DOM ready against
 * Chat.DEFAULT_SELECTORS.form. To skip auto-init, set
 * window.Chat.autoInit = false before this file loads.
 *
 * @see Chat
 */
(function (window, document, $) {
    'use strict';

    /**
     * Default DOM and class selectors used to build and query
     * the message list.
     *
     * Each key is overridable via `options.selectors` when
     * constructing a Chat instance.
     *
     * @type {Object}
     */
    var DEFAULT_SELECTORS = {
        thread: '#thread',
        form: '#chat-form',
        body: '[name="body"]',
        csrfMeta: 'meta[name="csrf-token"]',

        messageRow: 'mb-3',
        messageRowMine: 'text-end',
        messageRowOther: 'text-start',

        bubble: 'd-inline-block p-2 rounded',
        bubbleMine: 'bg-primary text-white',
        bubbleOther: 'bg-body-secondary',
        bubbleDeleted: 'opacity-75',

        meta: 'small text-muted mt-1'
    };

    /**
     * Default inline styles applied to each message bubble.
     *
     * @type {Object}
     */
    var DEFAULT_STYLES = {
        bubbleMaxWidth: '70%',
        bubbleWhitespace: 'pre-wrap'
    };

    /**
     * Polling intervals, in milliseconds, keyed by state.
     *
     * @type {Object}
     */
    var DEFAULT_INTERVALS = {
        burst: 2000,
        active: 10000,
        idle: 30000,
        idleAfter: 60000,
        error: 60000
    };

    /**
     * Default request timeout for polls, in milliseconds.
     *
     * @type {number}
     */
    var DEFAULT_TIMEOUT = 20000;

    /**
     * Resolves a jQuery object, DOM element, or selector string
     * into a jQuery object. Returns an empty jQuery object when
     * nothing matched, so callers can check `.length`.
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
     * Creates a chat instance bound to a form element.
     *
     * The form must carry data-conversation, data-send-url,
     * data-poll-url, data-me-id, and (for existing threads)
     * data-last-id attributes. These are consumed from the form
     * on construction.
     *
     * @constructor
     * @param {Object} options
     * @param {jQuery|Element|string} options.form Form element or selector
     * @param {jQuery|Element|string} [options.thread] Thread container; defaults to selectors.thread
     * @param {Object} [options.selectors] Overrides for DEFAULT_SELECTORS
     * @param {Object} [options.styles] Overrides for DEFAULT_STYLES
     * @param {Object} [options.intervals] Overrides for DEFAULT_INTERVALS
     * @param {number} [options.timeout] Poll timeout in milliseconds
     * @param {boolean} [options.debug] Enable console logging
     */
    function Chat(options) {
        options = options || {};

        this.selectors = $.extend({}, DEFAULT_SELECTORS, options.selectors || {});
        this.styles = $.extend({}, DEFAULT_STYLES, options.styles || {});
        this.intervals = $.extend({}, DEFAULT_INTERVALS, options.intervals || {});
        this.timeout = options.timeout || DEFAULT_TIMEOUT;
        this.debug = options.debug === true;

        this.$form = resolve(options.form || this.selectors.form);

        if (!this.$form.length) {
            throw new Error('Chat: form element not found');
        }

        this.$thread = resolve(options.thread || this.selectors.thread);
        this.$body = this.$form.find(this.selectors.body);

        var data = this.$form.data();

        this.conversationId = data.conversation;
        this.sendUrl = data.sendUrl;
        this.pollUrl = data.pollUrl;
        this.meId = parseInt(data.meId, 10) || 0;
        this.lastId = parseInt(data.lastId, 10) || 0;

        this.timer = null;
        this.xhr = null;
        this.idleSince = Date.now();
        this.burst = 0;
        this.failureBackoff = 0;

        this.boundVisibility = this.onVisibilityChange.bind(this);

        this.attachEvents();
    }

    /**
     * Wires the submit, keydown, and visibility event handlers
     * and kicks off the first scheduled poll.
     *
     * @return {void}
     */
    Chat.prototype.attachEvents = function () {
        var self = this;

        this.$form.on('submit', function (event) {
            event.preventDefault();
            self.send();
        });

        this.$body.on('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                self.$form.trigger('submit');
            }
        });

        $(document).on('visibilitychange', this.boundVisibility);

        if (this.$thread.length) {
            this.$thread.scrollTop(this.$thread[0].scrollHeight);
        }

        this.schedule();
    };

    /**
     * Pauses polling when the tab is hidden and resumes it with
     * an immediate catch-up poll when the tab becomes visible.
     *
     * Hiding the tab also cancels any pending setTimeout, so
     * a hidden tab generates zero requests.
     *
     * @return {void}
     */
    Chat.prototype.onVisibilityChange = function () {
        if (document.hidden) {
            this.cancel();
        } else {
            this.idleSince = Date.now();
            this.poll();
        }
    };

    /**
     * Submits the current textarea contents to the server.
     *
     * On success, clears the field, refocuses it, and triggers
     * a burst of fast polls so the new message renders
     * immediately instead of waiting for the next active poll.
     *
     * No-ops when the body is empty or whitespace.
     *
     * @return {void}
     */
    Chat.prototype.send = function () {
        var body = $.trim(this.$body.val());

        if (!body) {
            return;
        }

        var payload = {
            conversation_id: this.conversationId,
            body: body,
            client_id: this.generateClientId()
        };

        var token = this.getCsrfToken();
        if (token) {
            payload['csrf-token'] = token;
        }

        var self = this;

        $.ajax({
            url: this.sendUrl,
            method: 'POST',
            dataType: 'json',
            data: payload,
            xhrFields: {
                withCredentials: true
            }
        })
        .done(function (result) {
            if (result && result.id) {
                self.$body.val('').trigger('focus');
                self.burst = 3;
                self.idleSince = Date.now();
                self.cancel();
                self.poll();
            }
        })
        .fail(function (xhr, status, error) {
            self.log('send failed', status, error);
        });
    };

    /**
     * Fetches messages newer than `lastId` and schedules the next
     * poll.
     *
     * Uses `this.xhr` as a lock: if a request is already in flight,
     * the new call reschedules instead of stacking a second
     * request. `burst` is decremented on every successful response
     * so burst mode expires after exactly N polls, regardless of
     * whether messages arrived.
     *
     * @return {void}
     */
    Chat.prototype.poll = function () {
        this.timer = null;

        if (this.xhr) {
            this.schedule();
            return;
        }

        var self = this;

        this.log('polling');

        this.xhr = $.ajax({
            url: this.pollUrl,
            method: 'GET',
            dataType: 'json',
            data: {
                conversation_id: this.conversationId,
                since: this.lastId
            },
            timeout: this.timeout,
            xhrFields: {
                withCredentials: true
            }
        });

        this.xhr.done(function (payload) {
            self.failureBackoff = 0;

            if (self.burst > 0) {
                self.burst--;
            }

            self.consume(payload);
            self.schedule();
        })
        .fail(function (xhr, status, error) {
            self.log('poll failed', status, error);
            self.failureBackoff = 1;
            self.schedule();
        })
        .always(function () {
            self.xhr = null;
        });
    };

    /**
     * Renders every message in the payload that is newer than
     * `lastId` and advances `lastId` to the newest one.
     *
     * The `_id > lastId` guard is what makes the polling idempotent:
     * if the server returns a message already in `lastId` (e.g. the
     * sender's own message echoed back), it is skipped.
     *
     * @param {Object|null} payload Server response with a messages array
     * @return {void}
     */
    Chat.prototype.consume = function (payload) {
        if (!payload || !payload.messages || !payload.messages.length) {
            return;
        }

        var received = 0;

        for (var i = 0; i < payload.messages.length; i++) {
            var message = payload.messages[i];

            if (typeof message._id === 'number' && message._id > this.lastId) {
                this.lastId = message._id;
                this.appendMessage(message);
                received++;
            }
        }

        if (received > 0) {
            this.idleSince = Date.now();
        }
    };

    /**
     * Appends a single message to the thread and scrolls to
     * the bottom.
     *
     * Reads all class names and inline styles from
     * `this.selectors` and `this.styles`, so the visual layout
     * is fully configurable without editing this method.
     *
     * Handles the deleted-message tombstone (renders the
     * translated "[deleted]" label instead of the body).
     *
     * @param {Object} message Message in the server's IMessage shape
     * @return {void}
     */
    Chat.prototype.appendMessage = function (message) {
        var s = this.selectors;
        var isMine = message.user && parseInt(message.user._id, 10) === this.meId;

        var $wrapper = $('<div>').addClass(
            s.messageRow + ' ' + (isMine ? s.messageRowMine : s.messageRowOther)
        );

        var $bubble = $('<div>')
            .addClass(s.bubble + ' ' + (isMine ? s.bubbleMine : s.bubbleOther))
            .css({
                maxWidth: this.styles.bubbleMaxWidth,
                whiteSpace: this.styles.bubbleWhitespace
            });

        if (message.deleted === true) {
            $bubble.append(
                $('<em>')
                    .addClass(s.bubbleDeleted)
                    .text(this.translate('[deleted]', '[deleted]'))
            );
        } else {
            $bubble.text(message.text || '');
        }

        var $meta = $('<div>').addClass(s.meta).text(
            message.createdAt ? new Date(message.createdAt).toLocaleString() : ''
        );

        $wrapper.append($bubble).append($meta);
        this.$thread.append($wrapper);
        this.$thread.scrollTop(this.$thread[0].scrollHeight);
    };

    /**
     * Schedules the next poll after a delay chosen by `nextDelay()`.
     *
     * Cancels any previously scheduled timer first, so calling
     * this twice in a row leaves exactly one pending poll. If
     * the tab is hidden, the poll is not scheduled — the
     * visibility handler will trigger one when the tab returns.
     *
     * @return {void}
     */
    Chat.prototype.schedule = function () {
        if (this.timer) {
            clearTimeout(this.timer);
        }

        if (document.hidden) {
            this.timer = null;
            return;
        }

        var self = this;

        this.timer = setTimeout(function () {
            self.poll();
        }, this.nextDelay());
    };

    /**
     * Cancels any pending poll timer.
     *
     * Does not cancel an in-flight request — that is handled by
     * the request's own timeout in `poll()`.
     *
     * @return {void}
     */
    Chat.prototype.cancel = function () {
        if (this.timer) {
            clearTimeout(this.timer);
            this.timer = null;
        }
    };

    /**
     * Returns the delay, in milliseconds, before the next poll.
     *
     * Priority, in order:
     *   1. error — after a failed request, back off hard
     *   2. burst — after a send, poll fast to confirm delivery
     *   3. idle  — long silence, reduce server load
     *   4. active — default
     *
     * @return {number}
     */
    Chat.prototype.nextDelay = function () {
        if (this.failureBackoff > 0) {
            return this.intervals.error;
        }
        if (this.burst > 0) {
            return this.intervals.burst;
        }
        if (Date.now() - this.idleSince > this.intervals.idleAfter) {
            return this.intervals.idle;
        }
        return this.intervals.active;
    };

    /**
     * Returns a client-generated id used for optimistic-send
     * idempotency on the server.
     *
     * Uses crypto.randomUUID() when available (browsers on
     * HTTPS or localhost), with a timestamp-based fallback
     * otherwise. The fallback is not cryptographically secure
     * but is sufficient for the server's uniqueness check.
     *
     * @return {string}
     */
    Chat.prototype.generateClientId = function () {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }
        return String(Date.now()) + Math.random().toString(16).slice(2);
    };

    /**
     * Reads the CSRF token from the page's meta tag.
     *
     * Returns null when no meta tag is present, so callers can
     * omit the token from the request rather than sending an
     * empty string.
     *
     * @return {string|null}
     */
    Chat.prototype.getCsrfToken = function () {
        var $meta = $(this.selectors.csrfMeta);
        return $meta.length ? $meta.attr('content') : null;
    };

    /**
     * Looks up a translated string from the form's
     * data-translations attribute.
     *
     * The attribute is parsed as JSON on first access. Malformed
     * JSON is silently ignored, falling back to the provided
     * default — a broken translation should not break the chat.
     *
     * @param {string} key
     * @param {string} fallback
     * @return {string}
     */
    Chat.prototype.translate = function (key, fallback) {
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

        return fallback;
    };

    /**
     * Writes a debug message to the console when `debug` is on.
     *
     * Logging is off by default, so production pages do not
     * pollute the console with polling traces.
     *
     * @return {void}
     */
    Chat.prototype.log = function () {
        if (this.debug && window.console && console.log) {
            console.log.apply(console, arguments);
        }
    };

    Chat.DEFAULT_SELECTORS = DEFAULT_SELECTORS;
    Chat.DEFAULT_STYLES = DEFAULT_STYLES;
    Chat.DEFAULT_INTERVALS = DEFAULT_INTERVALS;
    Chat.DEFAULT_TIMEOUT = DEFAULT_TIMEOUT;
    Chat.autoInit = true;

    window.Chat = Chat;

    $(function () {
        if (!Chat.autoInit) {
            return;
        }

        var $form = $(DEFAULT_SELECTORS.form);
        if (!$form.length) {
            return;
        }

        if ($form.data('chat-initialized')) {
            return;
        }

        $form.data('chat-initialized', true);

        new Chat({ form: $form });
    });
})(window, document, jQuery);