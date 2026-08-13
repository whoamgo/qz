@extends('admin.layouts.app')
@section('panel')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between flex-wrap gap-2 align-items-center">
                    <div>
                        <h5 class="mb-0">{{ __($quiz->title) }}</h5>
                        <small class="text-muted">{{ __($quiz?->category?->name ?? '') }} @if($quiz->subCategory) / {{ __($quiz->subCategory->name) }} @endif</small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        @php echo $quiz->difficultyBadge; @endphp
                        @php echo $quiz->quizStatusBadge; @endphp
                        <span class="badge badge--info">{{ $quiz->questions->count() }} @lang('Questions')</span>
                        @if($quiz->time_limit > 0)
                            <span class="badge badge--warning"><i class="las la-clock"></i> {{ $quiz->time_limit }} @lang('Min')</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if($quiz->description)
                        <div class="alert alert-info mb-4">
                            <p class="mb-0">{{ __($quiz->description) }}</p>
                        </div>
                    @endif

                    <div class="row mb-4">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3 text-center">
                                <small class="text-muted d-block">@lang('Marks')</small>
                                <h5 class="mb-0">{{ $quiz->marks_per_correct }} / @lang('Correct')</h5>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3 text-center">
                                <small class="text-muted d-block">@lang('Negative')</small>
                                <h5 class="mb-0">{{ $quiz->negative_marking }} / @lang('Wrong')</h5>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3 text-center">
                                <small class="text-muted d-block">@lang('Passing')</small>
                                <h5 class="mb-0">{{ $quiz->pass_percentage }}%</h5>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="border rounded p-3 text-center">
                                <small class="text-muted d-block">@lang('Total Marks')</small>
                                <h5 class="mb-0">{{ $quiz->questions->sum(function($q) use ($quiz) { return $q->pivot->marks ?? $quiz->marks_per_correct; }) }}</h5>
                            </div>
                        </div>
                    </div>

                    <div id="quizPreviewQuestions">
                        @forelse($quiz->questions as $qIndex => $question)
                            <div class="preview-question border rounded p-4 mb-4" data-question-id="{{ $question->id }}">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="mb-0">
                                        <span class="badge badge--info me-2">Q{{ $qIndex + 1 }}</span>
                                        <span class="badge badge--{{ $question->difficulty == 'easy' ? 'success' : ($question->difficulty == 'medium' ? 'warning' : 'danger') }} me-2">{{ ucfirst($question->difficulty) }}</span>
                                        <span class="badge badge--dark me-2">{{ $question->pivot->marks ?? $quiz->marks_per_correct }} @lang('Marks')</span>
                                    </h6>
                                </div>
                                <div class="question-text mb-4">
                                    <h5>{{ __($question->question_text) }}</h5>
                                </div>
                                <div class="question-options">
                                    @php
                                        $options = $question->options;
                                        if ($quiz->randomize_options) {
                                            $options = $options->shuffle();
                                        }
                                    @endphp
                                    @foreach($options as $optIndex => $opt)
                                        <label class="option-item d-flex align-items-start gap-3 p-3 mb-2 border rounded cursor-pointer hover:bg-light {{ $question->correct_option_id == $opt->id ? 'border-success bg-success bg-opacity-10' : '' }}">
                                            <div class="form-check mt-1">
                                                <input class="form-check-input preview-option-input" type="radio" name="q_{{ $question->id }}" value="{{ $opt->id }}" data-question-id="{{ $question->id }}" data-option-id="{{ $opt->id }}" data-is-correct="{{ $question->correct_option_id == $opt->id ? 1 : 0 }}">
                                            </div>
                                            <div class="flex-grow-1">
                                                <span class="me-2">{{ chr(65 + $optIndex) }}.</span>
                                                <span>{{ __($opt->option_text) }}</span>
                                                @if($question->correct_option_id == $opt->id)
                                                    <span class="badge badge--success ms-2"><i class="las la-check"></i> @lang('Correct Answer')</span>
                                                @endif
                                            </div>
                                        </label>
                                    @endforeach
                                </div>
                                @if($quiz->show_explanation && $question->explanation)
                                    <div class="explanation-box mt-3 p-3 bg-primary bg-opacity-5 rounded border-left border-primary" style="border-left: 3px solid #4634ff;">
                                        <small class="d-block text-muted mb-1"><i class="las la-lightbulb"></i> <strong>@lang('Explanation')</strong></small>
                                        <p class="mb-0">{{ __($question->explanation) }}</p>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center py-5">
                                <i class="las la-question-circle fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">@lang('No questions found in this quiz.')</h4>
                            </div>
                        @endforelse
                    </div>

                    @if($quiz->questions->count() > 0 && $quiz->show_result)
                        <div class="card bg-light mt-4">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h6 class="mb-0">@lang('Check Your Score')</h6>
                                        <small class="text-muted">@lang('This preview simulates the quiz experience.')</small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button type="button" id="calculateScoreBtn" class="btn btn--primary btn--lg">
                                            <i class="las la-calculator"></i> @lang('Calculate Score')
                                        </button>
                                    </div>
                                </div>
                                <div id="scoreResult" class="mt-4" style="display: none;"></div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.quiz.show', $quiz->id) }}" />
    <a class="btn btn--lg btn-outline--primary" href="{{ route('admin.quiz.index') }}">
        <i class="las la-list"></i>@lang('All Quizzes')
    </a>
