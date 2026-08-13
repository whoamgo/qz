/**
 * common.js — shared helpers available to every other website script.
 * Exposes a single global: window.WSite
 */
(function ($, window) {
    'use strict';

    var WSite = {
        /** CSRF token from the meta tag rendered in layouts/app.blade.php. */
        csrf: function () {
            return $('meta[name="csrf-token"]').attr('content') || '';
        },

        /** Base URL, so scripts never hard-code a host. */
        base: function () {
            return $('meta[name="base-url"]').attr('content') || '';
        },

        /**
         * Toast notification. type: success | error | warning | info
         */
        toast: function (message, type) {
            type = type || 'info';

            var $wrap = $('.w-toast-wrap');
            if (!$wrap.length) {
                $wrap = $('<div class="w-toast-wrap" role="status" aria-live="polite"></div>').appendTo('body');
            }

            var icons = {
                success: 'bi-check-circle-fill',
                error: 'bi-x-circle-fill',
                warning: 'bi-exclamation-triangle-fill',
                info: 'bi-info-circle-fill'
            };

            var $toast = $(
                '<div class="w-toast is-' + type + '">' +
                    '<div class="d-flex align-items-start gap-2">' +
                        '<i class="bi ' + (icons[type] || icons.info) + '"></i>' +
                        '<div class="flex-grow-1 w-text-sm">' + $('<div>').text(message).html() + '</div>' +
                        '<button type="button" class="btn-close btn-close-sm" aria-label="Close"></button>' +
                    '</div>' +
                '</div>'
            );

            $wrap.append($toast);

            var dismiss = function () {
                // Class drives the CSS exit transition; remove after it ends.
                $toast.addClass('is-leaving');
                setTimeout(function () { $toast.remove(); }, 320);
            };

            $toast.find('.btn-close').on('click', dismiss);
            setTimeout(dismiss, 4200);
        },

        /**
         * Reward toast for gamification events (XP earned, badge unlocked).
         * Same component as toast(), with an icon and an accent.
         */
        reward: function (message, icon) {
            var $wrap = $('.w-toast-wrap');
            if (!$wrap.length) {
                $wrap = $('<div class="w-toast-wrap" role="status" aria-live="polite"></div>').appendTo('body');
            }

            var $toast = $(
                '<div class="w-toast is-success">' +
                    '<div class="d-flex align-items-center gap-2">' +
                        '<i class="bi ' + (icon || 'bi-lightning-charge-fill') + ' text-warning fs-5"></i>' +
                        '<div class="flex-grow-1 w-text-sm fw-semibold"></div>' +
                    '</div>' +
                '</div>'
            );
            $toast.find('div.flex-grow-1').text(message);
            $wrap.append($toast);

            setTimeout(function () {
                $toast.addClass('is-leaving');
                setTimeout(function () { $toast.remove(); }, 320);
            }, 4200);
        },

        /** Formats seconds as H:MM:SS or M:SS. */
        formatTime: function (totalSeconds) {
            totalSeconds = Math.max(0, parseInt(totalSeconds, 10) || 0);
            var h = Math.floor(totalSeconds / 3600);
            var m = Math.floor((totalSeconds % 3600) / 60);
            var s = totalSeconds % 60;
            var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            return h > 0 ? h + ':' + pad(m) + ':' + pad(s) : m + ':' + pad(s);
        },

        /** Trailing-edge debounce. */
        debounce: function (fn, wait) {
            var timer = null;
            return function () {
                var ctx = this, args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function () { fn.apply(ctx, args); }, wait || 250);
            };
        },

        /** jQuery AJAX with the CSRF header pre-set. */
        post: function (url, data) {
            return $.ajax({
                url: url,
                type: 'POST',
                data: data,
                dataType: 'json',
                headers: { 'X-CSRF-TOKEN': WSite.csrf(), 'X-Requested-With': 'XMLHttpRequest' }
            });
        },

        get: function (url, data) {
            return $.ajax({
                url: url,
                type: 'GET',
                data: data,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        },

        /** Toggles a button into a spinner state and back. */
        busy: function ($btn, isBusy, busyText) {
            if (isBusy) {
                $btn.data('original-html', $btn.html())
                    .prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-2"></span>' + (busyText || 'Please wait...'));
            } else {
                $btn.prop('disabled', false).html($btn.data('original-html'));
            }
        }
    };

    window.WSite = WSite;

    $(function () {
        // Server-side flash messages are rendered into data attributes and
        // surfaced through the same toast component as AJAX responses.
        $('[data-flash]').each(function () {
            WSite.toast($(this).data('flash-message'), $(this).data('flash'));
        });

        // Bootstrap tooltips, opt-in via data-bs-toggle.
        if (window.bootstrap) {
            $('[data-bs-toggle="tooltip"]').each(function () {
                new window.bootstrap.Tooltip(this);
            });
        }

        // Lazy-load any image that did not declare loading explicitly.
        $('img:not([loading])').attr('loading', 'lazy');
    });

})(jQuery, window);
