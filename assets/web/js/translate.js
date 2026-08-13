/**
 * translate.js — Google Website Translator, driven by the site's own
 * language switcher.
 *
 * Google's widget is injected into a hidden container and its banner/toolbar
 * is suppressed in CSS, so visitors only ever see our switcher. Language is
 * carried by the `googtrans` cookie, which Google reads on load.
 */
(function ($, window, document) {
    'use strict';

    var DEFAULT = 'en';

    var WTranslate = {

        /** Reads the language Google is currently applying. */
        current: function () {
            var m = document.cookie.match(/(?:^|;\s*)googtrans=\/[^\/]*\/([^;]+)/);
            return m ? decodeURIComponent(m[1]) : DEFAULT;
        },

        /**
         * Writes the cookie on every path/domain variant Google may look at.
         * The app can live in a sub-directory, so a single path=/ cookie is
         * not always enough.
         */
        setCookie: function (value) {
            var host = window.location.hostname;
            var paths = ['/', window.location.pathname.split('/')[1] ? '/' + window.location.pathname.split('/')[1] : null];
            var domains = [null, host, '.' + host];

            paths.forEach(function (path) {
                if (!path) { return; }
                domains.forEach(function (domain) {
                    var c = 'googtrans=' + (value || '') + '; path=' + path +
                            (domain ? '; domain=' + domain : '') +
                            (value ? '' : '; expires=Thu, 01 Jan 1970 00:00:00 GMT');
                    try { document.cookie = c; } catch (e) { /* ignore */ }
                });
            });
        },

        /** Switches language: English clears the cookie, others set it. */
        to: function (lang) {
            this.setCookie('');                       // always clear first
            if (lang && lang !== DEFAULT) {
                this.setCookie('/' + DEFAULT + '/' + lang);
            }
        }
    };

    window.WTranslate = WTranslate;

    // Google calls this once its script has loaded.
    window.wGoogleTranslateInit = function () {
        if (!window.google || !window.google.translate) { return; }
        new window.google.translate.TranslateElement({
            pageLanguage: DEFAULT,
            includedLanguages: $('#google_translate_element').data('languages') || 'en,hi,bn',
            autoDisplay: false
        }, 'google_translate_element');
    };

    $(function () {
        // The switcher sets both: Laravel's session locale (for @lang chrome)
        // and Google's cookie (for everything else, including DB content).
        $(document).on('click', '.wLangOption', function (e) {
            var lang = $(this).data('lang');
            if (!lang) { return; }
            e.preventDefault();
            WTranslate.to(lang);
            window.location.href = $(this).attr('href');   // /change/{code}
        });
    });

})(jQuery, window, document);