@endpush

@push('style')
    <style>
        .option-item {
            transition: all 0.2s;
        }
        .option-item:hover {
            background-color: #f8f9fa;
        }
        .explanation-box {
            display: block;
        }
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            @php
                $quizJson = [
                    'id' => $quiz->id,
                    'marks_per_correct' => $quiz->marks_per_correct,
                    'negative_marking' => $quiz->negative_marking,
                    'pass_percentage' => $quiz->pass_percentage,
                ];
            @endphp
            let quiz = @json($quizJson);

            $('#calculateScoreBtn').on('click', function() {
                let totalQuestions = $('.preview-question').length;
                let correctCount = 0;
                let wrongCount = 0;
                let unanswered = 0;
                let totalMarks = 0;
                let maxMarks = 0;

                $('.preview-question').each(function() {
                    let qId = $(this).data('question-id');
                    let selected = $(this).find('input[name="q_' + qId + '"]:checked');
                    let questionMarks = parseFloat($(this).find('.badge--dark').text()) || quiz.marks_per_correct;
                    maxMarks += questionMarks;

                    if (selected.length) {
                        if (selected.data('is-correct') == 1) {
                            correctCount++;
                            totalMarks += questionMarks;
                        } else {
                            wrongCount++;
                            totalMarks -= quiz.negative_marking;
                        }
                    } else {
                        unanswered++;
                    }
                });

                let percentage = maxMarks > 0 ? Math.max(0, ((totalMarks / maxMarks) * 100)) : 0;
                let passed = percentage >= quiz.pass_percentage;

                let html = `
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="card bg-white h-100">
                                <div class="card-body text-center">
                                    <small class="text-muted d-block mb-1">@lang('Total Questions')</small>
                                    <h3 class="mb-0">${totalQuestions}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-white h-100">
                                <div class="card-body text-center">
                                    <small class="text-muted d-block mb-1">@lang('Correct')</small>
                                    <h3 class="mb-0 text--success">${correctCount}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-white h-100">
                                <div class="card-body text-center">
                                    <small class="text-muted d-block mb-1">@lang('Wrong')</small>
                                    <h3 class="mb-0 text--danger">${wrongCount}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="card bg-white h-100">
                                <div class="card-body text-center">
                                    <small class="text-muted d-block mb-1">@lang('Score')</small>
                                    <h3 class="mb-0">${totalMarks.toFixed(2)} / ${maxMarks.toFixed(2)}</h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="card ${passed ? 'bg-success bg-opacity-10 border-success' : 'bg-danger bg-opacity-10 border-danger'}">
                                <div class="card-body text-center">
                                    <h3 class="mb-2">
                                        ${passed ? '<i class="las la-check-circle text--success"></i> @lang('PASSED')' : '<i class="las la-times-circle text--danger"></i> @lang('FAILED')'}
                                    </h3>
                                    <p class="mb-0">
                                        @lang('Your Score:') <strong>${percentage.toFixed(2)}%</strong>
                                        @lang('(Required:') ${quiz.pass_percentage}%)
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                $('#scoreResult').html(html).slideDown();
            });

        })(jQuery)
    </script>
@endpush
