@extends('website.layouts.app')

@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'About' => route('website.about')]" />
@endsection

@section('content')

{{-- Hero --}}
<section class="w-section w-section-alt">
    <div class="container text-center" style="max-width: 760px;">
        <span class="w-badge w-badge-primary mb-2"><i class="bi bi-mortarboard"></i> About Us</span>
        <h1 class="mb-2">About {{ $siteName }}</h1>
        <p class="w-muted" style="font-size: var(--w-fs-lg);">{{ $siteName }} — Learn. Play. Compete.</p>

        <div class="w-article-body mt-3">
            <p class="w-muted">
                {{ $siteName }} is a free, India-focused quiz platform that helps students and aspirants
                practise for competitive exams and general knowledge. From daily current affairs to full
                mock tests, every quiz comes with instant results and written explanations — so you learn
                the reasoning, not just the answer.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
            <a href="{{ route('website.quizzes') }}" class="btn w-btn-primary btn-lg px-4"><i class="bi bi-play-circle-fill me-2"></i>Browse Quizzes</a>
            <a href="{{ route('website.play.live') }}" class="btn w-btn-outline btn-lg px-4"><i class="bi bi-controller me-2"></i>Play Live</a>
        </div>
    </div>
</section>

{{-- Stats --}}
<section class="w-section">
    <div class="container">
        <div class="row g-3">
            @foreach ([
                ['bi-patch-question-fill', number_format($stats['quizzes']), 'Published Quizzes'],
                ['bi-card-checklist', number_format($stats['questions']), 'Practice Questions'],
                ['bi-grid-3x3-gap-fill', number_format($stats['categories']), 'Categories & Topics'],
                ['bi-people-fill', number_format($stats['members']), 'Registered Learners'],
            ] as [$icon, $num, $label])
                <div class="col-6 col-lg-3">
                    <div class="w-stat-tile h-100">
                        <strong><i class="bi {{ $icon }} text-primary"></i> {{ $num }}</strong>
                        <span>{{ $label }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Mission --}}
<section class="w-section w-section-alt">
    <div class="container" style="max-width: 820px;">
        <div class="w-section-head text-center d-block"><div class="mx-auto"><h2>Our Mission</h2></div></div>
        <p class="w-muted text-center" style="font-size: var(--w-fs-lg);">
            Quality exam preparation is often expensive or locked behind paywalls. Our mission is to make it
            <strong>free and accessible for every learner in India</strong> — with well-explained questions,
            honest scoring, and a fun, gamified experience that keeps you coming back.
        </p>
    </div>
</section>

{{-- What we offer --}}
<section class="w-section">
    <div class="container">
        <div class="w-section-head text-center d-block"><div class="mx-auto" style="max-width:620px;">
            <h2>What We Offer</h2>
            <p>Everything you need to practise, track progress and compete.</p>
        </div></div>
        <div class="row g-3">
            @foreach ([
                ['bi-lightbulb-fill', 'General Knowledge', 'Thousands of GK questions across history, geography, polity, science and more.'],
                ['bi-newspaper', 'Current Affairs', 'Daily, weekly and monthly current affairs quizzes to stay exam-ready.'],
                ['bi-mortarboard-fill', 'Competitive Exams', 'Focused practice for SSC, Banking, Railway, UPSC, Defence, State PSC and Teaching.'],
                ['bi-clipboard-check-fill', 'Mock Tests', 'Full-length, timed mock tests that mirror the real exam experience.'],
                ['bi-calendar-check-fill', 'Daily Quizzes', 'A fresh quiz every day to build a consistent study habit and streak.'],
                ['bi-controller', 'Play Live (Multiplayer)', 'Create a room, share a code and compete with friends on a live leaderboard.'],
            ] as [$icon, $title, $text])
                <div class="col-md-6 col-lg-4">
                    <div class="w-card h-100"><div class="w-card-body">
                        <span class="w-cat-icon m-0 mb-2" style="width:46px;height:46px;font-size:1.2rem;"><i class="bi {{ $icon }}"></i></span>
                        <h3 class="w-card-title" style="font-size: var(--w-fs-base);">{{ $title }}</h3>
                        <p class="w-text-sm w-muted mb-0">{{ $text }}</p>
                    </div></div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- How it works --}}
