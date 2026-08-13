/**
 * profile.js — bookmark toggles and the profile settings form.
 */
(function ($, window) {
    'use strict';

    $(function () {

        // Bookmark toggle, usable from quiz cards and the review screen.
        $(document).on('click', '.wBookmarkBtn', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var url = $btn.data('url');

            if (!url) {
                window.WSite.toast('Please sign in to save bookmarks.', 'warning');
                return;
            }

            $btn.prop('disabled', true);

            window.WSite.post(url, { type: $btn.data('type'), id: $btn.data('id') })
                .done(function (res) {
                    var $icon = $btn.find('i');
                    $icon.toggleClass('bi-bookmark', !res.bookmarked)
                         .toggleClass('bi-bookmark-fill', res.bookmarked);
                    $btn.attr('aria-pressed', res.bookmarked ? 'true' : 'false');
                    window.WSite.toast(res.message, 'success');
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not update the bookmark.';
                    window.WSite.toast(msg, 'error');
                })
                .always(function () { $btn.prop('disabled', false); });
        });

        // Avatar picker: instant local preview before upload. The server still
        // validates type and size on submit.
        var $avatarInput = $('#wAvatarInput');
        if ($avatarInput.length) {
            var originalSrc = $('#wAvatarPreview').attr('src');

            $avatarInput.on('change', function () {
                var file = this.files && this.files[0];
                if (!file) { return; }

                if (file.size > 2 * 1024 * 1024) {
                    window.WSite.toast('That image is larger than 2 MB. Please choose a smaller one.', 'error');
                    this.value = '';
                    return;
                }

                var reader = new FileReader();
                reader.onload = function (e) {
                    $('#wAvatarPreview').attr('src', e.target.result);
                };
                reader.readAsDataURL(file);

                $('#wAvatarName').text(file.name).addClass('w-muted');
                $('#wAvatarClear').removeClass('d-none');
            });

            $('#wAvatarClear').on('click', function () {
                $avatarInput.val('');
                $('#wAvatarPreview').attr('src', originalSrc);
                $('#wAvatarName').text('');
                $(this).addClass('d-none');
            });
        }

        // Client-side required-field check; the server validates regardless.
        $('#wSettingsForm').on('submit', function (e) {
            var ok = true;
            $(this).find('[required]').each(function () {
                var $f = $(this);
                var empty = !$.trim($f.val());
                $f.toggleClass('is-invalid', empty);
                if (empty) { ok = false; }
            });

            if (!ok) {
                e.preventDefault();
                window.WSite.toast('Please complete the required fields.', 'error');
            }
        });

        $('#wSettingsForm [required]').on('input', function () {
            $(this).toggleClass('is-invalid', !$.trim($(this).val()));
        });
    });

})(jQuery, window);
