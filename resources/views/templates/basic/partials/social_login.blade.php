@props(['text' => 'Login with'])
@if (gs('socialite_credentials')->linkedin->status || gs('socialite_credentials')->facebook->status == Status::ENABLE || gs('socialite_credentials')->google->status == Status::ENABLE)
    <div class="social-auth mb-4">
        <div class="social-auth__divide">
            <span>@lang('OR')</span>
        </div>
        <ul class="social-auth__list mt-4">
            @if (gs('socialite_credentials')->google->status == Status::ENABLE)
                <li class="social-auth__item">
                    <a class="social-auth__link" href="{{ route('user.social.login', 'google') }}">
                        <img src="{{ asset(activeTemplate(true) . 'images/google.svg') }}" alt="">
                        <span class="text">{{ $text }} @lang('Google')</span>
                    </a>
                </li>
            @endif
            @if (gs('socialite_credentials')->facebook->status == Status::ENABLE)
                <li class="social-auth__item">
                    <a class="social-auth__link" href="{{ route('user.social.login', 'facebook') }}">
                        <img src="{{ asset(activeTemplate(true) . 'images/facebook.svg') }}" alt="">
                        <span class="text">{{ $text }} @lang('Facebook')</span>
                    </a>
                </li>
            @endif
            @if (gs('socialite_credentials')->linkedin->status == Status::ENABLE)
                <li class="social-auth__item">
                    <a class="social-auth__link" href="{{ route('user.social.login', 'linkedin') }}">
                        <img src="{{ asset(activeTemplate(true) . 'images/linkdin.svg') }}" alt="img">
                        <span class="text">{{ $text }} @lang('Linkedin')</span>
                    </a>
                </li>
            @endif
        </ul>
    </div>
@endif