<section class="w-section w-section-alt">
    <div class="container">
        <div class="w-section-head text-center d-block"><div class="mx-auto"><h2>How It Works</h2></div></div>
        <div class="row g-3 g-lg-4">
            @foreach ([
                ['bi-book-half', 'Learn', 'Attempt quizzes with written explanations for every question, so you understand the concept.'],
                ['bi-controller', 'Play', 'Earn XP, unlock badges and keep your daily streak alive — solo or live with friends.'],
                ['bi-trophy-fill', 'Compete', 'Climb the leaderboard and track your accuracy per topic to see where to improve.'],
            ] as $i => [$icon, $title, $text])
                <div class="col-md-4">
                    <div class="w-step-card h-100">
                        <span class="w-step-num">{{ $i + 1 }}</span>
                        <span class="w-step-icon"><i class="bi {{ $icon }}"></i></span>
                        <h3 class="w-step-title">{{ $title }}</h3>
                        <p class="w-step-text">{{ $text }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Gamification + Why choose us --}}
<section class="w-section">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-6">
                <h2 class="mb-3">Gamified Learning</h2>
                <ul class="w-rules">
                    <li><span class="w-rule-num"><i class="bi bi-lightning-charge-fill"></i></span><div><strong>XP &amp; Levels</strong><br><span class="w-text-sm w-muted">Earn experience points for every quiz and correct answer, and level up as you go.</span></div></li>
                    <li><span class="w-rule-num"><i class="bi bi-award-fill"></i></span><div><strong>Badges</strong><br><span class="w-text-sm w-muted">Unlock achievements for streaks, accuracy and milestones.</span></div></li>
                    <li><span class="w-rule-num"><i class="bi bi-fire"></i></span><div><strong>Daily Streaks</strong><br><span class="w-text-sm w-muted">Practise every day to build and protect your streak.</span></div></li>
                    <li><span class="w-rule-num"><i class="bi bi-graph-up-arrow"></i></span><div><strong>Progress Tracking</strong><br><span class="w-text-sm w-muted">See your accuracy per topic and know exactly what to revise next.</span></div></li>
                </ul>
            </div>
            <div class="col-lg-6">
                <h2 class="mb-3">Why Choose Quiz Mitra</h2>
                <ul class="w-rules">
                    <li><span class="w-rule-num"><i class="bi bi-cash-coin"></i></span><div><strong>Free to use</strong><br><span class="w-text-sm w-muted">Practise thousands of questions without paying — free quizzes are clearly labelled.</span></div></li>
                    <li><span class="w-rule-num"><i class="bi bi-chat-square-text-fill"></i></span><div><strong>Real explanations</strong><br><span class="w-text-sm w-muted">Every question has a written explanation so you learn the reasoning.</span></div></li>
                    <li><span class="w-rule-num"><i class="bi bi-phone-fill"></i></span><div><strong>Mobile friendly</strong><br><span class="w-text-sm w-muted">Study anywhere — the site works smoothly on phone, tablet and desktop.</span></div></li>
                    <li><span class="w-rule-num"><i class="bi bi-shield-check"></i></span><div><strong>Fair &amp; secure</strong><br><span class="w-text-sm w-muted">Scoring runs on the server and answers are never exposed in the page.</span></div></li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- Exams covered --}}
<section class="w-section w-section-alt">
    <div class="container text-center" style="max-width: 820px;">
        <div class="w-section-head text-center d-block"><div class="mx-auto"><h2>Exams &amp; Subjects We Cover</h2></div></div>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            @foreach (['General Knowledge','Current Affairs','SSC','Banking','Railway','UPSC','Defence','State PSC','Teaching','Reasoning','Computer','Science','History','Geography','Polity','Mock Tests'] as $tag)
                <span class="w-badge w-badge-primary">{{ $tag }}</span>
            @endforeach
        </div>
        <p class="w-muted mt-3">…and many more topics added regularly.</p>
    </div>
</section>

{{-- Final CTA --}}
<section class="w-section">
    <div class="container">
        <div class="w-card" style="background: linear-gradient(135deg,#eef2ff,#fff);">
            <div class="w-card-body text-center py-5">
                <h2 class="mb-2">Start learning today — it's free</h2>
                <p class="w-muted mb-4">Create a free account to earn XP, unlock badges and climb the leaderboard.</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    @guest
                        <a href="{{ route('user.register') }}" class="btn w-btn-primary btn-lg px-4"><i class="bi bi-person-plus-fill me-2"></i>Create Free Account</a>
                    @endguest
                    <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline btn-lg px-4">Browse Quizzes</a>
                    <a href="{{ route('contact') }}" class="btn w-btn-outline btn-lg px-4">Contact Us</a>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
