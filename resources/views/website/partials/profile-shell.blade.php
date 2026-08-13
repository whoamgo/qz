{{-- Profile hero + sidebar nav, shared by every dashboard tab. --}}
@php
    $tabs = [
        'index'     => ['Profile', 'bi-person'],
        'quizzes'   => ['My Quizzes', 'bi-list-check'],
        'progress'  => ['Progress', 'bi-graph-up'],
        'xp'        => ['XP History', 'bi-lightning-charge'],
        'badges'    => ['Badges', 'bi-award'],
        'streak'    => ['Streak', 'bi-fire'],
        'bookmarks' => ['Bookmarks', 'bi-bookmark'],
        'settings'  => ['Settings', 'bi-gear'],
    ];
@endphp

<div class="w-profile-hero">
    <div class="d-flex flex-wrap align-items-center gap-4">
        <img class="w-profile-avatar"
             src="{{ getImage(getFilePath('userProfile') . '/' . $user->image, getFileSize('userProfile')) }}"
             alt="" width="88" height="88">
        <div class="flex-grow-1">
            <h1>{{ $user->fullname ?? $user->username }}</h1>
            <p class="mb-1 text-white-50">
                &#64;{{ $user->username }}
                <span class="mx-1" aria-hidden="true">&middot;</span>
                <span class="text-break">{{ $user->email }}</span>
            </p>
            <p class="mb-3 text-white-50 w-text-xs">
                Member since {{ showDateTime($user->created_at, 'M Y') }}
            </p>
            <div class="d-flex flex-wrap gap-2">
                <span class="w-badge" style="background: rgba(255,255,255,.2); color:#fff; border:0;">
                    <i class="bi bi-star-fill"></i> Level {{ $stats['level'] }}
                </span>
                <span class="w-badge" style="background: rgba(255,255,255,.2); color:#fff; border:0;">
                    <i class="bi bi-lightning-charge-fill"></i> {{ number_format($stats['total_xp']) }} XP
                </span>
                <span class="w-badge" style="background: rgba(255,255,255,.2); color:#fff; border:0;">
                    <i class="bi bi-fire"></i> {{ $stats['streak'] }} day streak
                </span>
                <span class="w-badge" style="background: rgba(255,255,255,.2); color:#fff; border:0;">
                    <i class="bi bi-award-fill"></i> {{ $stats['badges'] }} badges
                </span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 align-items-start">
    <div class="col-lg-3">
        <nav class="w-profile-nav" aria-label="Profile sections">
            @foreach ($tabs as $key => [$label, $icon])
                <a href="{{ route('website.profile.' . $key) }}"
                   class="{{ request()->routeIs('website.profile.' . $key) ? 'active' : '' }}"
                   @if(request()->routeIs('website.profile.' . $key)) aria-current="page" @endif>
                    <i class="bi {{ $icon }}" aria-hidden="true"></i> {{ $label }}
                </a>
            @endforeach
        </nav>
    </div>
    <div class="col-lg-9">
        @yield('profile-content')
    </div>
</div>
