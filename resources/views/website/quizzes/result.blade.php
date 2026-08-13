@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/quiz.css') }}" rel="stylesheet">
@endpush

@section('content')
<section class="w-section">
    <div class="container">

        {{-- Score --}}
        <div class="w-result-hero {{ $attempt->passed ? 'is-pass' : 'is-fail' }} mb-4">
            <div class="w-score-ring">
                <div>
                    <strong>{{ round($attempt->percentage) }}%</strong>
                    <span class="d-block">{{ $attempt->score }} / {{ $attempt->total_marks }}</span>
                </div>
            </div>
            <h1 class="h2 text-white mb-2">
                {{ $attempt->passed ? 'Congratulations, you passed!' : 'Not quite there yet' }}
            </h1>
            <p class="mb-0 text-white-50">
                {{ $quiz->title }} &middot; needed {{ $quiz->pass_percentage }}% to pass
            </p>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                {{-- Breakdown --}}
                <div class="row g-3 mb-4">
                    @foreach ([
                        ['is-correct', $attempt->correct_count, 'Correct', 'bi-check-circle-fill'],
                        ['is-wrong', $attempt->wrong_count, 'Wrong', 'bi-x-circle-fill'],
                        ['is-skipped', $attempt->skipped_count, 'Skipped', 'bi-dash-circle-fill'],
                        ['', gmdate($attempt->time_taken >= 3600 ? 'H:i:s' : 'i:s', $attempt->time_taken), 'Time taken', 'bi-clock-fill'],
                    ] as [$cls, $value, $label, $icon])
                        <div class="col-6 col-md-3">
                            <div class="w-stat-tile {{ $cls }}">
                                <strong><i class="bi {{ $icon }}"></i> {{ $value }}</strong>
                                <span>{{ $label }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Share your score --}}
                <div class="w-card mb-4">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-share-fill"></i> Share your score</h2>
                        <p class="w-text-sm w-muted mb-3">
                            Let others know how you did on {{ $quiz->title }}.
                        </p>
                        <div class="d-flex flex-wrap gap-2" id="wScoreShare"
                             data-share-text="I scored {{ round($attempt->percentage) }}% ({{ $attempt->correct_count }}/{{ $attempt->total_questions }}) on the {{ $quiz->title }} quiz{{ $attempt->passed ? ' and passed' : '' }}! Can you beat me?"
                             data-share-url="{{ route('website.quiz.show', $quiz->slug) }}">
                            <button type="button" class="btn w-btn-outline btn-sm wScoreShareBtn" data-network="whatsapp">
                                <i class="bi bi-whatsapp"></i> WhatsApp
                            </button>
                            <button type="button" class="btn w-btn-outline btn-sm wScoreShareBtn" data-network="facebook">
                                <i class="bi bi-facebook"></i> Facebook
                            </button>
                            <button type="button" class="btn w-btn-outline btn-sm wScoreShareBtn" data-network="twitter">
                                <i class="bi bi-twitter-x"></i> X
                            </button>
                            <button type="button" class="btn w-btn-outline btn-sm wScoreShareBtn" data-network="telegram">
                                <i class="bi bi-telegram"></i> Telegram
                            </button>
                            {{-- Instagram has no web share URL; copying the text is
                                 the only thing that actually works from a browser. --}}
                            <button type="button" class="btn w-btn-outline btn-sm wScoreShareBtn" data-network="instagram">
                                <i class="bi bi-instagram"></i> Instagram
                            </button>
                            <button type="button" class="btn w-btn-outline btn-sm wScoreShareBtn" data-network="copy">
                                <i class="bi bi-link-45deg"></i> Copy link
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="d-flex flex-wrap gap-2 mb-4">
                    @if ($quiz->show_correct_answers)
                        <a href="{{ route('website.quiz.review', $attempt->id) }}" class="btn w-btn-primary">
                            <i class="bi bi-list-check"></i> Review Answers
                        </a>
                    @endif
                    <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline">Try Another Quiz</a>
                    <a href="{{ route('website.profile.index') }}" class="btn w-btn-outline">Back to Dashboard</a>
                </div>

                {{-- Related --}}
                @if ($related->count())
                    <h2 class="h5 mb-3">More from {{ $quiz->category?->name ?? 'this category' }}</h2>
                    <div class="row g-3">
                        @foreach ($related as $r)
                            <div class="col-sm-6"><x-website::quiz-card :quiz="$r" /></div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Gamification --}}
            <div class="col-lg-4">
                <div class="w-card mb-4">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-lightning-charge-fill text-warning"></i> XP Earned</h2>
                        <div class="text-center py-3">
                            <strong style="font-size: 2.4rem; color: var(--w-primary);"
                                    data-countup="{{ $attempt->xp_awarded }}" data-countup-prefix="+">+{{ number_format($attempt->xp_awarded) }}</strong>
                            <span class="d-block w-muted">XP from this attempt</span>
                        </div>

                        @php $breakdown = $attempt->xp_breakdown['breakdown'] ?? []; @endphp
                        @if (!empty(array_filter($breakdown)))
                            @foreach ([
                                'completion' => 'Completion',
                                'correct_answers' => 'Correct answers',
                                'passing_bonus' => 'Passing bonus',
                                'perfect_bonus' => 'Perfect score bonus',
                                'first_attempt_bonus' => 'First attempt bonus',
                            ] as $key => $label)
                                @if (!empty($breakdown[$key]))
                                    <div class="w-xp-row"><span>{{ $label }}</span><span>+{{ $breakdown[$key] }}</span></div>
                                @endif
                            @endforeach
                            <div class="w-xp-row"><span>Total</span><span>+{{ number_format($attempt->xp_awarded) }}</span></div>
                        @endif
                    </div>
                </div>

                <div class="mb-4">
                    <x-website::xp-progress :xp="$xp" :nextLevel="$nextLevel" />
                </div>

                @if (auth()->user()->streak)
                    <div class="w-card mb-4">
                        <div class="w-card-body text-center">
                            <div class="w-streak justify-content-center" style="font-size: var(--w-fs-2xl);">
                                <i class="bi bi-fire"></i> {{ auth()->user()->streak->current_streak }}
                            </div>
                            <span class="w-muted w-text-sm">day streak</span>
                        </div>
                    </div>
                @endif

                @if ($recentBadges->count())
                    <div class="w-card">
                        <div class="w-card-body">
                            <h2 class="w-card-title"><i class="bi bi-award-fill"></i> Your Badges</h2>
                            <div class="row g-2">
                                @foreach ($recentBadges as $badge)
                                    <div class="col-6" data-badge-new><x-website::badge-card :badge="$badge" :earned="true" /></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
