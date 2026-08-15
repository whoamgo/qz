@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/rooms.css') }}" rel="stylesheet">
@endpush

@section('content')

{{-- Hero --}}
<section class="w-live-hero">
    <div class="container text-center">
        <span class="w-live-tag mb-3"><i class="bi bi-broadcast"></i> Multiplayer</span>
        <h1 class="mb-2">Play Live with Friends</h1>
        <p class="mx-auto mb-4" style="max-width: 600px;">
            Turn any quiz into a live match. Create a room, share a code, and race your friends —
            everyone plays the same quiz and climbs a live leaderboard.
        </p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="{{ route('website.rooms.create') }}" class="btn w-btn-light btn-lg px-4">
                <i class="bi bi-controller me-2"></i>Create a Room
            </a>
            <a href="{{ route('website.rooms.join') }}" class="btn w-live-btn-ghost btn-lg px-4">
                <i class="bi bi-box-arrow-in-right me-2"></i>Join a Room
            </a>
        </div>
        @guest
            <p class="w-text-sm mt-3 mb-0" style="opacity:.85;">Free to play — just <a href="{{ route('user.register') }}" class="text-white text-decoration-underline">create an account</a>.</p>
        @endguest
    </div>
</section>

{{-- How it works --}}
<section class="w-section">
    <div class="container">
        <div class="w-section-head text-center d-block">
            <div class="mx-auto" style="max-width: 620px;">
                <h2>How It Works</h2>
                <p>Six simple steps from creating a room to celebrating a winner.</p>
            </div>
        </div>

        <div class="row g-3 g-lg-4">
            @foreach ([
                ['bi-plus-circle-fill', 'Create Room', 'Pick a category &amp; quiz and set how many players can join.'],
                ['bi-hash', 'Get a Code', 'A unique room code like <strong>QZ7K9P</strong> is generated instantly.'],
                ['bi-people-fill', 'Friends Join', 'They enter your code and land in the live waiting room.'],
                ['bi-play-circle-fill', 'Host Starts', 'When everyone is ready, the host starts the quiz for all.'],
                ['bi-controller', 'Play Together', 'Everyone attempts the same quiz at the same time.'],
                ['bi-trophy-fill', 'Live Leaderboard', 'Ranks update live as players finish, then final results.'],
            ] as $i => [$icon, $title, $text])
                <div class="col-6 col-lg-2">
                    <div class="w-step-card h-100">
                        <span class="w-step-num">{{ $i + 1 }}</span>
                        <span class="w-step-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                        <h3 class="w-step-title" style="font-size: var(--w-fs-base);">{{ $title }}</h3>
                        <p class="w-step-text">{!! $text !!}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Why play live --}}
<section class="w-section w-section-alt">
    <div class="container">
        <div class="w-section-head text-center d-block">
            <div class="mx-auto" style="max-width: 620px;"><h2>Why Play Live</h2></div>
        </div>
        <div class="row g-3">
            @foreach ([
                ['bi-lock-fill', 'Private rooms', 'Only people with your code can join. You control the max players.'],
                ['bi-lightning-charge-fill', 'Same XP &amp; badges', 'You still earn XP and badges from every quiz you play in a room.'],
                ['bi-bar-chart-fill', 'Live standings', 'See who is winning in real time — scores, accuracy and time.'],
                ['bi-phone', 'Play anywhere', 'Fully responsive — host on a laptop, play on your phone.'],
            ] as [$icon, $title, $text])
                <div class="col-sm-6 col-lg-3">
                    <div class="w-card h-100"><div class="w-card-body">
                        <span class="w-cat-icon m-0 mb-2" style="width:44px;height:44px;font-size:1.1rem;"><i class="bi {{ $icon }}"></i></span>
                        <strong class="d-block">{!! $title !!}</strong>
                        <span class="w-text-sm w-muted">{!! $text !!}</span>
                    </div></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Bottom CTA --}}
<section class="w-section">
    <div class="container">
        <div class="w-live-banner">
            <div class="w-live-banner-text">
                <h2 class="mb-2">Ready to challenge your friends?</h2>
                <p class="mb-0">Create a room in seconds and share the code.</p>
            </div>
            <div class="w-live-banner-cta">
                <a href="{{ route('website.rooms.create') }}" class="btn w-btn-light btn-lg px-4"><i class="bi bi-controller me-2"></i>Create a Room</a>
                <a href="{{ route('website.rooms.join') }}" class="btn w-live-btn-ghost btn-lg px-4"><i class="bi bi-box-arrow-in-right me-2"></i>Join a Room</a>
            </div>
        </div>
    </div>
</section>

@endsection
