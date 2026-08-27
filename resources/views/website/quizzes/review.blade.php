@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/quiz.css') }}" rel="stylesheet">
@endpush

@section('breadcrumb')
    <x-website::breadcrumbs :trail="[
        'Home' => route('home'),
        $quiz->title => route('website.quiz.show', $quiz->slug),
        'Answer Review' => route('website.quiz.review', $attempt->id),
    ]" />
@endsection

@section('content')
<section class="w-section">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="h3 mb-1">Answer Review</h1>
                <p class="w-muted mb-0">{{ $quiz->title }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('website.quiz.result', $attempt->id) }}" class="btn w-btn-outline btn-sm">Back to result</a>
                <a href="{{ route('website.quiz.show', $quiz->slug) }}" class="btn w-btn-primary btn-sm">Retake quiz</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                ['is-correct', $attempt->correct_count, 'Correct'],
                ['is-wrong', $attempt->wrong_count, 'Wrong'],
                ['is-skipped', $attempt->skipped_count, 'Skipped'],
                ['', round($attempt->percentage) . '%', 'Score'],
            ] as [$cls, $value, $label])
                <div class="col-6 col-md-3">
                    <div class="w-stat-tile {{ $cls }}"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
                </div>
            @endforeach
        </div>

        @foreach ($answers as $answer)
            @php
                $q = $answer->question;
                $state = $answer->isSkipped() ? 'is-skipped' : ($answer->is_correct ? 'is-correct' : 'is-wrong');
            @endphp
            @continue(!$q)

            <article class="w-review-item">
                <div class="w-review-head {{ $state }}">
                    <strong>Question {{ $answer->question_order }}</strong>
                    <span class="w-badge">
                        @if ($answer->isSkipped())
                            <i class="bi bi-dash-circle"></i> Skipped
                        @elseif ($answer->is_correct)
                            <i class="bi bi-check-circle-fill"></i> Correct
                        @else
                            <i class="bi bi-x-circle-fill"></i> Wrong
                        @endif
                    </span>
                </div>

                <div class="w-review-body">
                    <p class="fw-semibold mb-3">{{ $q->question_text }}</p>

                    @foreach ($q->options as $option)
                        @php
                            $isCorrect = (bool) $option->is_correct;
                            $isChosen  = $answer->selected_option_id === $option->id;
                            $cls = $isCorrect ? 'is-correct' : ($isChosen ? 'is-chosen-wrong' : '');
                        @endphp
                        <div class="w-review-option {{ $cls }}">
                            <span class="w-option-key">{{ chr(65 + $loop->index) }}</span>
                            <span class="flex-grow-1">{{ $option->option_text }}</span>
                            @if ($isCorrect)
                                <span class="w-badge w-badge-free flex-shrink-0"><i class="bi bi-check"></i> Correct answer</span>
                            @elseif ($isChosen)
                                <span class="w-badge w-badge-hard flex-shrink-0"><i class="bi bi-x"></i> Your answer</span>
                            @endif
                        </div>
                    @endforeach

                    @if ($quiz->show_explanation && $q->explanation)
                        <div class="w-explanation mt-3">
                            <strong><i class="bi bi-lightbulb"></i> Explanation:</strong> {{ $q->explanation }}
                        </div>
                    @endif

                    @auth
                        <button type="button" class="btn w-btn-outline btn-sm mt-3 wBookmarkBtn"
                                data-url="{{ route('website.bookmark.toggle') }}" data-type="question" data-id="{{ $q->id }}"
                                aria-pressed="false">
                            <i class="bi bi-bookmark"></i> Save this question
                        </button>
                    @endauth
                </div>
            </article>
        @endforeach
    </div>
</section>
@endsection
