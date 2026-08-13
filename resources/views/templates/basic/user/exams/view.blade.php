@extends('Template::layouts.frontend')
@section('content')
    <section class="exam-section my-120">
        <div class="container">
            <div class="mb-60">
                <ul class="exam-info">
                    <li class="exam-info-item">
                        <span class="label">@lang('Subject') </span>
                        <span class="value">{{ implode(', ', $exam->subjects) }}</span>
                    </li>
                    <li class="exam-info-item">
                        <span class="label">@lang('Difficulty') </span>
                        <span class="value">{{ __(ucfirst($exam->difficulty)) }}</span>
                    </li>
                    <li class="exam-info-item">
                        <span class="label">@lang('Type') </span>
                        <span class="value">
                            @if ($exam->isFree())
                                <span class="badge badge--primary">@lang('Free')</span>
                            @else
                                <span class="badge badge--dark">@lang('Paid') - {{ showAmount($exam->price) }}</span>
                            @endif
                        </span>
                    </li>
                    <li class="exam-info-item">
                        <span class="label">@lang('Total Question') </span>
                        <span class="value">{{ getAmount($exam->question_quantity) }}</span>
                    </li>
                    <li class="exam-info-item">
                        <span class="label">@lang('Total Time') </span>
                        <span class="value">{{ getAmount($exam->duration) }} @lang('Minutes')</span>
                    </li>
                    <li class="exam-info-item">
                        <span class="label">@lang('Pass Mark') </span>
                        <span class="value">{{ getAmount($exam->pass_percentage) }} @lang('%')</span>
                    </li>
                </ul>
            </div>
            <div class="exam-qsn-wrapper">

                <div class="exam-qsn-result mb-4">
                    @if ($attendExam->status == Status::EXAM_INITIATE)
                        <div class="alert alert--danger align-items-center">
                            <span class="alert__icon">
                                <i class="las la-times-circle"></i>
                            </span>
                            <p class="alert-desc fw-semibold text--danger">@lang('You have not started the exam yet.')
                                <a href="{{ route('user.exam.start', $attendExam?->exam?->slug) }}" class="text--base text-decoration-underline fs-15">@lang('Start Now')</a>
                            </p>
                        </div>
                    @elseif($attendExam->status == Status::WAITING_RESULT)
                        <div class="alert alert--success align-items-center">
                            <span class="alert__icon">
                                <i class="las la-check-circle"></i>
                            </span>
                            <p class="alert__content fw-semibold text--success">
                                @lang('Your exam is submitted. Please wait for the result.')
                            </p>
                        </div>
                    @elseif($attendExam->status == Status::EXAM_COMPLETED)
                        <div class="alert {{ $attendExam->pass_percentage >= $exam->pass_percentage ? 'alert--success' : 'alert--danger' }} align-items-center">
                            <span class="alert__icon">
                                <i class="las {{ $attendExam->pass_percentage >= $exam->pass_percentage ? 'la-check-circle' : 'la-times-circle' }}"></i>
                            </span>
                            <p class="alert__content fw-semibold">
                                @lang('You answered')
                                <span class="count">
                                    {{ $attendExam->correct_answer }}
                                </span>
                                @lang('correct out of')
                                <span class="count">
                                    {{ getAmount($exam->question_quantity) }}
                                </span>.
                                @lang('Your score:') <span class="count">{{ getAmount($attendExam->pass_percentage) }}%</span>
                                @if ($attendExam->pass_percentage >= $exam->pass_percentage)
                                    <span class="badge badge--success ms-2">@lang('PASSED')</span>
                                    @if ($exam->isPaid())
                                        <a href="{{ route('user.exam.certificate.download', $attendExam->id) }}" class="btn btn--info btn--sm ms-2">
                                            <i class="las la-certificate me-1"></i> @lang('Download Certificate')
                                        </a>
                                    @else
                                        <span class="badge badge--primary ms-2">@lang('Free Exam - No Certificate')</span>
                                    @endif
                                @else
                                    <span class="badge badge--danger ms-2">@lang('FAILED')</span>
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
                @foreach ($exam->questions as $key => $question)
                    <div class="exam-qsn">
                        <div class="exam-qsn-body">
                            <h5 class="exam-qsn-title">
                                {{ $key + 1 }}. {{ __($question->title) }}
                            </h5>
                            <div class="exam-qsn-list">
                                @php
                                    $optionLetter = ['A', 'B', 'C', 'D'];
                                @endphp
                                @foreach ($question->options as $option)
                                    <label class="exam-qsn-item @if ($attendExam->status == Status::EXAM_COMPLETED) {{ $question->result_option_id == $option->id ? 'correct-answer' : '' }} @endif"
                                           for="qsn-{{ $question->id }}-{{ $option->id }}">
                                        <span class="exam-qsn-item__label">
                                            {{ $optionLetter[$loop->iteration - 1] }}
                                        </span>

                                        {{ __($option->title) }}

                                        <input type="radio" class="d-none" name="answer[{{ $question->id }}]"
                                               value="{{ $option->id }}" id="qsn-{{ $question->id }}-{{ $option->id }}"
                                               @checked(isset($examAnswer[$question->id]) && $examAnswer[$question->id] == $option->id)>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection


@if ($attendExam->status == Status::EXAM_COMPLETED)
    @push('style')
        <style>
            .expired {
                background-color: hsl(var(--danger));
                padding: 10px 20px;
                border-radius: 5px;
                color: hsl(var(--white));
                font-weight: 500;
            }

            .exam-qsn-item {
                pointer-events: none;
            }

            .exam-qsn-item:has(input:checked) .exam-qsn-item__label {
                background-color: hsl(var(--danger));
                color: hsl(var(--white));
            }

            .exam-qsn-item:has(input:checked) {
                background-color: hsl(var(--danger) / .05);
                border-color: hsl(var(--danger) / .5);
            }

            .exam-qsn-item.correct-answer .exam-qsn-item__label {
                background-color: hsl(var(--success)) !important;
                color: hsl(var(--white)) !important;
            }

            .exam-qsn-item.correct-answer {
                background-color: hsl(var(--success) / .05) !important;
                border-color: hsl(var(--success) / .5) !important;
            }
        </style>
    @endpush
@else
    @push('style')
        <style>
            .expired {
                background-color: hsl(var(--danger));
                padding: 10px 20px;
                border-radius: 5px;
                color: hsl(var(--white));
                font-weight: 500;
            }

            .exam-qsn-item {
                pointer-events: none;
            }
        </style>
    @endpush
@endif
