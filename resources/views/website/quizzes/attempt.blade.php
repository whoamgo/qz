@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/quiz.css') }}" rel="stylesheet">
@endpush
@push('body-class') w-quiz-shell @endpush

@section('content')
<div class="w-quiz-bar">
    <div class="container">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="flex-grow-1" style="min-width: 180px;">
                <div class="d-flex justify-content-between w-text-sm mb-1">
                    <strong class="text-truncate">{{ $quiz->title }}</strong>
                    <span id="wProgressLabel" class="w-muted flex-shrink-0 ms-2">0 / {{ count($questions) }} answered</span>
                </div>
                <div class="w-progress" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                     aria-label="Quiz progress">
                    <div class="w-progress-bar" id="wProgressBar" style="width: 0%"></div>
                </div>
            </div>

            @if ($remaining !== null)
                <span class="w-timer" id="wTimer" role="timer" aria-live="off" aria-label="Time remaining">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <span>{{ gmdate($remaining >= 3600 ? 'H:i:s' : 'i:s', $remaining) }}</span>
                </span>
            @else
                <span class="w-timer" id="wTimer"><i class="bi bi-infinity" aria-hidden="true"></i> No limit</span>
            @endif
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4 align-items-start">
        {{-- Question --}}
        <div class="col-lg-8">
            <div class="w-question-card">
                <span class="w-question-num" id="wQuestionNum">Question 1 of {{ count($questions) }}</span>
                <h1 class="w-question-text" id="wQuestionText" aria-live="polite"></h1>

                <div class="w-options" id="wOptions" role="radiogroup" aria-label="Answer options"></div>

                <div class="d-flex flex-wrap gap-2 mt-4 w-quiz-controls">
                    <button type="button" class="btn w-btn-outline" id="wPrevBtn">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i> Previous
                    </button>
                    <button type="button" class="btn w-btn-outline" id="wMarkBtn" aria-pressed="false">
                        <i class="bi bi-flag" aria-hidden="true"></i> Mark for review
                    </button>
                    <button type="button" class="btn w-btn-primary ms-auto" id="wNextBtn">
                        Next <i class="bi bi-chevron-right" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-success" id="wSubmitBtn">
                        <i class="bi bi-check2-circle" aria-hidden="true"></i> Submit
                    </button>
                </div>
            </div>
        </div>

        {{-- Navigator --}}
        <div class="col-lg-4">
            <div class="w-card" style="position: sticky; top: 96px;">
                <div class="w-card-body">
                    <h2 class="w-card-title" style="font-size: var(--w-fs-base);">Question Navigator</h2>

                    <div class="w-q-nav mb-3" id="wQuestionNav" role="group" aria-label="Jump to question"></div>

                    <div class="w-legend">
                        <span><i style="background: var(--w-primary);"></i> Current</span>
                        <span><i style="background: var(--w-success-light); border:1px solid var(--w-success);"></i> Answered</span>
                        <span><i style="background: var(--w-warning-light); border:1px solid var(--w-warning);"></i> Marked</span>
                        <span><i style="background: #fff; border:1px solid var(--w-border);"></i> Not visited</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Submit confirmation --}}
<div class="modal fade" id="wSubmitModal" tabindex="-1" aria-labelledby="wSubmitModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="wSubmitModalLabel">Submit this quiz?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="w-submit-summary mb-3">
                    <div><strong id="wSummaryAnswered">0</strong><span>Answered</span></div>
                    <div><strong id="wSummarySkipped">0</strong><span>Skipped</span></div>
                    <div><strong id="wSummaryMarked">0</strong><span>Marked</span></div>
                </div>
                <p class="w-muted mb-0">
                    Your score is calculated on the server. You cannot change answers after submitting.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn w-btn-outline" data-bs-dismiss="modal">Keep answering</button>
                <button type="button" class="btn btn-success" id="wConfirmSubmit">Yes, submit</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ wAsset('assets/web/js/quiz.js') }}"></script>
    <script>
        // The payload deliberately omits every answer-key field. Grading runs
        // only on the server, in QuizAttemptService, at submit time.
        jQuery(function () {
            window.QuizAttempt.init({
                questions:  @json($questions),
                remaining:  @json($remaining),
                timeLimit:  @json((int) $quiz->time_limit),
                startedAt:  {{ $attempt->started_at ? $attempt->started_at->timestamp * 1000 : 'Date.now()' }},
                answerUrl:  "{{ route('website.quiz.answer', $attempt->id) }}",
                reviewUrl:  "{{ route('website.quiz.mark.review', $attempt->id) }}",
                submitUrl:  "{{ route('website.quiz.submit', $attempt->id) }}"
            });
        });
    </script>
@endpush
