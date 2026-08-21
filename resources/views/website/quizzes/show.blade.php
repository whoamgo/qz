@extends('website.layouts.app')

@section('breadcrumb')
    @php
        $trail = ['Home' => route('home'), 'Quizzes' => route('website.quizzes')];
        if ($quiz->category) { $trail[$quiz->category->name] = route('website.category.show', $quiz->category->slug); }
        if ($quiz->subCategory && $quiz->category) {
            $trail[$quiz->subCategory->name] = route('website.subcategory.show', [$quiz->category->slug, $quiz->subCategory->slug]);
        }
        $trail[$quiz->title] = route('website.quiz.show', $quiz->slug);
    @endphp
    <x-website::breadcrumbs :trail="$trail" />
@endsection

@section('content')
<section class="w-section">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="w-card">
                    <div class="w-card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="w-badge w-badge-{{ $quiz->difficulty }}">{{ ucfirst($quiz->difficulty) }}</span>
                            <span class="w-badge w-badge-{{ $quiz->quiz_type }}">{{ ucfirst($quiz->quiz_type) }}</span>
                            @if ($quiz->category)
                                <a href="{{ route('website.category.show', $quiz->category->slug) }}" class="w-badge">
                                    <i class="bi bi-folder2"></i> {{ $quiz->category->name }}
                                </a>
                            @endif
                            @if ($quiz->subCategory && $quiz->category)
                                <a href="{{ route('website.subcategory.show', [$quiz->category->slug, $quiz->subCategory->slug]) }}" class="w-badge">
                                    {{ $quiz->subCategory->name }}
                                </a>
                            @endif
                        </div>

                        <h1 class="mb-3">{{ $quiz->title }}</h1>

                        @if ($quiz->description)
                            <p class="w-muted">{{ $quiz->description }}</p>
                        @endif

                        <div class="row g-3 mt-3">
                            @foreach ([
                                ['bi-question-circle', $quiz->effectiveQuestionCount($quiz->questions_count), 'Questions'],
                                ['bi-clock', $quiz->time_limit ? $quiz->time_limit . ' min' : 'No limit', 'Duration'],
                                ['bi-check2-circle', $quiz->pass_percentage . '%', 'To pass'],
                                ['bi-star', $quiz->marks_per_correct, 'Marks / correct'],
                            ] as [$icon, $value, $label])
                                <div class="col-6 col-md-3">
                                    <div class="w-stat-tile">
                                        <strong style="font-size: var(--w-fs-xl);"><i class="bi {{ $icon }}"></i> {{ $value }}</strong>
                                        <span>{{ $label }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Instructions --}}
                <div class="w-card mt-4">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-info-circle"></i> Instructions</h2>
                        <ul class="w-muted mb-0" style="padding-left: 1.1rem;">
                            <li>This quiz has <strong>{{ $quiz->effectiveQuestionCount($quiz->questions_count) }}</strong> questions.</li>
                            @if ($quiz->isLimited())
                                <li>
                                    Questions are picked <strong>at random</strong> from a bank of
                                    {{ $quiz->questions_count }}, so each attempt is different.
                                </li>
                            @endif
                            @if ($quiz->time_limit)
                                <li>You have <strong>{{ $quiz->time_limit }} minutes</strong>. The quiz submits automatically when time runs out.</li>
                            @else
                                <li>There is no time limit on this quiz.</li>
                            @endif
                            <li>You need <strong>{{ $quiz->pass_percentage }}%</strong> to pass.</li>
                            @if ($quiz->negative_marking > 0)
                                <li><strong>{{ $quiz->negative_marking }}</strong> mark(s) are deducted for each wrong answer.</li>
                            @else
                                <li>There is no negative marking.</li>
                            @endif
                            <li>You can mark questions for review and return to them before submitting.</li>
                            @if ($quiz->show_correct_answers)
                                <li>Correct answers and explanations are shown after you submit.</li>
                            @endif
                        </ul>
                    </div>
                </div>

                {{-- Public sample questions: real questions, answers and
                     explanations, server-rendered so search engines can crawl
                     the quiz content without login. Display-only — this never
                     starts a scored attempt or exposes the full question bank. --}}
                <div class="mt-4">
                    <x-website::quiz-samples :quiz="$quiz" :questions="$sampleQuestions" />
                </div>

                {{-- FAQ --}}
                <div class="mt-4">
                    <x-website::faq-accordion :faqs="$faqs" id="wQuizFaq" title="Frequently Asked Questions" />
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                <div class="w-card mb-4" style="position: sticky; top: 88px;">
                    <div class="w-card-body">
                        @auth
                            @if ($openAttempt)
                                <p class="w-text-sm w-muted mb-3">You have an unfinished attempt.</p>
                                <a href="{{ route('website.quiz.attempt', $openAttempt->id) }}" class="btn w-btn-primary btn-lg w-100 mb-2">
                                    <i class="bi bi-arrow-clockwise"></i> Resume Quiz
                                </a>
                            @else
                                <form action="{{ route('website.quiz.start', $quiz->slug) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn w-btn-primary btn-lg w-100 mb-2"
                                            data-track-click="true" data-track-event="quiz_started"
                                            data-track-name="Start Quiz" data-track-category="quiz"
                                            data-track-id="{{ $quiz->slug }}">
                                        <i class="bi bi-play-fill"></i> Start Quiz
                                    </button>
                                </form>
                            @endif

                            <button type="button" class="btn w-btn-outline w-100 wBookmarkBtn"
                                    data-url="{{ route('website.bookmark.toggle') }}" data-type="quiz" data-id="{{ $quiz->id }}"
                                    aria-pressed="{{ $bookmarked ? 'true' : 'false' }}">
                                <i class="bi {{ $bookmarked ? 'bi-bookmark-fill' : 'bi-bookmark' }}"></i> Bookmark
                            </button>

                            @if ($lastAttempt)
                                <hr>
                                <p class="w-text-sm w-muted mb-2">Your last attempt</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold fs-5 {{ $lastAttempt->passed ? 'text-success' : 'text-danger' }}">
                                        {{ $lastAttempt->percentage }}%
                                    </span>
                                    <a href="{{ route('website.quiz.result', $lastAttempt->id) }}" class="btn w-btn-outline btn-sm">View result</a>
                                </div>
                            @endif
                        @else
                            <p class="w-text-sm w-muted mb-3">Sign in to attempt this quiz and earn XP.</p>
                            <a href="{{ route('user.login') }}" class="btn w-btn-primary btn-lg w-100 mb-2">Login to Start</a>
                            <a href="{{ route('user.register') }}" class="btn w-btn-outline w-100">Create free account</a>
                        @endauth
                    </div>
                </div>

                @if ($related->count())
                    <h2 class="h5 mb-3">Related Quizzes</h2>
                    <div class="d-flex flex-column gap-3">
                        @foreach ($related as $r)
                            <x-website::quiz-card :quiz="$r" />
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
