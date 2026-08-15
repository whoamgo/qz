@php
    $navItems = [
        ['label' => __('Home'),            'route' => 'home',                  'icon' => 'bi-house'],
        ['label' => __('Quizzes'),         'route' => 'website.quizzes',               'icon' => 'bi-patch-question'],
        ['label' => 'Categories',         'route' => 'website.categories',               'icon' => 'bi-patch-question'],
        ['label' => __('Current Affairs'), 'route' => 'website.current.affairs.index', 'icon' => 'bi-newspaper'],
        ['label' => __('GK'),              'route' => 'website.category.show',         'icon' => 'bi-lightbulb', 'param' => 'general-knowledge'],
        ['label' => __('Play Live'),       'route' => 'website.play.live',             'icon' => 'bi-controller'],
        ['label' => __('Leaderboard'),     'route' => 'website.leaderboard',           'icon' => 'bi-trophy'],
        ['label' => __('Blog'),            'route' => 'blog',                  'icon' => 'bi-journal-text'],
        ['label' => __('Contact'),         'route' => 'contact',               'icon' => 'bi-envelope'],
    ];

    $wUser = auth()->user();
    $wXp   = $wUser?->xpProfile;
@endphp

<header class="w-header">
    <div class="container">
        <div class="d-flex align-items-center gap-3 py-2">

            {{-- Mobile menu trigger --}}
            <button class="btn w-btn-outline d-lg-none px-2" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#wMobileMenu"
                    aria-controls="wMobileMenu" aria-label="Open navigation menu">
                <i class="bi bi-list fs-4"></i>
            </button>

            <a href="{{ route('home') }}" class="w-logo notranslate d-flex align-items-center gap-2 flex-shrink-0">
                <img src="{{ getImage(getFilePath('logoIcon') . '/logo.png') }}"
                     alt="{{ gs('site_name') }} logo" width="180" height="55"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                <span style="display:none;">{{ gs('site_name') ?: 'QuizHub' }}</span>
            </a>

            {{-- Desktop navigation --}}
            <nav class="w-desktop-nav d-none d-lg-flex align-items-center gap-1 ms-2" aria-label="Main navigation">
                @foreach ($navItems as $item)
                    @php
                        $url = isset($item['param']) ? route($item['route'], $item['param']) : route($item['route']);
                        $isActive = isset($item['param'])
                            ? request()->is('category/' . $item['param'] . '*')
                            : request()->routeIs($item['route']);
                    @endphp
                    <a href="{{ $url }}" class="w-nav-link {{ $isActive ? 'active' : '' }}"
                       @if($isActive) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>

            {{-- Language switcher: posts to the existing SiteController@changeLanguage --}}
            @php $wLangs = \App\Models\Language::orderBy('name')->get(); @endphp
            @if ($wLangs->count() > 1)
                <div class="dropdown w-lang-switch ms-auto ms-lg-0">
                    <button class="btn w-btn-outline btn-sm dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            aria-label="@lang('Change language')">
                        <i class="bi bi-translate" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline ms-1">
                            {{ strtoupper(session('lang', 'en')) }}
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        @foreach ($wLangs as $lang)
                            <li>
                                <a class="dropdown-item wLangOption d-flex align-items-center gap-2 {{ session('lang', 'en') === $lang->code ? 'active' : '' }}"
                                   data-lang="{{ $lang->code }}"
                                   href="{{ route('lang', $lang->code) }}">
                                    <span class="w-lang-code notranslate">{{ strtoupper($lang->code) }}</span>
                                    <span>{{ __($lang->name) }}</span>
                                    @if (session('lang', 'en') === $lang->code)
                                        <i class="bi bi-check2 ms-auto" aria-hidden="true"></i>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Account area --}}
            <div class="d-flex align-items-center gap-2 ms-auto ms-lg-0 flex-shrink-0">
                @guest
                    <a href="{{ route('user.login') }}" class="btn w-btn-outline btn-sm d-none d-sm-inline-flex">@lang('Login')</a>
                    <a href="{{ route('user.register') }}" class="btn w-btn-primary btn-sm">@lang('Register')</a>
                @else
                    <span class="w-xp-pill notranslate d-none d-md-inline-flex" title="Total XP">
                        <i class="bi bi-lightning-charge-fill" aria-hidden="true"></i>
                        {{ number_format($wXp->total_xp ?? 0) }} XP
                    </span>
                    <span class="w-level-pill notranslate d-none d-lg-inline-flex" title="Current level">
                        <i class="bi bi-star-fill" aria-hidden="true"></i>
                        Lv {{ $wXp->current_level ?? 1 }}
                    </span>

                    <div class="dropdown">
                        <button class="btn p-0 border-0 bg-transparent" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false"
                                aria-label="Account menu">
                            <img class="w-avatar"
                                 src="{{ getImage(getFilePath('userProfile') . '/' . $wUser->image, getFileSize('userProfile')) }}"
                                 alt="{{ $wUser->fullname ?? $wUser->username }}" width="36" height="36">
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" style="min-width: 232px;">
                            <li class="px-3 py-2 border-bottom">
                                <strong class="d-block text-truncate">{{ $wUser->fullname ?? $wUser->username }}</strong>
                                <small class="w-muted notranslate">&#64;{{ $wUser->username }}</small>
                            </li>
                            <li><a class="dropdown-item" href="{{ route('website.profile.index') }}"><i class="bi bi-person me-2"></i>@lang('Profile')</a></li>
                            <li><a class="dropdown-item" href="{{ route('website.profile.quizzes') }}"><i class="bi bi-list-check me-2"></i>@lang('My Quizzes')</a></li>
                            <li><a class="dropdown-item" href="{{ route('website.profile.progress') }}"><i class="bi bi-graph-up me-2"></i>@lang('My Progress')</a></li>
                            <li><a class="dropdown-item" href="{{ route('website.profile.xp') }}"><i class="bi bi-lightning-charge me-2"></i>@lang('XP History')</a></li>
                            <li><a class="dropdown-item" href="{{ route('website.profile.badges') }}"><i class="bi bi-award me-2"></i>@lang('Badges')</a></li>
                            <li><a class="dropdown-item" href="{{ route('website.profile.streak') }}"><i class="bi bi-fire me-2"></i>@lang('Streak')</a></li>
                            <li><a class="dropdown-item" href="{{ route('website.profile.bookmarks') }}"><i class="bi bi-bookmark me-2"></i>@lang('Bookmarks')</a></li>
                            <li><a class="dropdown-item" href="{{ route('website.profile.settings') }}"><i class="bi bi-gear me-2"></i>@lang('Settings')</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="{{ route('user.logout') }}"><i class="bi bi-box-arrow-right me-2"></i>@lang('Logout')</a></li>
                        </ul>
                    </div>
                @endguest
            </div>
        </div>
    </div>
