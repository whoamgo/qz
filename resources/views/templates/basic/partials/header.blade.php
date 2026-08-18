<header class="header" id="header">
    <div class="container">
        <nav class="navbar navbar-expand-xl navbar-light">
            <a class="navbar-brand logo" href="{{ route('home') }}">
                <img src="{{ siteLogo() }}" alt="img" />
            </a>
            <button class="navbar-toggler header-button" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#offcanvasDarkNavbar" aria-controls="offcanvasDarkNavbar"
                    aria-label="Toggle navigation">
                <span id="hiddenNav"><i class="las la-bars"></i></span>
            </button>

            <div class="offcanvas border-0 offcanvas-end" tabindex="-1" id="offcanvasDarkNavbar">
                <div class="offcanvas-header">
                    <a class="logo navbar-brand" href="{{ route('home') }}">
                        <img src="{{ siteLogo() }}" alt="img" />
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav nav-menu align-items-xl-center justify-content-center w-100">
                        <li class="nav-item d-xl-none">
                            @if (gs('multi_language'))
                                @include('Template::partials.language')
                            @endif
                            @auth
                                @include('Template::partials.auth_nav')
                            @endauth
                        </li>

                        <li class="nav-item {{ menuActive('home') }}">
                            <a class="nav-link" aria-current="page" href="{{ route('home') }}">@lang('Home')</a>
                        </li>
                        @foreach ($pages as $k => $data)
                            <li class="nav-item @if ($data->slug == Request::segment(1)) active @endif">
                                <a class="nav-link" aria-current="page" href="{{ route('pages', $data->slug) }}">{{ __($data->name) }}</a>
                            </li>
                        @endforeach
                        {{-- Pricing page retired - route commented in web.php.
                        <li class="nav-item {{ menuActive('pricing') }}">
                            <a class="nav-link" href="{{ route('pricing') }}">@lang('Pricing')</a>
                        </li>
                        --}}
                        <li class="nav-item {{ menuActive('exams') }}">
                            <a class="nav-link" href="{{ route('exams') }}">@lang('Exams')</a>
                        </li>
                        <li class="nav-item {{ menuActive('blog') }}">
                            <a class="nav-link" href="{{ route('blog') }}">@lang('Blog')</a>
                        </li>
                        <li class="nav-item {{ menuActive('contact') }}">
                            <a class="nav-link" href="{{ route('contact') }}">@lang('Contact')</a>
                        </li>


                        @guest
                            <li class="nav-item d-xl-none d-block">
                                <a href="{{ route('user.login') }}" class="btn w-100 btn--md btn--base pill">
                                    <span class="icon">
                                        <i class="fa-regular fa-circle-user"></i>
                                    </span>
                                    @lang('Login')
                                </a>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>

            <div class="header-right d-none d-xl-flex">
                @if (gs('multi_language'))
                    @include('Template::partials.language')
                @endif
                @auth
                    @include('Template::partials.auth_nav')
                @else
                    <a href="{{ route('user.login') }}" class="btn btn--md btn--base pill">
                        @lang('Login')
                    </a>
                    <a href="{{ route('user.register') }}" class="btn btn--md btn-outline--base pill">
                        @lang('Register')
                    </a>
                @endauth
            </div>
        </nav>
    </div>
</header>
