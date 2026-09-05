@extends('website.layouts.app')

@section('content')

    @php
        // Independence Day (15 Aug) festive theme — shows for a short window
        // around the day, then removes itself automatically.
        $isIndependence = now()->month === 8 && now()->day >= 10 && now()->day <= 16;
    @endphp

    {{-- 1. Hero + search --}}
    <section class="w-hero @if ($isIndependence) w-independence @endif">
        <div class="container w-hero-inner">
            <div class="row align-items-center g-5">
                <div class="col-lg-7">
                    @if ($isIndependence)
                        <span class="w-independence-badge">🇮🇳 Happy Independence Day</span>
                    @endif
                    <h1>Free Online Quizzes for GK & Competitive Exams</h1>
                    <p class="mb-4">
                        Practice GK, Current Affairs and Competitive Exam quizzes — earn XP, unlock
                        badges, and now <strong>play live with friends</strong> in real-time quiz rooms.
                    </p>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if ($todayQuiz)
                            <a href="{{ route('website.quiz.show', $todayQuiz->slug) }}" class="btn w-btn-light btn-lg px-4">
                                <i class="bi bi-play-circle-fill me-2" aria-hidden="true"></i>Start Today's Quiz
                            </a>
                        @else
                            <a href="{{ route('website.quizzes') }}" class="btn w-btn-light btn-lg px-4">
                                <i class="bi bi-play-circle-fill me-2" aria-hidden="true"></i>Browse Quizzes
                            </a>
                        @endif
                        <a href="{{ route('website.play.live') }}" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-controller me-2" aria-hidden="true"></i>Play Live <span class="w-hero-new">New</span>
                        </a>
                        <a href="{{ route('exams') }}" class="btn btn-outline-light btn-lg px-4">Explore Exams</a>
                    </div>

                    {{-- Trust badges (real, cached numbers) --}}
                    <div class="w-hero-stats mb-4">
                        <span><strong>{{ number_format($heroStats['quizzes']) }}+</strong> Quizzes</span>
                        <span><strong>{{ number_format($heroStats['questions']) }}+</strong> Questions</span>
                        <span><strong>{{ number_format($heroStats['members']) }}+</strong> Learners</span>
                    </div>

                    {{-- Hero search with live suggestions --}}
                    <div class="w-search-wrap w-hero-search" style="max-width: 520px;">
                        <form action="{{ route('website.search') }}" method="GET" role="search">
                            <i class="bi bi-search w-search-icon" aria-hidden="true"></i>
                            <input type="search" name="q" class="form-control form-control-lg w-search-input"
                                   placeholder="Search a topic or quiz..." autocomplete="off"
                                   value="{{ request('q') }}" aria-label="Search quizzes"
                                   aria-autocomplete="list" aria-controls="wHeroSuggest"
                                   data-suggest-url="{{ route('website.search.suggest') }}">
                        </form>
                        <div class="w-suggest" id="wHeroSuggest" role="listbox" aria-label="Search suggestions"></div>
                    </div>
                </div>

                <div class="col-lg-5">
                    @if ($heroSlides->count())
                        {{-- Admin-managed banner slider (Frontend Manager > Banner). --}}
                        <div id="wHeroSlider" class="w-hero-slider carousel slide carousel-fade"
                             data-bs-ride="carousel" data-bs-interval="4500" data-bs-pause="hover">

                            <div class="carousel-inner">
                                @foreach ($heroSlides as $i => $slide)
                                    <div class="carousel-item @if($i === 0) active @endif">
                                        <img src="{{ $slide->image }}"
                                             alt="{{ $slide->title ?: 'Banner ' . ($i + 1) }}"
                                             loading="{{ $i === 0 ? 'eager' : 'lazy' }}"
                                             decoding="async">
                                    </div>
                                @endforeach
                            </div>

                            @if ($heroSlides->count() > 1)
                                <button class="carousel-control-prev" type="button"
                                        data-bs-target="#wHeroSlider" data-bs-slide="prev">
                                    <span class="w-hero-slider-arrow"><i class="bi bi-chevron-left" aria-hidden="true"></i></span>
                                    <span class="visually-hidden">@lang('Previous slide')</span>
                                </button>
                                <button class="carousel-control-next" type="button"
                                        data-bs-target="#wHeroSlider" data-bs-slide="next">
                                    <span class="w-hero-slider-arrow"><i class="bi bi-chevron-right" aria-hidden="true"></i></span>
                                    <span class="visually-hidden">@lang('Next slide')</span>
                                </button>

                                <div class="carousel-indicators w-hero-slider-dots">
                                    @foreach ($heroSlides as $i => $slide)
                                        <button type="button" data-bs-target="#wHeroSlider"
                                                data-bs-slide-to="{{ $i }}"
                                                @if($i === 0) class="active" aria-current="true" @endif
                                                aria-label="@lang('Slide') {{ $i + 1 }}"></button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- No banners published yet: keep the original stat tiles. --}}
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="w-hero-stat">
                                    <strong>{{ number_format($categories->count()) }}</strong>
                                    <span>Categories</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="w-hero-stat">
                                    <strong>{{ number_format($popularQuizzes->count() + $latestQuizzes->count()) }}+</strong>
                                    <span>Quizzes</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="w-hero-stat">
                                    <strong><i class="bi bi-lightning-charge-fill"></i></strong>
                                    <span>Earn XP &amp; Levels</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="w-hero-stat">
                                    <strong><i class="bi bi-award-fill"></i></strong>
                                    <span>Unlock Badges</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- 1b. Play Live banner (compact teaser → dedicated landing page) --}}
    <section class="w-section" style="padding-top: var(--w-space-6); padding-bottom: 0;">
        <div class="container">
            <div class="w-live-banner">
                <div class="w-live-banner-text">
                    <span class="w-live-tag"><i class="bi bi-broadcast"></i> New · Multiplayer</span>
                    <h2>Play Live with Friends</h2>
                    <p>Create a room, share a code, and race friends through the same quiz — with a live leaderboard.</p>
                    <div class="w-live-chips">
                        <span>Create</span><i class="bi bi-chevron-right"></i>
                        <span>Share Code</span><i class="bi bi-chevron-right"></i>
                        <span>Play</span><i class="bi bi-chevron-right"></i>
                        <span>Win</span>
                    </div>
                </div>
                <div class="w-live-banner-cta">
                    <a href="{{ route('website.rooms.create') }}" class="btn w-btn-light btn-lg px-4">
                        <i class="bi bi-controller me-2"></i>Create a Room
                    </a>
                    <a href="{{ route('website.rooms.join') }}" class="btn w-live-btn-ghost btn-lg px-4">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Join a Room
                    </a>
                    <a href="{{ route('website.play.live') }}" class="w-live-how">See how it works <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    {{-- 1c. Prepare by Exam & Subject — explore grid (SEO deep internal linking) --}}
    <section class="w-section">
        <div class="container">
            <div class="w-section-head text-center d-block">
                <div>
                    <h2>Prepare by Exam &amp; Subject</h2>
                    <p>Jump straight to what you're preparing for — competitive exams, core subjects, practice modes and rewards.</p>
                </div>
            </div>

            <div class="hx-grid4">
                <div class="hx-col">
                    <div class="hx-col-head">
                        <span class="hx-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                        <h3>Competitive Exams</h3>
                    </div>
                    <ul class="hx-list">
                        <li><a href="{{ route('exams') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> SSC — CGL, CHSL &amp; GD</a></li>
                        <li><a href="{{ route('exams') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> UPSC Civil Services</a></li>
                        <li><a href="{{ route('exams') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Banking — IBPS &amp; SBI</a></li>
                        <li><a href="{{ route('exams') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Railway — RRB NTPC</a></li>
                        <li><a href="{{ route('exams') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Defence — NDA &amp; CDS</a></li>
                    </ul>
                </div>

                <div class="hx-col">
                    <div class="hx-col-head">
                        <span class="hx-icon"><i class="bi bi-journal-bookmark-fill" aria-hidden="true"></i></span>
                        <h3>Core Subjects</h3>
                    </div>
                    <ul class="hx-list">
                        <li><a href="{{ route('website.category.show', 'general-knowledge') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> General Knowledge</a></li>
                        <li><a href="{{ route('website.current.affairs.index') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Current Affairs</a></li>
                        <li><a href="{{ route('website.category.show', 'reasoning') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Reasoning &amp; Aptitude</a></li>
                        <li><a href="{{ route('website.category.show', 'computer-technology') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Computer &amp; Technology</a></li>
                        <li><a href="{{ route('website.category.show', 'world-quiz') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> World &amp; Geography</a></li>
                    </ul>
                </div>

                <div class="hx-col">
                    <div class="hx-col-head">
                        <span class="hx-icon"><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i></span>
                        <h3>Practice Modes</h3>
                    </div>
                    <ul class="hx-list">
                        <li><a href="{{ route('website.quizzes') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> All Quizzes</a></li>
                        <li><a href="{{ route('website.mock.tests') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Mock Tests</a></li>
                        <li><a href="{{ route('website.pyq') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Previous Year Questions</a></li>
                        <li><a href="{{ route('website.current.affairs.today') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Today's Current Affairs</a></li>
                        <li><a href="{{ route('website.play.live') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Play Live with Friends</a></li>
                    </ul>
                </div>

                <div class="hx-col">
                    <div class="hx-col-head">
                        <span class="hx-icon"><i class="bi bi-trophy-fill" aria-hidden="true"></i></span>
                        <h3>Earn &amp; Compete</h3>
                    </div>
                    <ul class="hx-list">
                        <li><a href="#dailySpin"><i class="bi bi-arrow-right" aria-hidden="true"></i> Daily Spin &amp; Win</a></li>
                        <li><a href="{{ route('website.leaderboard') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Leaderboard</a></li>
                        <li><a href="{{ route('exams') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> All Exams</a></li>
                        <li><a href="{{ route('website.categories') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Browse All Categories</a></li>
                        <li><a href="{{ route('user.register') }}"><i class="bi bi-arrow-right" aria-hidden="true"></i> Create a Free Account</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- 1d. Everything you need — feature grid --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="w-section-head text-center d-block">
                <div>
                    <h2>Everything You Need to Crack Your Exam</h2>
                    <p>Free quizzes, real exam patterns and gamified practice — all in one place, on any device.</p>
                </div>
            </div>

            <div class="hx-feat-grid">
                <div class="hx-feat">
                    <span class="hx-feat-icon"><i class="bi bi-patch-question-fill" aria-hidden="true"></i></span>
                    <h3>10,000+ Practice Questions</h3>
                    <p>Thousands of exam-style MCQs across GK, Current Affairs, Reasoning and more — each with a clear written explanation so you learn as you practise.</p>
                    <a href="{{ route('website.quizzes') }}" class="hx-feat-link">Explore <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="hx-feat">
                    <span class="hx-feat-icon"><i class="bi bi-calendar-check-fill" aria-hidden="true"></i></span>
                    <h3>Daily Current Affairs</h3>
                    <p>Fresh daily, weekly and monthly current-affairs quizzes that keep you exam-ready on national, international and economic news.</p>
                    <a href="{{ route('website.current.affairs.index') }}" class="hx-feat-link">Explore <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="hx-feat">
                    <span class="hx-feat-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                    <h3>Exam-Focused Prep</h3>
                    <p>Curated quiz hubs for SSC, UPSC, Banking, Railway and Defence — practise exactly the subjects each exam actually tests.</p>
                    <a href="{{ route('exams') }}" class="hx-feat-link">Explore <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="hx-feat">
                    <span class="hx-feat-icon"><i class="bi bi-controller" aria-hidden="true"></i></span>
                    <h3>Play Live with Friends</h3>
                    <p>Create a room, share a code and race friends through the same quiz in real time — with a live leaderboard and instant results.</p>
                    <a href="{{ route('website.play.live') }}" class="hx-feat-link">Explore <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="hx-feat">
                    <span class="hx-feat-icon"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i></span>
                    <h3>XP, Levels &amp; Badges</h3>
                    <p>Earn XP for every quiz, level up, unlock badges and climb the leaderboard — a fun daily habit that keeps you consistent.</p>
                    <a href="{{ route('website.leaderboard') }}" class="hx-feat-link">Explore <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="hx-feat">
                    <span class="hx-feat-icon"><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i></span>
                    <h3>Free Mock Tests &amp; PYQs</h3>
                    <p>Full-length timed mock tests and previous-year questions that simulate the real exam so there are no surprises on test day.</p>
                    <a href="{{ route('website.mock.tests') }}" class="hx-feat-link">Explore <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
            </div>
        </div>
    </section>

    @push('styles')
    <style>
        /* Home explore grid + feature grid (scoped, ships with the page) */
        .hx-grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--w-space-4)}
        .hx-col{background:var(--w-bg);border:1px solid var(--w-border);border-radius:16px;padding:var(--w-space-5);
            transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
        .hx-col:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(2,22,45,.08);border-color:#f6d3b3}
        .hx-col-head{display:flex;align-items:center;gap:.6rem;padding-bottom:var(--w-space-3);
            margin-bottom:var(--w-space-3);border-bottom:1px solid var(--w-border)}
        .hx-col-head h3{font-size:var(--w-fs-lg);margin:0;color:var(--w-primary);font-weight:700}
        .hx-icon{flex:0 0 auto;width:42px;height:42px;border-radius:11px;display:grid;place-items:center;
            background:#fff2e8;color:#02162d;font-size:1.15rem}
        .hx-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:.1rem}
        .hx-list a{display:flex;align-items:center;gap:.5rem;padding:.42rem 0;color:var(--w-body);
            font-size:var(--w-fs-sm);font-weight:500;text-decoration:none;transition:color .15s ease,padding .15s ease}
        .hx-list a i{color:#02162d;font-size:.78rem;transition:transform .15s ease}
        .hx-list a:hover{color:#02162d;padding-left:4px}
        .hx-list a:hover i{transform:translateX(2px)}

        .hx-feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--w-space-4)}
        .hx-feat{background:var(--w-bg);border:1px solid var(--w-border);border-radius:16px;padding:var(--w-space-5);
            display:flex;flex-direction:column;transition:transform .18s ease,box-shadow .18s ease}
        .hx-feat:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(2,22,45,.08)}
        .hx-feat-icon{width:46px;height:46px;border-radius:50%;display:grid;place-items:center;
            background:var(--w-primary-light);color:var(--w-primary);font-size:1.25rem;margin-bottom:var(--w-space-3)}
        .hx-feat h3{font-size:var(--w-fs-lg);color:var(--w-primary);font-weight:700;margin:0 0 .35rem}
        .hx-feat p{color:var(--w-muted);font-size:var(--w-fs-sm);margin:0 0 var(--w-space-3);flex:1;line-height:1.6}
        .hx-feat-link{color:#02162d;font-weight:600;font-size:var(--w-fs-sm);text-decoration:none;
            display:inline-flex;align-items:center;gap:.35rem;margin-top:auto}
        .hx-feat-link i{transition:transform .15s ease}
        .hx-feat-link:hover{color:#c2410c}
        .hx-feat-link:hover i{transform:translateX(3px)}

        @media (max-width:991.98px){
            .hx-grid4{grid-template-columns:repeat(2,1fr)}
            .hx-feat-grid{grid-template-columns:repeat(2,1fr)}
        }
        @media (max-width:575.98px){
            .hx-grid4{grid-template-columns:1fr}
            .hx-feat-grid{grid-template-columns:1fr}
        }
    </style>
    @endpush

    {{-- 2. Signed-in: continue learning + progress --}}
    @auth
        @if ($continueLearning->count() || $userStats)
            <section class="w-section w-section-alt">
                <div class="container">
                    <div class="row g-4 align-items-start">
                        @if ($continueLearning->count())
                            <div class="col-lg-8">
                                <div class="w-section-head">
                                    <div>
                                        <h2>Continue Learning</h2>
                                        <p>Pick up where you left off.</p>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    @foreach ($continueLearning as $attempt)
                                        <div class="col-md-6">
                                            <div class="w-card">
                                                <div class="w-card-body">
                                                    <span class="w-badge w-badge-primary mb-2">In progress</span>
                                                    <h3 class="w-card-title">{{ $attempt->quiz->title }}</h3>
                                                    <p class="w-text-sm w-muted mb-3">{{ $attempt->quiz->category?->name }}</p>
                                                    <a href="{{ route('website.quiz.attempt', $attempt->id) }}" class="btn w-btn-primary btn-sm mt-auto">
                                                        Resume <i class="bi bi-arrow-right"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($userStats)
                            <div class="col-lg-4">
                                <div class="w-section-head"><div><h2>Your Progress</h2></div></div>
                                <div class="w-card mb-3">
                                    <div class="w-card-body">
                                        <div class="row g-3 text-center">
                                            <div class="col-6">
                                                <strong class="d-block fs-4">{{ $userStats['attempts'] }}</strong>
                                                <small class="w-muted">Quizzes</small>
                                            </div>
                                            <div class="col-6">
                                                <strong class="d-block fs-4">{{ $userStats['accuracy'] }}%</strong>
                                                <small class="w-muted">Accuracy</small>
                                            </div>
                                            <div class="col-6">
                                                <strong class="d-block fs-4">{{ number_format($userStats['total_xp']) }}</strong>
                                                <small class="w-muted">Total XP</small>
                                            </div>
                                            <div class="col-6">
                                                <strong class="d-block fs-4 text-warning">
                                                    <i class="bi bi-fire"></i> {{ $userStats['streak'] }}
                                                </strong>
                                                <small class="w-muted">Day streak</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @if ($weakTopics->count())
                                    <div class="w-card">
                                        <div class="w-card-body">
                                            <h3 class="w-card-title" style="font-size: var(--w-fs-base);">
                                                <i class="bi bi-graph-down-arrow text-danger"></i> Topics to improve
                                            </h3>
                                            @foreach ($weakTopics as $topic)
                                                <div class="mb-3">
                                                    <div class="d-flex justify-content-between w-text-sm mb-1">
                                                        <a href="{{ route('website.category.show', $topic->slug) }}">{{ $topic->name }}</a>
                                                        <span class="w-muted">{{ round($topic->avg_score) }}%</span>
                                                    </div>
                                                    <div class="w-progress">
                                                        <div class="w-progress-bar {{ $topic->avg_score < 50 ? 'is-danger' : 'is-warning' }}"
                                                             data-progress="{{ min(100, round($topic->avg_score)) }}"
                                                             style="width: {{ min(100, round($topic->avg_score)) }}%"></div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </section>
        @endif
    @endauth

    {{-- 3. Today's quiz --}}
    @if ($todayQuiz)
        <section class="w-section">
            <div class="container">
                <div class="w-card" style="background: linear-gradient(135deg,#eef2ff,#fff);">
                    <div class="w-card-body">
                        <div class="row align-items-center g-4">
                            <div class="col-md-8">
                                <span class="w-badge w-badge-primary mb-2"><i class="bi bi-calendar-check"></i> Quiz of the day</span>
                                <h2 class="mb-2">{{ $todayQuiz->title }}</h2>
                                <p class="w-muted mb-3">{{ \Illuminate\Support\Str::limit($todayQuiz->description, 160) }}</p>
                                <div class="w-meta">
                                    <span><i class="bi bi-question-circle"></i> {{ $todayQuiz->total_questions }} questions</span>
                                    <span><i class="bi bi-clock"></i> {{ $todayQuiz->time_limit ?: '—' }} min</span>
                                    <span class="w-badge w-badge-{{ $todayQuiz->difficulty }}">{{ ucfirst($todayQuiz->difficulty) }}</span>
                                </div>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <a href="{{ route('website.quiz.show', $todayQuiz->slug) }}" class="btn w-btn-primary btn-lg w-100">
                                    <i class="bi bi-play-fill"></i> Take Today's Quiz
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- 3b. Daily Spin & Win — gamified daily habit (self-contained partial). --}}
    @include('website.home.daily-spin')

    {{-- 4. Popular exams --}}
    @if ($examCategories->count())
        <section class="w-section w-section-alt" style="display:none;">
            <div class="container">
                <div class="w-section-head">
                    <div>
                        <h2>Popular Exams</h2>
                        <p>Targeted preparation for major competitive examinations.</p>
                    </div>
                    <a href="{{ route('exams') }}" class="btn w-btn-outline btn-sm">View all</a>
                </div>
                <div class="row g-3">
                    @foreach ($examCategories as $exam)
                        <div class="col-6 col-md-4 col-lg-3">
                            <x-website::category-card :category="$exam" :url="route('website.exam.show', $exam->slug)" />
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 4b. Most Popular Quizzes — admin-curated slider ("Show in Most Popular"). --}}
    @if ($featuredPopular->count())
        <section class="w-section">
            <div class="container">
                <div class="w-section-head">
                    <div>
                        <h2>Most Popular Quizzes</h2>
                        <p>Hand-picked quizzes our learners love the most.</p>
                    </div>
                    <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline btn-sm">All quizzes</a>
                </div>

                <div class="w-pop-slider" data-pop-slider>
                    <button type="button" class="w-pop-nav w-pop-prev" data-pop-prev aria-label="Previous quizzes">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </button>

                    <div class="w-pop-track" data-pop-track>
                        @foreach ($featuredPopular as $quiz)
                            <div class="w-pop-slide"><x-website::quiz-card :quiz="$quiz" /></div>
                        @endforeach
                    </div>

                    <button type="button" class="w-pop-nav w-pop-next" data-pop-next aria-label="More quizzes">
                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </section>
    @endif

    {{-- 5. Popular quizzes --}}
    <section class="w-section">
        <div class="container">
            <div class="w-section-head">
                <div>
                    <h2>Mitra Quizzes</h2>
                    <p>Most attempted quizzes across all categories.</p>
                </div>
                <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline btn-sm">All Mitra Quizzes</a>
            </div>

            @if ($popularQuizzes->count())
                <div class="row g-3">
                    @foreach ($popularQuizzes as $quiz)
                        <div class="col-sm-6 col-lg-3"><x-website::quiz-card :quiz="$quiz" /></div>
                    @endforeach
                </div>
            @else
                <x-website::empty-state icon="bi-patch-question" title="No quizzes published yet"
                    message="Quizzes will appear here once they are published from the admin panel." />
            @endif
        </div>
    </section>

    {{-- 6. Current affairs --}}
    @if ($currentAffairs->count())
        <section class="w-section w-section-alt">
            <div class="container">
                <div class="w-section-head">
                    <div>
                        <h2>Current Affairs</h2>
                        <p>Stay updated with daily, weekly and monthly news quizzes.</p>
                    </div>
                    <a href="{{ route('website.current.affairs.index') }}" class="btn w-btn-outline btn-sm">View all</a>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="{{ route('website.current.affairs.today') }}" class="btn w-btn-primary btn-sm"><i class="bi bi-calendar-day"></i> Today</a>
                    <a href="{{ route('website.current.affairs.weekly') }}" class="btn w-btn-outline btn-sm"><i class="bi bi-calendar-week"></i> Weekly</a>
                    <a href="{{ route('website.current.affairs.monthly') }}" class="btn w-btn-outline btn-sm"><i class="bi bi-calendar-month"></i> Monthly</a>
                </div>

                <div class="row g-3">
                    @foreach ($currentAffairs as $quiz)
                        <div class="col-sm-6 col-lg-4"><x-website::quiz-card :quiz="$quiz" /></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 7. Explore categories --}}
    <section class="w-section">
        <div class="container">
            <div class="w-section-head">
                <div>
                    <h2>Explore Categories</h2>
                    <p>Browse {{ $categories->count() }} subject areas and their topics.</p>
                </div>
                <a href="{{ route('website.categories') }}" class="btn w-btn-outline btn-sm">All categories</a>
            </div>
            <div class="row g-3">
                @foreach ($categories->take(12) as $cat)
                    <div class="col-6 col-md-4 col-lg-2">
                        <x-website::category-card :category="$cat" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 7b. How to earn XP --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="w-section-head text-center d-block">
                <div class="mx-auto" style="max-width: 640px;">
                    <span class="w-badge w-badge-primary mb-2"><i class="bi bi-lightning-charge-fill"></i> Gamified learning</span>
                    <h2>How to Earn XP</h2>
                    <p>Four simple steps — create an account, start playing, and earn XP, badges and leaderboard rank as you learn.</p>
                </div>
            </div>

            <div class="row g-3 g-lg-4 w-xp-steps">
                @foreach ([
                    ['bi-person-plus-fill', 'Create Account', 'Register free in seconds — just your name and email, no fees.', 'Register', route('user.register')],
                    ['bi-box-arrow-in-right', 'Log In', 'Sign in to save your progress, streaks and every XP point you earn.', 'Login', route('user.login')],
                    ['bi-play-circle-fill', 'Play Quizzes', 'Attempt GK, Current Affairs, SSC, Banking and mock-test quizzes.', 'Browse quizzes', route('website.quizzes')],
                    ['bi-trophy-fill', 'Earn XP & Rewards', 'Score XP on every quiz, unlock badges and climb the leaderboard.', 'Leaderboard', route('website.leaderboard')],
                ] as $i => [$icon, $title, $text, $cta, $link])
                    <div class="col-6 col-lg-3">
                        <div class="w-step-card">
                            <span class="w-step-num">{{ $i + 1 }}</span>
                            <span class="w-step-icon"><i class="bi {{ $icon }}" aria-hidden="true"></i></span>
                            <h3 class="w-step-title">{{ $title }}</h3>
                            <p class="w-step-text">{{ $text }}</p>
                            <a href="{{ $link }}" class="w-step-link">{{ $cta }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="text-center mt-4">
                @guest
                    <a href="{{ route('user.register') }}" class="btn w-btn-primary btn-lg px-4">
                        <i class="bi bi-lightning-charge-fill me-2" aria-hidden="true"></i>Start Earning XP — It's Free
                    </a>
                @else
                    <a href="{{ route('website.quizzes') }}" class="btn w-btn-primary btn-lg px-4">
                        <i class="bi bi-play-circle-fill me-2" aria-hidden="true"></i>Play a Quiz & Earn XP
                    </a>
                @endguest
            </div>
        </div>
    </section>


    {{-- 7c. How QuizMitra Works — 4-step onboarding (SEO how-it-works) --}}
    <section class="w-section">
        <div class="container">
            <div class="w-section-head text-center d-block">
                <div>
                    <h2>How QuizMitra Works</h2>
                    <p>From picking a quiz to climbing the leaderboard — start improving in four simple steps.</p>
                </div>
            </div>
            <div class="hx-steps">
                <div class="hx-step">
                    <div class="hx-step-num">01</div>
                    <span class="hx-step-icon"><i class="bi bi-collection-fill" aria-hidden="true"></i></span>
                    <h3>Pick a Quiz</h3>
                    <p>Choose from thousands of quizzes across GK, Current Affairs, Reasoning and every major exam.</p>
                </div>
                <div class="hx-step">
                    <div class="hx-step-num">02</div>
                    <span class="hx-step-icon"><i class="bi bi-pencil-square" aria-hidden="true"></i></span>
                    <h3>Answer the Questions</h3>
                    <p>Attempt clean, timed multiple-choice questions on any device — no clutter, no distractions.</p>
                </div>
                <div class="hx-step">
                    <div class="hx-step-num">03</div>
                    <span class="hx-step-icon"><i class="bi bi-clipboard2-data-fill" aria-hidden="true"></i></span>
                    <h3>See Instant Results</h3>
                    <p>Get your score instantly with the correct answer and a written explanation for every question.</p>
                </div>
                <div class="hx-step">
                    <div class="hx-step-num">04</div>
                    <span class="hx-step-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span>
                    <h3>Earn XP &amp; Improve</h3>
                    <p>Collect XP, unlock badges, spot weak topics and climb the leaderboard as you improve.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- 7d. Who is QuizMitra for — audience use-cases (accent-bar cards) --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="w-section-head text-center d-block">
                <div>
                    <h2>Who Is QuizMitra For?</h2>
                    <p>Whatever you're preparing for, there's a focused set of quizzes waiting for you.</p>
                </div>
            </div>
            <div class="hx-use-grid">
                <a href="{{ route('exams') }}" class="hx-use-card">
                    <span class="hx-use-icon"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                    <span class="hx-use-body"><span class="hx-use-tag">SSC &amp; Railway Aspirants</span>
                        <p>Targeted GK, reasoning and current-affairs practice for CGL, CHSL, NTPC and Group D.</p></span>
                    <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <a href="{{ route('exams') }}" class="hx-use-card">
                    <span class="hx-use-icon"><i class="bi bi-bank2" aria-hidden="true"></i></span>
                    <span class="hx-use-body"><span class="hx-use-tag">Banking Aspirants</span>
                        <p>Reasoning, aptitude, banking awareness and current affairs for IBPS &amp; SBI PO/Clerk.</p></span>
                    <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <a href="{{ route('exams') }}" class="hx-use-card">
                    <span class="hx-use-icon"><i class="bi bi-building-fill" aria-hidden="true"></i></span>
                    <span class="hx-use-body"><span class="hx-use-tag">UPSC &amp; State PSC</span>
                        <p>Broad General Studies and daily current-affairs practice for Prelims-style testing.</p></span>
                    <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <a href="{{ route('exams') }}" class="hx-use-card">
                    <span class="hx-use-icon"><i class="bi bi-shield-fill-check" aria-hidden="true"></i></span>
                    <span class="hx-use-body"><span class="hx-use-tag">Defence Aspirants</span>
                        <p>GK, current affairs and general science practice for NDA, CDS and AFCAT.</p></span>
                    <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <a href="{{ route('website.categories') }}" class="hx-use-card">
                    <span class="hx-use-icon"><i class="bi bi-backpack2-fill" aria-hidden="true"></i></span>
                    <span class="hx-use-body"><span class="hx-use-tag">School &amp; College Students</span>
                        <p>Build strong fundamentals in GK, science and more with fun, bite-sized quizzes.</p></span>
                    <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
                <a href="{{ route('website.quizzes') }}" class="hx-use-card">
                    <span class="hx-use-icon"><i class="bi bi-emoji-laughing-fill" aria-hidden="true"></i></span>
                    <span class="hx-use-body"><span class="hx-use-tag">Quiz &amp; Trivia Lovers</span>
                        <p>Sports, movies, entertainment and world trivia — learn something new every day.</p></span>
                    <span class="hx-use-arrow"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>
                </a>
            </div>
        </div>
    </section>

    @push('styles')
    <style>
        /* Home: how-it-works steps + audience use-case cards (scoped) */
        .hx-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--w-space-4)}
        .hx-step{background:var(--w-bg);border:1.5px solid var(--w-border);border-radius:16px;
            padding:var(--w-space-5);text-align:center;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
        .hx-step:hover{transform:translateY(-3px);box-shadow:0 12px 30px rgba(2,22,45,.08);border-color:#f6d3b3}
        .hx-step:first-child{border-color:#02162d}
        .hx-step-num{font-size:2rem;font-weight:800;color:#e5e7eb;line-height:1}
        .hx-step:first-child .hx-step-num{color:#02162d}
        .hx-step-icon{width:56px;height:56px;border-radius:14px;display:grid;place-items:center;margin:var(--w-space-3) auto;
            background:#02162d;color:#02162d;font-size:1.5rem}
        .hx-step:first-child .hx-step-icon{background:#02162d;color:#fff}
        .hx-step h3{font-size:var(--w-fs-lg);color:var(--w-primary);font-weight:700;margin:0}
        .hx-step h3::after{content:"";display:block;width:34px;height:3px;background:#02162d;border-radius:2px;margin:.55rem auto 0}
        .hx-step p{color:var(--w-muted);font-size:var(--w-fs-sm);margin:.75rem 0 0;line-height:1.6}

        .hx-use-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:var(--w-space-4)}
        .hx-use-card{display:flex;align-items:flex-start;gap:var(--w-space-4);background:var(--w-bg);
            border:1px solid var(--w-border);border-left:4px solid #02162d; border-radius:14px;padding:var(--w-space-5);
            text-decoration:none;transition:transform .18s ease,box-shadow .18s ease}
        .hx-use-card:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(2,22,45,.08)}
        .hx-use-icon{flex:0 0 auto;width:52px;height:52px;border-radius:13px;display:grid;place-items:center;
            background:#02162d;color:#02162d;font-size:1.4rem}
        .hx-use-body{flex:1;min-width:0}
        .hx-use-tag{display:block;color:#02162d;font-weight:700;font-size:var(--w-fs-base);margin-bottom:.2rem}
        .hx-use-body p{color:var(--w-muted);font-size:var(--w-fs-sm);margin:0;line-height:1.55}
        .hx-use-arrow{flex:0 0 auto;align-self:center;width:38px;height:38px;border-radius:50%;
            border:1px solid var(--w-border);display:grid;place-items:center;color:#02162d;
            transition:background .15s ease,color .15s ease,transform .15s ease}
        .hx-use-card:hover .hx-use-arrow{background:#02162d;color:#fff;transform:translateX(2px)}

        @media (max-width:991.98px){ .hx-steps{grid-template-columns:repeat(2,1fr)} }
        @media (max-width:767.98px){ .hx-use-grid{grid-template-columns:1fr} }
        @media (max-width:575.98px){ .hx-steps{grid-template-columns:1fr} }
    </style>
    @endpush

    {{-- 8. Leaderboard --}}
    @if ($leaders->count())
        <section class="w-section w-section-alt">
            <div class="container">
                <div class="row g-4 align-items-start">
                    <div class="col-lg-7">
                        <div class="w-section-head">
                            <div>
                                <h2>Top Performers</h2>
                                <p>Highest XP earners across the platform.</p>
                            </div>
                            <a href="{{ route('website.leaderboard') }}" class="btn w-btn-outline btn-sm">Full leaderboard</a>
                        </div>
                        <div class="w-card">
                            @foreach ($leaders as $i => $row)
                                <x-website::leaderboard-card
                                    :row="(object)['user' => $row->user, 'xp' => $row->total_xp, 'level' => $row->current_level, 'attempts' => null]"
                                    :rank="$i + 1"
                                    :isMe="auth()->check() && auth()->id() === $row->user_id" />
                            @endforeach
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="w-section-head"><div><h2>Why Choose Us</h2></div></div>
                        <div class="row g-3">
                            @foreach ([
                                ['bi-lightning-charge-fill', 'Earn XP for everything', 'Every quiz you complete awards XP that builds your level and rank.'],
                                ['bi-award-fill', 'Unlock badges', 'Achievements recognise streaks, accuracy and milestones.'],
                                ['bi-graph-up-arrow', 'Track weak topics', 'See exactly which subjects need more practice.'],
                                ['bi-phone', 'Practice anywhere', 'Fully responsive — study on mobile, tablet or desktop.'],
                            ] as [$icon, $title, $text])
                                <div class="col-12">
                                    <div class="d-flex gap-3">
                                        <span class="w-cat-icon m-0 flex-shrink-0" style="width:44px;height:44px;font-size:1.1rem;">
                                            <i class="bi {{ $icon }}"></i>
                                        </span>
                                        <div>
                                            <strong class="d-block">{{ $title }}</strong>
                                            <span class="w-text-sm w-muted">{{ $text }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- 9. Blog --}}
    @if ($blogs->count())
        <section class="w-section">
            <div class="container">
                <div class="w-section-head">
                    <div>
                        <h2>Latest Articles</h2>
                        <p>Preparation strategy, study guides and exam tips.</p>
                    </div>
                    <a href="{{ route('blog') }}" class="btn w-btn-outline btn-sm">Visit blog</a>
                </div>
                <div class="row g-4">
                    @foreach ($blogs as $blog)
                        <div class="col-md-4"><x-website::blog-card :blog="$blog" /></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Testimonials --}}
    @if ($testimonials->count())
        <section class="w-section">
            <div class="container">
                <div class="w-section-head">
                    <div>
                        <h2>{{ $testimonialContent?->data_values?->heading ?? 'What learners say' }}</h2>
                        <p>{{ $testimonialContent?->data_values?->short_details ?? 'Feedback from people preparing with us every day.' }}</p>
                    </div>
                </div>

                <div class="row g-4">
                    @foreach ($testimonials->take(4) as $testimonial)
                        <div class="col-sm-6 col-lg-3">
                            <x-website::testimonial-card :testimonial="$testimonial" />
                        </div>
                    @endforeach
                </div>

                @if ($testimonials->first()?->is_sample)
                    {{-- Visible to admins only: a nudge that these are placeholders. --}}
                    @auth
                        <p class="w-text-xs w-muted text-center mt-4 mb-0">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            Showing sample testimonials. Add real ones from
                            Admin &rsaquo; Frontend Manager &rsaquo; Testimonial to replace them.
                        </p>
                    @endauth
                @endif
            </div>
        </section>
    @endif

    {{-- 10. FAQ + SEO content --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="row g-5 align-items-start">
                <div class="col-lg-7">
                    <x-website::faq-accordion :faqs="$faqs" id="wHomeFaq" />
                </div>
                <div class="col-lg-5">
                    <h2 class="mb-3">Practice smarter for competitive exams</h2>
                    <div class="w-muted">
                        <p>
                            This platform brings together General Knowledge, Current Affairs and exam-specific
                            practice for SSC, Railway, Banking, UPSC, Defence, State PSC and Teaching examinations.
                            Every question carries a written explanation so you learn the reasoning, not just the answer.
                        </p>
                        <p class="mb-0">
                            Progress is tracked automatically: your accuracy per topic, your XP and level, your daily
                            streak and the badges you unlock. Use the weak-topic panel on your dashboard to decide
                            what to revise next.
                        </p>
                    </div>
                    <a href="{{ route('user.register') }}" class="btn w-btn-primary mt-4">
                        Create a free account <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </section>

@endsection

{{-- Most Popular Quizzes slider: arrow controls + auto-hide when it fits. --}}
@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('[data-pop-slider]').forEach(function (slider) {
                var track = slider.querySelector('[data-pop-track]');
                var prev  = slider.querySelector('[data-pop-prev]');
                var next  = slider.querySelector('[data-pop-next]');
                if (!track) { return; }

                // Scroll by one "page" — as many whole cards as currently fit.
                function stepBy() {
                    var card = track.querySelector('.w-pop-slide');
                    var cardW = card ? card.getBoundingClientRect().width : track.clientWidth;
                    var styles = getComputedStyle(track);
                    var gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
                    var perView = Math.max(1, Math.floor((track.clientWidth + gap) / (cardW + gap)));
                    return (cardW + gap) * perView;
                }

                function update() {
                    var max = track.scrollWidth - track.clientWidth - 1;
                    var fits = track.scrollWidth <= track.clientWidth + 1;
                    slider.classList.toggle('w-pop-static', fits);
                    if (prev) { prev.disabled = track.scrollLeft <= 0; }
                    if (next) { next.disabled = track.scrollLeft >= max; }
                }

                if (prev) { prev.addEventListener('click', function () { track.scrollBy({ left: -stepBy(), behavior: 'smooth' }); }); }
                if (next) { next.addEventListener('click', function () { track.scrollBy({ left: stepBy(), behavior: 'smooth' }); }); }
                track.addEventListener('scroll', update, { passive: true });
                window.addEventListener('resize', update);
                update();
            });
        })();
    </script>
@endpush

@if ($isIndependence)
    @push('scripts')
        <script src="{{ wAsset('assets/web/js/confetti.js') }}"></script>
        <script>
            // Independence Day celebration: confetti + rising tricolour balloons,
            // once per session on the first home-page open. The whole thing runs
            // for ~10 seconds, then removes itself. Decorative only.
            (function () {
                try {
                    if (sessionStorage.getItem('wBalloonsShown')) { return; }
                    sessionStorage.setItem('wBalloonsShown', '1');
                } catch (e) { /* storage blocked — just show it once this load */ }

                if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

                var rand = function (a, b) { return a + Math.random() * (b - a); };
                var DURATION = 10000; // total run time (ms)

                // ---- Confetti: an opening burst and a second one mid-way -----
                if (window.WConfetti) {
                    window.WConfetti.celebrate();
                    setTimeout(function () { window.WConfetti.celebrate(); }, 5000);
                }

                // ---- Balloons ------------------------------------------------
                var COLORS = ['is-saffron', 'is-white', 'is-green', 'is-tricolor', 'is-tricolor'];
                var COUNT = 30;

                var layer = document.createElement('div');
                layer.className = 'w-balloons';
                layer.setAttribute('aria-hidden', 'true');

                for (var i = 0; i < COUNT; i++) {
                    var b = document.createElement('span');
                    b.className = 'w-balloon ' + COLORS[(Math.random() * COLORS.length) | 0];
                    var size = rand(32, 56);
                    b.style.left = rand(1, 95) + '%';
                    b.style.width = size + 'px';
                    b.style.height = (size * 1.25) + 'px';
                    // Longer rise + wider delay spread so balloons keep coming
                    // across the full 10 seconds instead of clearing early.
                    b.style.animationDuration = rand(6, 9) + 's';
                    b.style.animationDelay = rand(0, 4) + 's';
                    b.style.setProperty('--drift', rand(-45, 45) + 'px');
                    b.style.setProperty('--rot', rand(-12, 12) + 'deg');
                    layer.appendChild(b);
                }

                document.body.appendChild(layer);

                // Fade out and remove the whole layer after the run time.
                setTimeout(function () {
                    layer.classList.add('is-hiding');
                    setTimeout(function () { layer.remove(); }, 850);
                }, DURATION);
            })();
        </script>
    @endpush
@endif
