@extends('Template::layouts.frontend')
@section('content')
    <section class="exam-section my-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-xxl-10 col-lg-11">
                    <div class="exam-countdown">
                        <div class="exam-countdown-time">
                            <div class="exam-countdown-time__content">
                                <p class="box">
                                    <span class="exam-countdown-time__hrs box-style"></span>
                                    <span class="box__text">@lang('Hours')</span>
                                </p>
                                <p class="box">
                                    <span class="exam-countdown-time__min box-style"></span>
                                    <span class="box__text">@lang('Min')</span>
                                </p>
                                <p class="box">
                                    <span class="exam-countdown-time__sec box-style"></span>
                                    <span class="box__text">@lang('Sec')</span>
                                </p>
                            </div>
                        </div>
                    </div>

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
                                    <span class="text-muted small ms-1">(@lang('No Certificate - Results Only'))</span>
                                @else
                                    <span class="badge badge--dark">@lang('Paid') - {{ showAmount($exam->price) }}</span>
                                    <span class="text-muted small ms-1">(@lang('Certificate on Pass'))</span>
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
                    <div class="exam-qsn-wrapper">
                        <form action="{{ route('user.exam.submit', $exam->slug) }}" method="POST" id="examForm">
                            @csrf
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
                                                <label class="exam-qsn-item"
                                                       for="qsn-{{ $question->id }}-{{ $option->id }}">
                                                    <span class="exam-qsn-item__label">
                                                        {{ $optionLetter[$loop->iteration - 1] }}
                                                    </span>

                                                    {{ __($option->title) }}

                                                    <input type="radio" class="d-none" name="answer[{{ $question->id }}]"
                                                           value="{{ $option->id }}"
                                                           id="qsn-{{ $question->id }}-{{ $option->id }}"
                                                           {{ old("answer.$question->id") == $option->id ? 'checked' : '' }}>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                            @if (!$examEnd)
                                <div class="text-end">
                                    <button class="btn btn--base px-5" type="submit">@lang('Submit Exam')</button>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('style')
    <style>
        .expired {
            background-color: hsl(var(--danger));
            padding: 10px 20px;
            border-radius: 5px;
            color: hsl(var(--white));
            font-weight: 500;
        }
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            var endTime = "{{ \Carbon\Carbon::parse($attendExam->end_time)->toIso8601String() }}";
            var countDownDate = new Date(endTime).getTime();



            function setCowndownProgress(className, gradientAngle) {
                var gradient = "conic-gradient(hsl(var(--base)) " + gradientAngle +
                    "deg, hsl(var(--base) / .1) 0deg)";
                $(className).css("background", gradient);
            }

            var countDownDate = new Date(endTime).getTime();
            var countdownInterval = setInterval(function() {
                var nowTime = new Date().getTime();
                var distance = countDownDate - nowTime;

                console.log(distance);


                if (distance <= 0 || isNaN(distance)) {
                    clearInterval(countdownInterval);
                    $(".exam-countdown-time__hrs").text("00");
                    $(".exam-countdown-time__min").text("00");
                    $(".exam-countdown-time__sec").text("00");
                    $(".exam-countdown-time__content").html("<p class='expired'>EXPIRED</p>");
                    return;
                }

                var hours = Math.floor((distance / (1000 * 60 * 60)) % 24);
                var minutes = Math.floor((distance / (1000 * 60)) % 60);
                var seconds = Math.floor((distance / 1000) % 60);

                $(".exam-countdown-time__hrs").text(hours.toString().padStart(2, '0'));
                $(".exam-countdown-time__min").text(minutes.toString().padStart(2, '0'));
                $(".exam-countdown-time__sec").text(seconds.toString().padStart(2, '0'));

                setCowndownProgress(".exam-countdown-time__hrs", (hours / 24) * 360);
                setCowndownProgress(".exam-countdown-time__min", (minutes / 60) * 360);
                setCowndownProgress(".exam-countdown-time__sec", (seconds / 60) * 360);

            }, 1000);


            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                const saved = localStorage.getItem(radio.name);
                if (saved && saved == radio.value) {
                    radio.checked = true;
                }
                radio.addEventListener('change', function() {
                    localStorage.setItem(radio.name, this.value);
                });
            });

            document.getElementById('examForm').addEventListener('submit', function() {
                localStorage.clear();
            });

        })(jQuery)
    </script>
@endpush
