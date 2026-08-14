@props(['quiz'])
@php
    // What the user will actually be served, after any question limit.
    $bank   = $quiz->questions_count ?? $quiz->total_questions;
    $qCount = $quiz->effectiveQuestionCount($bank);
    $xp     = $quiz->xpSettings->completion_xp ?? null;
@endphp
<article class="w-card">
    <div class="w-card-body">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="d-flex flex-wrap gap-1">
                <span class="w-badge w-badge-{{ $quiz->difficulty }}">{{ ucfirst($quiz->difficulty) }}</span>
                <span class="w-badge w-badge-{{ $quiz->quiz_type }}">{{ ucfirst($quiz->quiz_type) }}</span>
            </div>
            @auth
                <button type="button" class="btn btn-sm border-0 p-1 wBookmarkBtn"
                        data-url="{{ route('website.bookmark.toggle') }}" data-type="quiz" data-id="{{ $quiz->id }}"
                        aria-pressed="false" aria-label="Bookmark {{ $quiz->title }}">
                    <i class="bi bi-bookmark"></i>
                </button>
            @endauth
        </div>

        <h3 class="w-card-title">
            <a href="{{ route('website.quiz.show', $quiz->slug) }}">{{ $quiz->title }}</a>
        </h3>

        @if ($quiz->category)
            <p class="w-text-sm w-muted mb-3">
                <i class="bi bi-folder2" aria-hidden="true"></i>
                {{ $quiz->category->name }}@if ($quiz->subCategory) <span class="w-muted">&rsaquo;</span> {{ $quiz->subCategory->name }}@endif
            </p>
        @endif

        <div class="w-meta mt-auto">
            <span><i class="bi bi-question-circle" aria-hidden="true"></i> {{ $qCount }} Qs</span>
            <span><i class="bi bi-clock" aria-hidden="true"></i> {{ $quiz->time_limit ? $quiz->time_limit . ' min' : 'No limit' }}</span>
            <span><i class="bi bi-check2-circle" aria-hidden="true"></i> {{ $quiz->pass_percentage }}% to pass</span>
            @if ($xp)
                <span class="text-warning fw-semibold"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> {{ $xp }} XP</span>
            @endif
        </div>
    </div>
    <div class="w-card-footer">
        <a href="{{ route('website.quiz.show', $quiz->slug) }}" class="btn w-btn-primary btn-sm w-100">
            <i class="bi bi-play-fill" aria-hidden="true"></i> Start Quiz
        </a>
    </div>
</article>
