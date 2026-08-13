/**
 * search.js — header typeahead. Queries the suggest endpoint after a short
 * debounce and renders grouped results with keyboard support.
 */
(function ($, window) {
    'use strict';

    $(function () {
        var $input = $('.w-search-input');
        if (!$input.length) { return; }

        var $box = $('.w-suggest');
        var url = $input.data('suggest-url');
        var activeIndex = -1;

        function hide() { $box.removeClass('show').empty(); activeIndex = -1; }

        function render(payload) {
            var html = '';

            if (payload.quizzes && payload.quizzes.length) {
                html += '<div class="w-suggest-label">Quizzes</div>';
                $.each(payload.quizzes, function (i, q) {
                    html += '<a class="w-suggest-item" href="' + q.url + '">' +
                                '<i class="bi bi-patch-question me-2"></i>' +
                                '<span class="wq-title"></span>' +
                                '<small class="w-muted d-block ms-4">' + (q.questions || 0) + ' questions</small>' +
                            '</a>';
                });
            }

            if (payload.categories && payload.categories.length) {
                html += '<div class="w-suggest-label">Categories</div>';
                $.each(payload.categories, function (i, c) {
                    html += '<a class="w-suggest-item" href="' + c.url + '">' +
                                '<i class="bi bi-folder2 me-2"></i><span class="wc-title"></span>' +
                            '</a>';
                });
            }

            if (!html) {
                html = '<div class="w-suggest-item text-center w-muted">No matches found</div>';
            }

            $box.html(html).addClass('show');

            // Titles are injected via .text() so results cannot carry markup.
            $box.find('.wq-title').each(function (i) { $(this).text(payload.quizzes[i].title); });
            $box.find('.wc-title').each(function (i) { $(this).text(payload.categories[i].name); });
        }

        var lookup = window.WSite.debounce(function (term) {
            window.WSite.get(url, { q: term })
                .done(render)
                .fail(hide);
        }, 260);

        $input.on('input', function () {
            var term = $.trim($(this).val());
            if (term.length < 2) { hide(); return; }
            lookup(term);
        });

        $input.on('keydown', function (e) {
            var $items = $box.find('.w-suggest-item');
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
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.w-search-wrap').length) { hide(); }
        });
    });

})(jQuery, window);
