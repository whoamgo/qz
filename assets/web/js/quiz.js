/**
 * quiz.js — the attempt screen.
 *
 * Holds question state client-side for navigation only. Answers are persisted
 * to the server on every change; the score is never computed here and the
 * correct answers are never present in the payload the server sends.
 */
(function ($, window) {
    'use strict';

    var QuizAttempt = {
        config: null,
        questions: [],
        index: 0,
        remaining: null,
        timerId: null,
        submitting: false,

        init: function (config) {
            this.config = config;
            this.questions = config.questions || [];
            this.remaining = (config.remaining === null || typeof config.remaining === 'undefined')
                ? null
                : parseInt(config.remaining, 10);

            if (!this.questions.length) { return; }

            this.cacheDom();
            this.bindEvents();
            this.render();
            this.startTimer();
            this.guardUnload();
        },

        cacheDom: function () {
            this.$questionText = $('#wQuestionText');
            this.$questionNum = $('#wQuestionNum');
            this.$options = $('#wOptions');
            this.$nav = $('#wQuestionNav');
            this.$progress = $('#wProgressBar');
            this.$progressLabel = $('#wProgressLabel');
            this.$timer = $('#wTimer');
            this.$prev = $('#wPrevBtn');
            this.$next = $('#wNextBtn');
            this.$mark = $('#wMarkBtn');
            this.$answeredCount = $('.wAnsweredCount');
        },

        bindEvents: function () {
            var self = this;

            this.$prev.on('click', function () { self.go(self.index - 1); });
            this.$next.on('click', function () { self.go(self.index + 1); });
            this.$mark.on('click', function () { self.toggleMark(); });

            this.$nav.on('click', 'button', function () {
                self.go(parseInt($(this).data('index'), 10));
            });

            // Option selection is delegated so re-rendering does not lose it.
            this.$options.on('change', 'input[type=radio]', function () {
                self.select(parseInt($(this).val(), 10));
            });

            $('#wSubmitBtn').on('click', function () { self.confirmSubmit(); });
            $('#wConfirmSubmit').on('click', function () { self.submit(false); });

            // Keyboard: 1-4 pick an option, arrows navigate.
            $(document).on('keydown', function (e) {
                if ($(e.target).is('input, textarea, select')) { return; }

                if (e.key >= '1' && e.key <= '9') {
                    var i = parseInt(e.key, 10) - 1;
                    var $radio = self.$options.find('input[type=radio]').eq(i);
                    if ($radio.length) { $radio.prop('checked', true).trigger('change'); }
                } else if (e.key === 'ArrowRight') {
                    self.go(self.index + 1);
                } else if (e.key === 'ArrowLeft') {
                    self.go(self.index - 1);
                }
            });
        },

        current: function () { return this.questions[this.index]; },

        go: function (i) {
            if (i < 0 || i >= this.questions.length) { return; }
            this.index = i;
            this.render();
            $('html, body').animate({ scrollTop: 0 }, 180);
        },

        render: function () {
            var q = this.current();
            if (!q) { return; }

            this.$questionNum.text('Question ' + (this.index + 1) + ' of ' + this.questions.length);
            this.$questionText.text(q.text);

            // Options are built with .text() so any markup in the source is
            // rendered as literal text rather than injected as HTML.
            var html = '';
            var letters = ['A', 'B', 'C', 'D', 'E', 'F'];
            $.each(q.options, function (i, opt) {
                var checked = q.selected_option_id === opt.id;
                var id = 'wOpt' + opt.id;
                html += '<label class="w-option' + (checked ? ' is-selected' : '') + '" for="' + id + '">' +
                            '<input type="radio" class="visually-hidden" name="wAnswer" id="' + id + '" value="' + opt.id + '"' + (checked ? ' checked' : '') + '>' +
                            '<span class="w-option-key">' + (letters[i] || (i + 1)) + '</span>' +
                            '<span class="w-option-text"></span>' +
                        '</label>';
            });
            this.$options.html(html);
            this.$options.find('.w-option-text').each(function (i) {
                $(this).text(q.options[i].text);
            });

            this.$mark.toggleClass('active', !!q.marked_for_review)
                      .attr('aria-pressed', q.marked_for_review ? 'true' : 'false');

            this.$prev.prop('disabled', this.index === 0);
            this.$next.prop('disabled', this.index === this.questions.length - 1);

            this.renderNav();
            this.renderProgress();
        },

        renderNav: function () {
            var self = this, html = '';
            $.each(this.questions, function (i, q) {
                var cls = [];
                if (i === self.index) { cls.push('is-current'); }
                if (q.selected_option_id) { cls.push('is-answered'); }
                if (q.marked_for_review) { cls.push('is-marked'); }
                html += '<button type="button" class="' + cls.join(' ') + '" data-index="' + i + '" ' +
                        'aria-label="Question ' + (i + 1) + '"' + (i === self.index ? ' aria-current="true"' : '') + '>' +
                        (i + 1) + '</button>';
            });
            this.$nav.html(html);
        },

        renderProgress: function () {
            var answered = 0;
            $.each(this.questions, function (i, q) { if (q.selected_option_id) { answered++; } });

            var pct = this.questions.length ? Math.round((answered / this.questions.length) * 100) : 0;
            this.$progress.css('width', pct + '%').attr('aria-valuenow', pct);
            this.$progressLabel.text(answered + ' / ' + this.questions.length + ' answered');
            this.$answeredCount.text(answered);
            $('.wSkippedCount').text(this.questions.length - answered);
        },

        select: function (optionId) {
            var q = this.current();
            q.selected_option_id = optionId;

            this.$options.find('.w-option').removeClass('is-selected');
            this.$options.find('input[value="' + optionId + '"]').closest('.w-option').addClass('is-selected');

            this.renderNav();
            this.renderProgress();
            this.persist(q);
        },

        toggleMark: function () {
            var q = this.current();
            q.marked_for_review = !q.marked_for_review;

            this.$mark.toggleClass('active', q.marked_for_review)
                      .attr('aria-pressed', q.marked_for_review ? 'true' : 'false');
            this.renderNav();

            window.WSite.post(this.config.reviewUrl, {
                question_id: q.id,
                marked: q.marked_for_review ? 1 : 0
            });
        },

        /** Saves one answer. Failures surface as a toast, never silently. */
        persist: function (q) {
            window.WSite.post(this.config.answerUrl, {
                question_id: q.id,
                option_id: q.selected_option_id,
                marked: q.marked_for_review ? 1 : 0
            }).fail(function () {
                window.WSite.toast('Could not save that answer. Check your connection.', 'error');
            });
        },

        startTimer: function () {
            var self = this;
            if (this.remaining === null) { return; }

            this.tick();
            this.timerId = setInterval(function () { self.tick(); }, 1000);
        },

        tick: function () {
            if (this.remaining === null) { return; }

            this.remaining--;
            this.$timer.text(window.WSite.formatTime(this.remaining));

            this.$timer.toggleClass('is-warning', this.remaining <= 300 && this.remaining > 60);
            this.$timer.toggleClass('is-danger', this.remaining <= 60);

            if (this.remaining <= 0) {
                clearInterval(this.timerId);
                window.WSite.toast('Time is up. Submitting your attempt...', 'warning');
                this.submit(true);
            }
        },

        confirmSubmit: function () {
            var answered = 0;
            $.each(this.questions, function (i, q) { if (q.selected_option_id) { answered++; } });

            $('#wSummaryAnswered').text(answered);
            $('#wSummarySkipped').text(this.questions.length - answered);
            $('#wSummaryMarked').text($.grep(this.questions, function (q) { return q.marked_for_review; }).length);

            var modal = new window.bootstrap.Modal(document.getElementById('wSubmitModal'));
            modal.show();
        },

        submit: function (auto) {
            if (this.submitting) { return; }
            this.submitting = true;
            clearInterval(this.timerId);

            var elapsed = this.config.timeLimit
                ? (this.config.timeLimit * 60) - Math.max(0, this.remaining || 0)
                : Math.floor((Date.now() - this.config.startedAt) / 1000);

            // Release the unload guard before navigating away.
            $(window).off('beforeunload');

            var $form = $('<form method="POST" action="' + this.config.submitUrl + '"></form>')
                .append('<input type="hidden" name="_token" value="' + window.WSite.csrf() + '">')
                .append('<input type="hidden" name="time_taken" value="' + elapsed + '">')
                .appendTo('body');

            $form.trigger('submit');
        },

        /** Warns before an accidental navigation away mid-attempt. */
        guardUnload: function () {
            $(window).on('beforeunload', function (e) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            });
        }
    };

    window.QuizAttempt = QuizAttempt;

})(jQuery, window);
