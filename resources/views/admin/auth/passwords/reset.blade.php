@extends('admin.layouts.master')

@push('style')
    @include('admin.auth.partials.auth-style')
@endpush

@section('content')
<div class="qz-login">
    <div class="qz-login-card">

        {{-- Visual / brand side (decorative only) --}}
        <aside class="qz-login-brand">
            <div class="qz-brand-badge">
                <img src="{{ siteFavicon() }}" alt="{{ gs('site_name') }}">
                <span>{{ __(gs('site_name')) }}</span>
            </div>

            <div class="qz-brand-copy">
                <h2>@lang('Set a new') <br>@lang('password')</h2>
                <p>@lang('Choose a strong password you have not used before to finish recovering your admin account.')</p>
                <ul class="qz-brand-features">
                    <li><i class="las la-shield-alt"></i> @lang('Use a strong, unique password')</li>
                    <li><i class="las la-check-circle"></i> @lang('Confirm it to avoid typos')</li>
                    <li><i class="las la-sign-in-alt"></i> @lang('Then sign in as usual')</li>
                </ul>
            </div>
        </aside>

        {{-- Form side --}}
        <div class="qz-login-form-wrap">
            <div class="qz-form-head">
                <h3>@lang('Recover Account')</h3>
                <p>@lang('Enter and confirm your new password.')</p>
            </div>

            {{-- Functionality preserved: same action, method, hidden email/token,
                 field names and CSRF. UI only changed. --}}
            <form action="{{ route('admin.password.change') }}" method="POST" class="login-form">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group">
                    <label for="qzNewPassword">@lang('New Password')</label>
                    <div class="qz-field">
                        <i class="las la-lock qz-field-icon"></i>
                        <input type="password" id="qzNewPassword" name="password" class="form-control"
                            placeholder="@lang('Enter a new password')" required>
                        <button type="button" class="qz-pw-toggle" aria-label="@lang('Show password')" data-qz-toggle="qzNewPassword">
                            <i class="las la-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirmation">@lang('Re-type New Password')</label>
                    <div class="qz-field">
                        <i class="las la-lock qz-field-icon"></i>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                            placeholder="@lang('Re-enter the new password')" required>
                        <button type="button" class="qz-pw-toggle" aria-label="@lang('Show password')" data-qz-toggle="password_confirmation">
                            <i class="las la-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-submit">@lang('Submit')</button>

                <div class="text-center">
                    <a href="{{ route('admin.login') }}" class="qz-back-link">
                        <i class="las la-arrow-left" aria-hidden="true"></i> @lang('Back to Login')
                    </a>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection

@push('script')
<script>
    "use strict";
    (function ($) {
        // UI-only: password visibility toggle. Does not alter submission.
        $(document).on('click', '.qz-pw-toggle', function () {
            var input = document.getElementById($(this).data('qzToggle'));
            if (!input) return;
            var icon = $(this).find('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.removeClass('la-eye').addClass('la-eye-slash');
            } else {
                input.type = 'password';
                icon.removeClass('la-eye-slash').addClass('la-eye');
            }
        });
    })(jQuery);
</script>
@endpush