</header>

{{-- Mobile offcanvas navigation --}}
<div class="offcanvas offcanvas-start w-offcanvas" tabindex="-1" id="wMobileMenu" aria-labelledby="wMobileMenuLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="wMobileMenuLabel">@lang('Menu')</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
    </div>
    <div class="offcanvas-body">

        <div class="p-3 border-bottom">
            <form action="{{ route('website.search') }}" method="GET" role="search">
                <div class="position-relative">
                    <i class="bi bi-search w-search-icon" aria-hidden="true"></i>
                    <input type="search" name="q" class="form-control w-search-input"
                           placeholder="Search quizzes..." value="{{ request('q') }}"
                           aria-label="Search quizzes">
                </div>
            </form>
        </div>

        @foreach ($navItems as $item)
            @php $url = isset($item['param']) ? route($item['route'], $item['param']) : route($item['route']); @endphp
            <a href="{{ $url }}" class="w-mobile-link {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i> {{ $item['label'] }}
            </a>
        @endforeach

        @auth
            <div class="px-3 py-3 bg-light border-top border-bottom d-flex align-items-center gap-2">
                <span class="w-xp-pill"><i class="bi bi-lightning-charge-fill"></i> {{ number_format($wXp->total_xp ?? 0) }} XP</span>
                <span class="w-level-pill"><i class="bi bi-star-fill"></i> Lv {{ $wXp->current_level ?? 1 }}</span>
            </div>
            <a href="{{ route('website.profile.index') }}" class="w-mobile-link"><i class="bi bi-person"></i> Profile</a>
            <a href="{{ route('website.profile.quizzes') }}" class="w-mobile-link"><i class="bi bi-list-check"></i> My Quizzes</a>
            <a href="{{ route('website.profile.badges') }}" class="w-mobile-link"><i class="bi bi-award"></i> Badges</a>
            <a href="{{ route('website.profile.bookmarks') }}" class="w-mobile-link"><i class="bi bi-bookmark"></i> Bookmarks</a>
            <a href="{{ route('user.logout') }}" class="w-mobile-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        @else
            <div class="p-3 d-grid gap-2">
                <a href="{{ route('user.login') }}" class="btn w-btn-outline">@lang('Login')</a>
                <a href="{{ route('user.register') }}" class="btn w-btn-primary">@lang('Create free account')</a>
            </div>
        @endauth
    </div>
</div>
