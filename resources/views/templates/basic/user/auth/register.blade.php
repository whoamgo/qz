@extends('Template::layouts.frontend')
@section('content')
    @if (gs('registration'))
        @php
            $registerContent = getContent('register.content', true);
            $bannerContent = getContent('banner.content', true);
            $bannerElement = getContent('banner.element', orderById: true);
            $testimonialElement = getContent('testimonial.element', orderById: true);
        @endphp
        <div class="account-section">
            <div class="account-content">
                <form action="{{ route('user.register') }}" method="POST" class="account-form lg-style verify-gcaptcha">
                    @csrf
                    <a href="{{ route('home') }}" class="logo navbar-brand mb-4">
                        <img class="logo-img" src="{{ siteLogo() }}" alt="Logo">
                    </a>

                    <div class="row">
                        <div class="col-sm-6 col-lg-12 col-xl-6">
                            <div class="form-group">
                                <label class="form--label">@lang('First Name')</label>
                                <input type="text" class="form--control md-style" name="firstname"
                                       value="{{ old('firstname') }}" required>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-12 col-xl-6">
                            <div class="form-group">
                                <label class="form--label">@lang('Last Name')</label>
                                <input type="text" class="form--control md-style" name="lastname"
                                       value="{{ old('lastname') }}" required>
                            </div>
                        </div>
                        <div class="col-lg-12">
                            <div class="form-group">
                                <label class="form--label">@lang('Email Address')</label>
                                <input type="email" class="form--control md-style checkUser" name="email"
                                       value="{{ old('email') }}" required>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-12 col-xl-6">
                            <div class="form-group">
                                <label for="password" class="form--label">@lang('Password')</label>
                                <div class="position-relative">
                                    <input id="password" type="password" name="password"
                                           class="form--control md-style @if (gs('secure_password')) secure-password @endif"
                                           required>
                                    <span class="password-show-hide fas fa-eye toggle-password fa-eye-slash"
                                          id="#password"></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-lg-12 col-xl-6">
                            <div class="form-group">
                                <label for="confirm-password" class="form--label">@lang('Confirm Password')</label>
                                <div class="position-relative">
                                    <input id="confirm-password" type="password" class="form--control md-style"
                                           name="password_confirmation" required>
                                    <span class="password-show-hide fas fa-eye toggle-password fa-eye-slash"
                                          id="#confirm-password"></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <x-captcha label="form--label" class="true" />
                        </div>
                    </div>
                    @if (gs('agree'))
                        @php
                            $policyPages = getContent('policy_pages.element', false, orderById: true);
                        @endphp
                        <div class="mb-4">
                            <div class="form--check">
                                <input class="form-check-input" name="agree" type="checkbox" @checked(old('agree'))
                                       id="remember" required>
                                <div class="form-check-label ms-2">
                                    <label class="" for="remember">
                                        @lang('I agree with')
                                        @foreach ($policyPages as $policy)
                                            <a href="{{ route('policy.pages', $policy->slug) }}" class="text--base"
                                               target="_blank">{{ __($policy->data_values->title) }}</a>
                                            @if (!$loop->last)
                                                ,
                                            @endif
                                        @endforeach
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif

                    <button type="submit" class="btn btn--base btn--md w-100">@lang('Submit')</button>

                    @include('Template::partials.social_login', ['text' => 'Register with'])

                    <p class="account-external text-center mt-4">@lang('Already have an account?')
                        <a href="{{ route('user.login') }}" class="text--base">@lang('Login Now!')</a>
                    </p>
                </form>
            </div>
            <div class="account-left">
                <div class="account-left__thumb">
                    <img class="fit-image" src="{{ frontendImage('register', $registerContent?->data_values?->image ?? '', '960x945') }}" alt="img">
                </div>

                <div class="account-left__content">
                    <div class="account-left__content-top">
                        <div class="account-heading">
                            <h3 class="account-heading__title">{{ __($registerContent?->data_values?->heading ?? '') }}</h3>
                            <p class="account-heading__text">
                                {{ __($registerContent?->data_values?->subheading ?? '') }}
                            </p>
                        </div>

                        <div class="banner-content-rating mb-0">
                            <div class="banner-content-rating-thumb">
                                @foreach ($bannerElement as $banner)
                                    <img src="{{ frontendImage('banner', $banner?->data_values?->image ?? '', '60x60') }}" alt="img" />
                                @endforeach
                            </div>
                            <div class="banner-content-rating-content">
                                <div class="banner-content-rating-star">
                                    <ul class="star-list">
                                        @php
                                            echo showRatings($bannerContent?->data_values?->review_rating ?? 5);
                                        @endphp
                                    </ul>
                                    <span class="count">{{ __($bannerContent?->data_values?->rating_text ?? '') }}</span>
                                </div>
                                <p class="banner-content-rating-text">
                                    {{ __($bannerContent?->data_values?->rating_text ?? '') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="account-left__content-bottom">
                        <div class="testimonial-slider">
                            @foreach ($testimonialElement as $testimonial)
                                <div class="testimonial">
                                    <div class="testimonial-wrapper">
                                        <div class="testimonial-author">
                                            <div class="testimonial-avatar">
                                                <img src="{{ frontendImage('testimonial', $testimonial?->data_values?->image ?? '', '100x100') }}" alt="img">
                                            </div>
                                            <div class="testimonial-person">
                                                <div class="testimonial-name">{{ __($testimonial?->data_values?->name ?? '') }}</div>
                                                <div class="testimonial-job"> {{ __($testimonial?->data_values?->designation ?? '') }}</div>
                                            </div>
                                        </div>
                                        <div class="testimonial-content">
                                            <div class="testimonial-quotemark">
                                                “
                                            </div>
                                            <div class="testimonial-quote">
                                                {{ __($testimonial->data_values?->review ?? '') }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal custom--modal fade" id="existModalCenter" tabindex="-1" role="dialog"
             aria-labelledby="existModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="existModalLongTitle">@lang('You are with us')</h5>
                        <span type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                            <i class="las la-times"></i>
                        </span>
                    </div>
                    <div class="modal-body">
                        <p class="text-center">@lang('You already have an account please Login ')</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--danger btn--sm"
                                data-bs-dismiss="modal">@lang('Close')</button>
                        <a href="{{ route('user.login') }}" class="btn btn--base btn--sm">@lang('Login')</a>
                    </div>
                </div>
            </div>
        </div>
    @else
        @include('Template::partials.registration_disabled')
    @endif
@endsection
@if (gs('registration'))

    @push('style')
        <style>
            .social-login-btn {
                border: 1px solid #cbc4c4;
            }

            .hover-input-popup .input-popup .error {
                color: hsl(var(--white)/0.5);
            }
        </style>
    @endpush

    @if (gs('secure_password'))
        @push('script-lib')
            <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
        @endpush
    @endif

    @push('script')
        <script>
            "use strict";
            (function($) {

                $('.checkUser').on('focusout', function(e) {
                    var url = '{{ route('user.checkUser') }}';
                    var value = $(this).val();
                    var token = '{{ csrf_token() }}';

                    var data = {
                        email: value,
                        _token: token
                    }

                    $.post(url, data, function(response) {
                        if (response.data != false) {
                            $('#existModalCenter').modal('show');
                        }
                    });
                });
            })(jQuery);
        </script>
    @endpush

@endif
