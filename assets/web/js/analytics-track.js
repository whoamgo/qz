/*!
 * QuizMitra first-party click / event tracker.
 *
 * Usage (declarative):
 *   <button data-track-click="true"
 *           data-track-name="Create Room"
 *           data-track-category="quiz">Create Room</button>
 *
 * Usage (programmatic):
 *   window.QzTrack.event('quiz_started', 'quiz', { name: 'HTML Quiz' });
 *
 * All security-sensitive fields (user, IP, session, timestamp) are resolved
 * server-side. This script only reports what element was interacted with.
 * Fire-and-forget: failures never surface to the user.
 */
(function () {
    'use strict';

    var meta = function (name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    };

    var CSRF = meta('csrf-token');
    var BASE = (meta('base-url') || '').replace(/\/+$/, '');
    var ENDPOINT = BASE + '/track/event';

    function send(payload) {
        try {
            payload.page_path = payload.page_path || window.location.pathname;
            payload.page_url = payload.page_url || window.location.href;
            payload.page_title = payload.page_title || document.title;

            var body = JSON.stringify(payload);

            // Prefer fetch(keepalive) so the request survives navigation.
            if (window.fetch) {
                fetch(ENDPOINT, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: body,
                    keepalive: true,
                    credentials: 'same-origin'
                }).catch(function () { /* silent */ });
            }
        } catch (e) { /* silent */ }
    }

    // Public API for business events fired from other scripts.
    window.QzTrack = {
        event: function (eventType, category, extra) {
            extra = extra || {};
            send({
                event_type: eventType || 'click',
                element_name: extra.name || eventType,
                element_category: category || extra.category || null,
                element_id: extra.id || null,
                element_type: extra.type || 'event'
            });
        }
    };

    // Delegated listener so dynamically-added elements work too.
    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[data-track-click]') : null;
        if (!el) { return; }
        if (el.getAttribute('data-track-click') === 'false') { return; }

        send({
            event_type: el.getAttribute('data-track-event') || 'click',
            element_name: el.getAttribute('data-track-name')
                || (el.innerText || el.textContent || '').trim().slice(0, 120)
                || el.getAttribute('aria-label')
                || null,
            element_category: el.getAttribute('data-track-category') || null,
            element_id: el.getAttribute('data-track-id') || el.id || null,
            element_type: (el.tagName || '').toLowerCase()
        });
    }, true);
})();
