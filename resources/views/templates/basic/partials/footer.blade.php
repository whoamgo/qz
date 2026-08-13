@php
    $footerContent = getContent('footer.content', true);
    $socialIcons = getContent('social_icon.element', orderById: true);
    $policyPages = getContent('policy_pages.element', false, orderById: true);
    $contactContent = getContent('contact_us.content', true);
@endphp


<footer class="footer">
    <div class="footer-top">
        <div class="container custom--container">
            <div class="row gy-4 gy-sm-5 justify-content-between">
                <div class="col-lg-4 pe-lg-5">
                    <div class="footer-item">
                        <a class="footer-item__logo" href="{{ route('home') }}">
                            <img src="{{ siteLogo() }}" alt="img">
                        </a>
                        <p class="footer-item__desc">
                            {{ __($footerContent?->data_values?->description ?? '') }}
                        </p>

                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 col-xsm-6">
                    <div class="footer-item">
                        <h5 class="footer-item__title">@lang('Useful Links')</h5>
                        <ul class="footer-menu">
                            <li class="footer-menu__item">
                                <a class="footer-menu__link" href="{{ route('home') }}">
                                    @lang('Home')
                                </a>
                            </li>
                            <li class="footer-menu__item">
                                <a class="footer-menu__link" href="{{ route('pricing') }}">
                                    @lang('Pricing')
                                </a>
                            </li>
                            <li class="footer-menu__item">
                                <a class="footer-menu__link" href="{{ route('blog') }}">
                                    @lang('Blogs')
                                </a>
                            </li>
                            <li class="footer-menu__item">
                                <a class="footer-menu__link" href="{{ route('contact') }}">
                                    @lang('Contact')
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 col-xsm-6">
                    <div class="footer-item">
                        <h5 class="footer-item__title">@lang('Policy Links')</h5>
                        <ul class="footer-menu">
                            @foreach ($policyPages as $policy)
                                <li class="footer-menu__item">
                                    <a class="footer-menu__link" href="{{ route('policy.pages', $policy->slug) }}">{{ __($policy->data_values?->title ?? '') }}</a>
                                </li>
                            @endforeach
                            <li class="footer-menu__item">
                                <a class="footer-menu__link" href="{{ route('cookie.policy') }}">
                                    @lang('Cookie Policy')
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="col-lg-3 col-md-4">
                    <div class="footer-item">
                        <h5 class="footer-item__title">@lang('Contact Info')</h5>
                        <ul class="footer-contact">
                            <li class="footer-contact__item">
                                <div class="icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="content">
                                    <p>{{ __($contactContent?->data_values?->contact_address ?? '') }}</p>
                                </div>
                            </li>
                            <li class="footer-contact__item">
                                <div class="icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="content">
                                    <a href="mailto:{{ $contactContent?->data_values?->email_address ?? '' }}" class="link"> {{ $contactContent?->data_values?->email_address ?? '' }} </a>
                                </div>
                            </li>
                            <li class="footer-contact__item">
                                <div class="icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div class="content">
                                    <a href="tel:{{ $contactContent?->data_values?->contact_number ?? '' }}" class="link"> {{ $contactContent?->data_values?->contact_number ?? '' }} </a>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <div class="flex-between gap-2">
                <ul class="social-list">
                    @foreach ($socialIcons as $social)
                        <li class="social-list__item">
                            <a href="{{ $social?->data_values?->url ?? '' }}" class="social-list__link" target="_blank">
                                @php
                                    echo $social?->data_values?->social_icon ?? '';
                                @endphp
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="footer-bottom__text">
                    @lang('Copyright') &copy; <a href="{{ route('home') }}" class="text-white">{{ __(gs('site_name')) }}</a> {{ date('Y') }} @lang('All Right Reserved.')
                </p>
            </div>
        </div>
    </div>
</footer>
