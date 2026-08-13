/**
 * search.js — live search suggestions.
 *
 * Each .w-search-wrap is wired independently, so the hero search and the
 * mobile menu search can coexist without sharing state. Previously a single
 * global selector meant only the first input on the page worked.
 */
(function ($, window) {
    'use strict';

    function initSearch(wrap) {
        var $wrap  = $(wrap);
        var $input = $wrap.find('.w-search-input');
        var $box   = $wrap.find('.w-suggest');
        var url    = $input.data('suggest-url');

        // No endpoint declared: plain form, no live suggestions.
        if (!$input.length || !$box.length || !url) { return; }

        var activeIndex = -1;
        var lastTerm = '';

        function hide() {
            $box.removeClass('show').empty();
            activeIndex = -1;
            $input.attr('aria-expanded', 'false');
        }

        function skeleton() {
            var rows = '';
            for (var i = 0; i < 3; i++) {
                rows += '<div class="w-suggest-item"><span class="w-skeleton-line is-title mb-0"></span></div>';
            }
            $box.html(rows).addClass('show');
        }

        function render(payload) {
            var quizzes = payload.quizzes || [];
            var cats    = payload.categories || [];
            var html    = '';

            if (quizzes.length) {
                html += '<div class="w-suggest-label">Quizzes</div>';
                $.each(quizzes, function (i, q) {
                    html += '<a class="w-suggest-item" href="' + q.url + '" role="option">' +
                                '<i class="bi bi-patch-question me-2"></i>' +
                                '<span><span class="wq-title"></span>' +
                                '<small class="w-muted d-block">' + (q.questions || 0) + ' questions</small></span>' +
                            '</a>';
                });
            }

            if (cats.length) {
                html += '<div class="w-suggest-label">Categories</div>';
                $.each(cats, function (i, c) {
                    html += '<a class="w-suggest-item" href="' + c.url + '" role="option">' +
                                '<i class="bi bi-folder2 me-2"></i><span class="wc-title"></span>' +
                            '</a>';
                });
            }

            if (!html) {
                html = '<div class="w-suggest-item text-center w-muted">No matches found</div>';
            }

            $box.html(html).addClass('show');
            $input.attr('aria-expanded', 'true');

            // Titles are set with .text() so a result can never inject markup.
            $box.find('.wq-title').each(function (i) { $(this).text(quizzes[i].title); });
            $box.find('.wc-title').each(function (i) { $(this).text(cats[i].name); });

            activeIndex = -1;
        }

        var lookup = window.WSite.debounce(function (term) {
            window.WSite.get(url, { q: term })
                .done(function (payload) {
                    // Ignore a response that arrived after the user typed on.
                    if (term !== lastTerm) { return; }
                    render(payload);
                })
                .fail(hide);
        }, 250);

        $input.on('input', function () {
            var term = $.trim($(this).val());
            lastTerm = term;

            if (term.length < 2) { hide(); return; }
            skeleton();
            lookup(term);
        });

        $input.on('focus', function () {
            if ($.trim($(this).val()).length >= 2 && $box.children().length) {
                $box.addClass('show');
            }
        });

        $input.on('keydown', function (e) {
            var $items = $box.find('a.w-suggest-item');
            if (!$items.length) { return; }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, $items.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                window.location.href = $items.eq(activeIndex).attr('href');
                return;
            } else if (e.key === 'Escape') {
                hide();
                return;
            } else {
                return;
            }

            $items.removeClass('is-active').eq(activeIndex).addClass('is-active');
            $items.eq(activeIndex)[0].scrollIntoView({ block: 'nearest' });
        });

        // Close when focus or a click leaves this particular widget.
        $(document).on('click', function (e) {
            if (!$(e.target).closest($wrap).length) { hide(); }
        });
    }

    $(function () {
        $('.w-search-wrap').each(function () { initSearch(this); });
    });

})(jQuery, window);
