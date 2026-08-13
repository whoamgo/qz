/**
 * user-data.js — the post-registration profile step.
 *
 * Keeps the hidden country_code / mobile_code in sync with the country select
 * (the server validates all three independently), and checks username and
 * mobile availability against the existing user.checkUser endpoint.
 */
(function ($, window) {
    'use strict';

    var WUserData = {
        config: null,

        init: function (config) {
            this.config = config || {};
            this.$country = $('#uCountry');
            this.$username = $('#uUsername');
            this.$mobile = $('#uMobile');

            if (!this.$country.length) { return; }

            this.preselectCountry();
            this.syncCountry();
            this.bind();
        },

        /** Preselect the country detected from the visitor's IP, if any. */
        preselectCountry: function () {
            var code = this.config.detectedCode;
            if (!code) { return; }

            var $match = this.$country.find('option[data-code="' + code + '"]');
            if ($match.length) { this.$country.val($match.val()); }
        },

        /** Mirrors the selected option into the two hidden fields. */
        syncCountry: function () {
            var $selected = this.$country.find(':selected');
            var dial = $selected.data('mobile_code');
            var code = $selected.data('code');

            $('input[name=mobile_code]').val(dial);
            $('input[name=country_code]').val(code);
            $('.mobile-code').text('+' + dial);
        },

        bind: function () {
            var self = this;

            this.$country.on('change', function () {
                self.syncCountry();
                if ($.trim(self.$mobile.val())) { self.check('mobile'); }
            });

            // Username: enforce the server's character rule as you type so the
            // form cannot be submitted with a value the controller will reject.
            this.$username.on('input', function () {
                var cleaned = $(this).val().toLowerCase().replace(/[^a-z0-9_]/g, '');
                if (cleaned !== $(this).val()) { $(this).val(cleaned); }
            });

            this.$mobile.on('input', function () {
                var digits = $(this).val().replace(/[^0-9]/g, '');
                if (digits !== $(this).val()) { $(this).val(digits); }
            });

            this.$username.on('blur', function () { self.check('username'); });
            this.$mobile.on('blur', function () { self.check('mobile'); });

            $('#wUserDataForm').on('submit', function (e) {
                if ($.trim(self.$username.val()).length < 6) {
                    e.preventDefault();
                    self.$username.addClass('is-invalid');
                    window.WSite.toast('Username must be at least 6 characters.', 'error');
                    return;
                }
                if ($('.usernameExist').text() || $('.mobileExist').text()) {
                    e.preventDefault();
                    window.WSite.toast('Please fix the highlighted fields before continuing.', 'error');
                    return;
                }
                window.WSite.busy($('#wUserDataSubmit'), true, 'Saving...');
            });
        },

        /** Availability check against the existing checkUser endpoint. */
        check: function (type) {
            var value = (type === 'username' ? this.$username : this.$mobile).val();
            if (!$.trim(value)) { $('.' + type + 'Exist').text(''); return; }

            var data = { _token: window.WSite.csrf() };
            if (type === 'username') {
                data.username = value;
            } else {
                data.mobile = value;
                data.mobile_code = $('.mobile-code').text().substr(1);
            }

            $.post(this.config.checkUrl, data, function (res) {
                if (res && res.data) {
                    $('.' + res.type + 'Exist').text(res.field + ' already exists');
                    $('#u' + (res.type === 'username' ? 'Username' : 'Mobile')).addClass('is-invalid');
                } else {
                    $('.' + type + 'Exist').text('');
                    $('#u' + (type === 'username' ? 'Username' : 'Mobile')).removeClass('is-invalid');
                }
            });
        }
    };

    window.WUserData = WUserData;

})(jQuery, window);
