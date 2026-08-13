/**
 * app.js — global site behaviour: navigation, filters, scroll helpers.
 */
(function ($, window) {
    'use strict';

    $(function () {

        // Auto-submit listing filters on change, preserving the query string.
        $('.wFilterForm').on('change', 'select', function () {
            $(this).closest('form').trigger('submit');
        });

        $('.wFilterReset').on('click', function (e) {
            e.preventDefault();
            var $form = $($(this).data('target'));
            $form.find('select').val('');
            $form.find('input[type=text], input[type=search]').val('');
            $form.trigger('submit');
        });

        // Back-to-top, shown only once the user has scrolled a screenful.
        var $toTop = $('#wBackToTop');
        if ($toTop.length) {
            $(window).on('scroll', window.WSite.debounce(function () {
                $toTop.toggleClass('d-none', $(window).scrollTop() < 600);
            }, 120));

            $toTop.on('click', function () {
                $('html, body').animate({ scrollTop: 0 }, 320);
            });
        }

        // Mark the active item in the mobile bottom nav from the current path.
        var path = window.location.pathname;
        $('.w-bottom-nav a').each(function () {
            var href = $(this).attr('href') || '';
            try {
                var p = new URL(href, window.location.origin).pathname;
                if (p !== '/' && path.indexOf(p) === 0) { $(this).addClass('active'); }
                else if (p === '/' && path === '/') { $(this).addClass('active'); }
            } catch (err) { /* ignore malformed hrefs */ }
        });

        // Share buttons on article pages.
        $('.wShareBtn').on('click', function (e) {
            e.preventDefault();
            var network = $(this).data('network');
            var url = encodeURIComponent(window.location.href);
            var title = encodeURIComponent(document.title);
            var target = '';

            if (network === 'facebook') {
                target = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
            } else if (network === 'twitter') {
                target = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title;
            } else if (network === 'whatsapp') {
                target = 'https://wa.me/?text=' + title + '%20' + url;
            } else if (network === 'linkedin') {
                target = 'https://www.linkedin.com/sharing/share-offsite/?url=' + url;
            } else if (network === 'copy') {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(window.location.href).then(function () {
                        window.WSite.toast('Link copied to clipboard.', 'success');
                    });
                }
                return;
            }

            window.open(target, '_blank', 'noopener,width=620,height=480');
        });

        // Table of contents highlighting on article pages.
        var $toc = $('.w-toc a');
        if ($toc.length) {
            $(window).on('scroll', window.WSite.debounce(function () {
                var top = $(window).scrollTop() + 120;
                $toc.each(function () {
                    var $target = $($(this).attr('href'));
                    if ($target.length && $target.offset().top <= top) {
                        $toc.removeClass('active');
                        $(this).addClass('active');
                    }
                });
            }, 100));
        }
    });

})(jQuery, window);

/**
 * Quiz result score sharing. Instagram has no web intent URL, so it copies the
 * text for pasting instead of pretending to open a share dialog.
 */
(function ($, window) {
    'use strict';

    $(function () {
        var $wrap = $('#wScoreShare');
        if (!$wrap.length) { return; }

        var text = $wrap.data('share-text') || '';
        var url  = $wrap.data('share-url') || window.location.href;

        $('.wScoreShareBtn').on('click', function () {
            var net = $(this).data('network');
            var t = encodeURIComponent(text);
            var u = encodeURIComponent(url);
            var target = '';

            if (net === 'whatsapp')      { target = 'https://wa.me/?text=' + t + '%20' + u; }
            else if (net === 'facebook') { target = 'https://www.facebook.com/sharer/sharer.php?u=' + u + '&quote=' + t; }
            else if (net === 'twitter')  { target = 'https://twitter.com/intent/tweet?text=' + t + '&url=' + u; }
            else if (net === 'telegram') { target = 'https://t.me/share/url?url=' + u + '&text=' + t; }
            else if (net === 'instagram' || net === 'copy') {
                var payload = net === 'instagram' ? (text + ' ' + url) : url;

                // Native share sheet first on mobile — that is the only route
                // that can actually reach the Instagram app.
                if (net === 'instagram' && navigator.share) {
                    navigator.share({ text: text, url: url }).catch(function () {});
                    return;
                }
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(payload).then(function () {
                        window.WSite.toast(
                            net === 'instagram'
                                ? 'Score copied — paste it into your Instagram story or post.'
                                : 'Link copied to clipboard.',
                            'success'
                        );
                    });
                } else {
                    window.WSite.toast('Copy this: ' + payload, 'info');
                }
                return;
            }

            window.open(target, '_blank', 'noopener,width=620,height=520');
        });
    });

})(jQuery, window);

/**
 * Footer newsletter sign-up. Posts over AJAX with the CSRF token, keeps the
 * button in a busy state while in flight, and reports the server's message
 * inline as well as via a toast.
 */
(function ($, window) {
    'use strict';

    $(function () {
        var $form = $('#wSubscribeForm');
        if (!$form.length) { return; }

        var $input   = $('#wSubscribeEmail');
        var $btn     = $('#wSubscribeBtn');
        var $msg     = $('#wSubscribeMsg');
        var original = $msg.html();
        var busy     = false;

        function setMessage(text, state) {
            $msg.removeClass('is-success is-error');
            if (state) { $msg.addClass('is-' + state); }
            $msg.text(text);
        }

        $form.on('submit', function (e) {
            e.preventDefault();
            if (busy) { return; }

            var email = $.trim($input.val());
            if (!email) {
                setMessage('Please enter your email address.', 'error');
                $input.trigger('focus');
                return;
            }

            busy = true;
            $btn.prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm"></span>');

            window.WSite.post($form.data('action'), { email: email })
                .done(function (res) {
                    setMessage(res.message, 'success');
                    window.WSite.toast(res.message, 'success');
                    $input.val('');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) ||
                              'Could not subscribe right now. Please try again.';
                    setMessage(msg, 'error');
                    window.WSite.toast(msg, 'error');
                })
                .always(function () {
                    busy = false;
                    $btn.prop('disabled', false).html('<i class="bi bi-send-fill"></i>');
                    // Restore the privacy note after a moment.
                    window.setTimeout(function () {
                        $msg.removeClass('is-success is-error').html(original);
                    }, 6000);
                });
        });
    });

})(jQuery, window);
