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
                <h2>@lang('Welcome back,') <br>@lang('Administrator')</h2>
                <p>@lang('Sign in to manage quizzes, users, categories and everything on your') {{ __(gs('site_name')) }} @lang('dashboard.')</p>
                <ul class="qz-brand-features">
                    <li><i class="las la-shield-alt"></i> @lang('Secure, role-based admin access')</li>
                    <li><i class="las la-bolt"></i> @lang('Fast quiz & content management')</li>
                    <li><i class="las la-chart-line"></i> @lang('Live insights and analytics')</li>
                </ul>
            </div>
        </aside>

        {{-- Form side --}}
        <div class="qz-login-form-wrap">
            <div class="qz-form-head">
                <h3>{{ __($pageTitle) }}</h3>
                <p>@lang('Enter your credentials to access the dashboard.')</p>
            </div>

            {{-- Functionality preserved: same action, method, name attributes,
                 CSRF, captcha and the verify-gcaptcha hook. UI only changed. --}}
            <form action="{{ route('admin.login') }}" method="POST" class="verify-gcaptcha login-form">
                @csrf
                <div class="form-group">
                    <label for="qzUsername">@lang('Username')</label>
                    <div class="qz-field">
                        <i class="las la-user qz-field-icon"></i>
                        <input type="text" id="qzUsername" class="form-control" value="{{ old('username') }}"
                            name="username" placeholder="@lang('Enter your username')" required>
                    </div>
                </div>

                <div class="form-group">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="qzPassword">@lang('Password')</label>
                        <a href="{{ route('admin.password.reset') }}" class="forget-text">@lang('Forgot Password?')</a>
                    </div>
                    <div class="qz-field">
                        <i class="las la-lock qz-field-icon"></i>
                        <input type="password" id="qzPassword" class="form-control" name="password"
                            placeholder="@lang('Enter your password')" required>
                        <button type="button" class="qz-pw-toggle" aria-label="@lang('Show password')" data-qz-toggle="qzPassword">
                            <i class="las la-eye"></i>
                        </button>
                    </div>
                </div>

                <x-captcha />

                <button type="submit" class="btn btn-submit">@lang('LOGIN')</button>
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
