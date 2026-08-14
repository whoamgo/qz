@extends('admin.layouts.app')

@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="exam-section">
                <div class="exam-details-top mb-60">
                    <ul class="exam-info">
                        <li class="exam-info-item">
                            <span class="label">@lang('Subject') </span>
                            <span class="value">{{ implode(', ', $exam->subjects) }}</span>
                        </li>
                        <li class="exam-info-item">
                            <span class="label">@lang('Total Question') </span>
                            <span class="value">{{ getAmount($exam->question_quantity) }}</span>
                        </li>
                        <li class="exam-info-item">
                            <span class="label">@lang('Total Time') </span>
                            <span class="value">{{ getAmount($exam->duration) }} @lang('Minutes')</span>
                        </li>
                    </ul>

                    <div class="exam-qsn-result">
                        @if ($attendExam->status == Status::EXAM_INITIATE)
                            @lang('You have not started the exam yet.')
                        @elseif($attendExam->status == Status::WAITING_RESULT)
                            @lang('Your exam is submitted. Please wait for the result.')
                        @elseif($attendExam->status == Status::EXAM_COMPLETED)
                            @lang('You answered')
                            <span class="count">{{ $attendExam->correct_answer }}</span>
                            @lang('correct out of')
                            <span class="count">{{ getAmount($exam->question_quantity) }}</span>.
                        @endif
                    </div>
                </div>
                <div class="exam-qsn-wrapper">
                    @foreach ($exam->questions as $key => $question)
                        <div class="exam-qsn">
                            <div class="exam-qsn-header">
                                <h5 class="exam-qsn-title">
                                    {{ $key + 1 }}. {{ $question->title }}
                                </h5>
                            </div>
                            <div class="exam-qsn-body">
                                <ul class="exam-qsn-list">
                                    @foreach ($question->options as $option)
                                        <li class="exam-qsn-item form--check @if ($attendExam->status == Status::EXAM_COMPLETED) {{ $question->result_option_id == $option->id ? 'correct-answer' : '' }} @endif">
                                            <input type="radio" class="form-check-input" name="answer[{{ $question->id }}]"
                                                   value="{{ $option->id }}" id="qsn-{{ $question->id }}-{{ $option->id }}" @checked(isset($examAnswer[$question->id]) && $examAnswer[$question->id] == $option->id)>
                                            <label for="qsn-{{ $question->id }}-{{ $option->id }}">{{ __($option->title) }}</label>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .exam-details-top {
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex;
            -webkit-box-align: center;
            -ms-flex-align: center;
            align-items: center;
            gap: 12px;
            -webkit-box-pack: justify;
            -ms-flex-pack: justify;
            justify-content: space-between;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 12px;
            -ms-flex-wrap: wrap;
            flex-wrap: wrap;
            gap: 24px;
        }

        .exam-info {
            border-radius: 8px;
        }

        .exam-info-item {
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex;
            -webkit-box-align: center;
            -ms-flex-align: center;
            align-items: center;
            gap: 16px;
            font-size: 1rem;
        }

        .exam-info-item:not(:last-child) {
            margin-bottom: 12px;
        }

        .exam-info-item .label {
            font-weight: 500;
            color: hsl(var(--white));
            min-width: 132px;
            position: relative;
        }

        .exam-info-item .label:after {
            content: ":";
            position: absolute;
            right: 0px;
            top: 50%;
            -webkit-transform: translateY(-50%);
            transform: translateY(-50%);
            color: hsl(var(--white)/0.5);
        }

        .exam-info-item .value {
            font-weight: 500;
        }


        .exam-qsn-wrapper {
            width: 100%;
            margin: 0 auto;
        }

        .exam-qsn-result {
            font-weight: 600;
            font-size: 1rem;
        }

        .exam-qsn-result .count {
            color: #0087ff;
            font-size: 1.5rem;
        }

        .expired {
            background-color: #eb2222;
            padding: 10px 20px;
            border-radius: 5px;
            color: hsl(var(--white));
            font-weight: 500;
        }

        .exam-qsn {
            border: 1px solid hsl(0deg 0% 0% / 10%);
            border-radius: 12px;
            overflow: hidden;
        }


        .exam-qsn:not(:last-child) {
            margin-bottom: 20px;
        }

        .exam-qsn-header {
            padding: 20px;
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex;
            gap: 12px;
            -webkit-box-align: center;
            -ms-flex-align: center;
            align-items: center;
            background-color: hsl(231 84% 17% / 5%);
            border-bottom: 1px solid hsl(0deg 0% 0% / 10%);
        }

        .exam-qsn-body {
            padding: 20px;
            background-color: hsl(var(--base)/0.05);
        }

        .exam-qsn-item {
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex;
            -webkit-box-align: center;
            -ms-flex-align: center;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .exam-qsn-item:not(:last-child) {
            margin-bottom: 12px;
        }

        .exam-qsn-item label {
            cursor: pointer;
            margin-bottom: 0px;
        }

        .exam-qsn-item input {
            margin: 0;
        }

        .exam-qsn-item.correct-answer {
            color: #28c76f;
        }

        .expired {
            background-color: #eb2222;
            padding: 10px 20px;
            border-radius: 5px;
            color: #ffffff;
            font-weight: 500;
        }

        .exam-qsn-item {
            pointer-events: none;
        }

        .exam-qsn-item:has(input:checked) label {
            color: #eb2222;
        }


        .exam-qsn-item.correct-answer label {
            color: #28c76f !important;
        }
    </style>
@endpush
