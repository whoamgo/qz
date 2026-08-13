@extends('Template::layouts.frontend')
@section('content')
    @php
        $contactContent = getContent('contact_us.content', true);
        $contactElement = getContent('contact_us.element', false, null, true);
        $socialIcons = getContent('social_icon.element', orderById: true);
    @endphp
    <section class="contact-section my-120">
        <div class="container">
            <div class="row gy-4 mb-60">
                <div class="col-md-6 col-lg-4">
                    <div class="contact-info">
                        <div class="contact-info__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" class="lucide lucide-mail-icon lucide-mail">
                                <path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"></path>
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                            </svg>
                        </div>
                        <h4 class="contact-info__title">@lang('Email Us')</h4>
                        <p class="contact-info__desc">{{ __($contactContent?->data_values?->email_address_description ?? '') }}</p>
                        <a class="contact-info__link" href="mailto:{{ $contactContent?->data_values?->email_address ?? '' }}">
                            {{ __($contactContent?->data_values?->email_address ?? '') }}
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" class="lucide lucide-move-right-icon lucide-move-right">
                                <path d="M18 8L22 12L18 16"></path>
                                <path d="M2 12H22"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="contact-info">
                        <div class="contact-info__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" class="lucide lucide-phone-icon lucide-phone">
                                <path
                                      d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384">
                                </path>
                            </svg>
                        </div>
                        <h4 class="contact-info__title">@lang('Call Us')</h4>
                        <p class="contact-info__desc">{{ __($contactContent?->data_values?->contact_number_description ?? '') }}</p>
                        <a class="contact-info__link" href="tel:{{ $contactContent?->data_values?->contact_number ?? '' }}">
                            {{ __($contactContent?->data_values?->contact_number ?? '') }}
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" class="lucide lucide-move-right-icon lucide-move-right">
                                <path d="M18 8L22 12L18 16"></path>
                                <path d="M2 12H22"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="contact-info">
                        <div class="contact-info__icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" class="lucide lucide-map-pin-icon lucide-map-pin">
                                <path
                                      d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0">
                                </path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </div>
                        <h4 class="contact-info__title">@lang('Find Us')</h4>
                        <p class="contact-info__desc">{{ __($contactContent?->data_values?->contact_address_description ?? '') }}</p>
                        <a class="contact-info__link" href="javascript:void(0)">
                            {{ __($contactContent?->data_values?->contact_address ?? '') }}
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                 stroke-linejoin="round" class="lucide lucide-arrow-up-right-icon lucide-arrow-up-right">
                                <path d="M7 7h10v10"></path>
                                <path d="M7 17 17 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>

            <div class="row gy-5 justify-content-between">
                <div class="col-lg-6">
                    <img class="contact-thumb wow fadeInLeft" data-wow-delay="0.2s"
                         src="{{ frontendImage('contact_us', $contactContent?->data_values?->image ?? '', '640x535') }}" alt="contact">
                </div>
                <div class="col-lg-6">
                    <div class="contact-form wow fadeInRight" data-wow-delay="0.2s">
                        <form method="POST" class="verify-gcaptcha">
                            @csrf
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="form--label">@lang('Name')</label>
                                        <input type="text" name="name" class="form--control md-style"
                                               value="{{ old('name', $user?->fullname) }}"
                                               @if ($user && $user->profile_complete) readonly @endif required>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label class="form--label">@lang('Email Address')</label>
                                        <input name="email" type="email" class="form--control md-style"
                                               value="{{ old('email', $user?->email) }}"
                                               @if ($user) readonly @endif required>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group">
                                        <label class="form--label">@lang('Subject')</label>
                                        <input type="text" name="subject" class="form--control md-style"
                                               value="{{ old('subject') }}" required>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <div class="form-group">
                                        <label class="form--label">@lang('Message')</label>
                                        <textarea class="form--control md-style" name="message" required>{{ old('message') }}</textarea>
                                    </div>
                                </div>
                                <div class="col-sm-12">
                                    <x-captcha label="form--label" class="true" />
                                </div>
                                <div class="col-sm-12">
                                    <button type="submit" class="btn btn--base w-100">@lang('Send Messages')</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="my-120">
        <div class="container">
            <div class="contact-map">
                <iframe src="{{ $contactContent?->data_values?->map_link ?? '' }}" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
    </section>


    @if (isset($sections->secs) && $sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
        @endforeach
    @endif
@endsection
