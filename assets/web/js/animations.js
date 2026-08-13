/**
 * animations.js — motion behaviour that CSS alone cannot express.
 *
 * Deliberately small: scroll reveal, number count-up and progress-bar fill.
 * Everything degrades gracefully — if this file fails to load, no content is
 * left hidden, because the reveal classes are only applied from here.
 */
(function ($, window, document) {
    'use strict';

    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var supportsObserver = 'IntersectionObserver' in window;

    var WAnim = {

        init: function () {
            if (reduced) {
                // Honour the OS setting: paint final states immediately.
                this.paintFinalStates();
                return;
            }

            this.scrollReveal();
            this.progressBars();
            this.countUp();
            this.badgePop();
        },

        /** With reduced motion, jump straight to the end state of everything. */
        paintFinalStates: function () {
            $('[data-progress]').each(function () {
                $(this).css('width', $(this).data('progress') + '%');
            });
            $('[data-countup]').each(function () {
                $(this).text($(this).data('countup'));
            });
        },

        /**
         * Reveals sections and card grids as they enter the viewport.
         * Classes are added here, not in Blade, so content is visible by
         * default and only becomes animatable once JS is running.
         */
        scrollReveal: function () {
            if (!supportsObserver) { return; }

            var targets = [];

            // Whole sections below the fold.
            $('.w-section').each(function (i) {
                // The first section is already in view on load; let the hero
                // and page-in animation handle it instead.
                if (i === 0) { return; }
                $(this).addClass('w-reveal');
                targets.push(this);
            });

            // Card grids animate as a staggered group. A row only qualifies if
            // EVERY child column holds a card-like element — otherwise a page
            // layout row (sidebar + content) would get hidden along with it.
            var CARDS = '.w-card, .w-stat-tile, .w-badge-tile, .w-cat-card, .w-skeleton-card';

            $('.row.g-3, .row.g-4').each(function () {
                var $row = $(this);
                if ($row.closest('.w-reveal').length) { return; }

                var $cols = $row.children();
                if ($cols.length < 2) { return; }

                // Layout rows nest further rows, or carry the profile nav.
                if ($row.find('.w-profile-nav').length) { return; }
                if ($cols.children('.row').length) { return; }

                var allCards = true;
                $cols.each(function () {
                    if (!$(this).find(CARDS).addBack(CARDS).length) { allCards = false; }
                });
                if (!allCards) { return; }

                $row.addClass('w-reveal-group');
                targets.push(this);
            });

            if (!targets.length) { return; }

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) { return; }
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

            targets.forEach(function (el) { observer.observe(el); });

            // Safety net: if anything is still hidden after 3s (observer never
            // fired, element off-screen in an odd layout), force it visible.
            window.setTimeout(function () {
                targets.forEach(function (el) { el.classList.add('is-visible'); });
            }, 3000);
        },

        /**
         * Fills progress bars from 0 to their target width once visible.
         * Markup carries data-progress="NN"; the inline width stays as the
         * server-rendered fallback for a no-JS visitor.
         */
        progressBars: function () {
            var $bars = $('[data-progress]');
            if (!$bars.length) { return; }

            $bars.css('width', '0%');

            var fill = function (el) {
                var $el = $(el);
                window.setTimeout(function () {
                    $el.css('width', ($el.data('progress') || 0) + '%');
                }, 120);
            };

            if (!supportsObserver) {
                $bars.each(function () { fill(this); });
                return;
            }

            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) { return; }
                    fill(e.target);
                    obs.unobserve(e.target);
                });
            }, { threshold: 0.3 });

            $bars.each(function () { obs.observe(this); });
        },

        /** Counts a number up to its final value, e.g. XP earned on a result. */
        countUp: function () {
            var $els = $('[data-countup]');
            if (!$els.length) { return; }

            var animate = function (el) {
                var $el = $(el);
                var target = parseFloat($el.data('countup')) || 0;
                var prefix = $el.data('countup-prefix') || '';
                var duration = 900;
                var start = null;

                if (target === 0) { $el.text(prefix + '0'); return; }

                var step = function (ts) {
                    if (start === null) { start = ts; }
                    var progress = Math.min((ts - start) / duration, 1);
                    // easeOutCubic
                    var eased = 1 - Math.pow(1 - progress, 3);
                    $el.text(prefix + Math.round(target * eased).toLocaleString());
                    if (progress < 1) { window.requestAnimationFrame(step); }
                };

                window.requestAnimationFrame(step);
            };

            if (!supportsObserver) {
                $els.each(function () { animate(this); });
                return;
            }

            var obs = new IntersectionObserver(function (entries) {
                entries.forEach(function (e) {
                    if (!e.isIntersecting) { return; }
                    animate(e.target);
                    obs.unobserve(e.target);
                });
            }, { threshold: 0.5 });

            $els.each(function () {
                $(this).text($(this).data('countup-prefix') || '');
                obs.observe(this);
            });
        },

        /** One-off pop on badges rendered as newly earned. */
        badgePop: function () {
            $('[data-badge-new]').addClass('w-badge-unlocked');
        }
    };

    window.WAnim = WAnim;
    $(function () { WAnim.init(); });

})(jQuery, window, document);
