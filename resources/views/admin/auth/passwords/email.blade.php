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
                <h2>@lang('Forgot your') <br>@lang('password?')</h2>
                <p>@lang('No worries. Enter the email linked to your admin account and we will send you a verification code to reset it.')</p>
                <ul class="qz-brand-features">
                    <li><i class="las la-envelope"></i> @lang('We email you a secure code')</li>
                    <li><i class="las la-key"></i> @lang('Set a brand-new password')</li>
                    <li><i class="las la-lock"></i> @lang('Your account stays protected')</li>
                </ul>
            </div>
        </aside>

        {{-- Form side --}}
        <div class="qz-login-form-wrap">
            <div class="qz-form-head">
                <h3>@lang('Recover Account')</h3>
                <p>@lang('Enter your account email to receive a reset code.')</p>
            </div>

            {{-- Functionality preserved: same action, method, name attribute,
                 CSRF, captcha and the verify-gcaptcha hook. UI only changed. --}}
            <form action="{{ route('admin.password.reset') }}" method="POST" class="verify-gcaptcha login-form">
                @csrf
                <div class="form-group">
                    <label for="qzEmail">@lang('Email')</label>
                    <div class="qz-field">
                        <i class="las la-envelope qz-field-icon"></i>
                        <input type="email" id="qzEmail" name="email" class="form-control" value="{{ old('email') }}"
                            placeholder="@lang('Enter your account email')" required>
                    </div>
                </div>

                <x-captcha />

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
