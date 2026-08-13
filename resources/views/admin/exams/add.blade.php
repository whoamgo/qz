@extends('admin.layouts.app')
@section('panel')
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card ">
                <div class="card-body">
                    <form action="{{ route('admin.exams.store', $exam?->id ?? 0) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="field-wrapper mb-4">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Exam Image')</p>
                                <p class="note">@lang('Upload the image representing the exam')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <x-image-uploader image="{{ $exam?->image ?? '' }}" class="w-100" type="exam" :required="false" />
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Category')</p>
                                <p class="note">@lang('The name of the exam category (e.g., Government Job, Bank Job, Academic, IT Certification).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <select name="category_id" class="form-control select2" required>
                                    <option value="" selected disabled>@lang('Select One')</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category_id', $exam?->category_id) == $category->id)>{{ __($category->name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Difficulty')</p>
                                <p class="note">@lang('Select the difficulty level of this exam.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <select name="difficulty" class="form-control select2" required>
                                    <option value="easy" @selected(old('difficulty', $exam?->difficulty) == 'easy')>@lang('Easy')</option>
                                    <option value="medium" @selected(old('difficulty', $exam?->difficulty ?? 'medium') == 'medium')>@lang('Medium')</option>
                                    <option value="hard" @selected(old('difficulty', $exam?->difficulty) == 'hard')>@lang('Hard')</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Exam Type')</p>
                                <p class="note">@lang('Free exams do not generate certificates. Paid exams generate certificates on passing.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <select name="exam_type" id="exam_type" class="form-control select2" required>
                                    <option value="free" @selected(old('exam_type', $exam?->exam_type ?? 'free') == 'free')>@lang('Free')</option>
                                    <option value="paid" @selected(old('exam_type', $exam?->exam_type) == 'paid')>@lang('Paid')</option>
                                </select>
                            </div>
                        </div>

                        <div class="field-wrapper" id="price_wrapper" style="display: {{ old('exam_type', $exam?->exam_type ?? 'free') == 'paid' ? 'flex' : 'none' }};">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Price')</p>
                                <p class="note">@lang('Enter the price for this paid exam.')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <span class="input-group-text">{{ gs('cur_sym') }}</span>
                                    <input type="number" step="any" name="price" id="price_input" class="form-control" value="{{ old('price', $exam?->price ?? 0) }}" min="0">
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Exam Title')</p>
                                <p class="note">@lang('The name of the exam or test (e.g., Basic Math Test, English Grammar Quiz, Logical Reasoning Practice).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input type="text" name="title" class="form-control" value="{{ old('title', $exam?->title) }}" required>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Duration')</p>
                                <p class="note">@lang('The total time allocated for the exam (e.g., 30 minutes, 1 hour, 2 hours).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <input type="text" name="duration" class="form-control" value="{{ old('duration', $exam?->duration) }}" required>
                                    <span class="input-group-text">@lang('Minutes')</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Total Question')</p>
                                <p class="note">@lang('The total number of questions included in the exam (e.g., 10, 20, 50).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <input type="text" name="question_quantity" class="form-control" value="{{ old('question_quantity', $exam?->question_quantity) }}" required>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Pass Mark Percentage')</p>
                                <p class="note">@lang('The minimum score required to pass the exam (e.g., 50%, 60%, 75%).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <div class="input-group">
                                    <input type="text" name="pass_percentage" class="form-control" value="{{ old('pass_percentage', $exam?->pass_percentage) }}" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>

                        <div class="field-wrapper">
                            <div class="field-wrapper-label">
                                <p class="title">@lang('Subjects') <i class="las la-info-circle" title="@lang('Separate multiple keywords by comma or enter key')"></i></p>
                                <p class="note">@lang('Select the subjects or topics covered in this exam (e.g., Mathematics, English, Logical Reasoning).')</p>
                            </div>
                            <div class="field-wrapper-view">
                                <select name="subjects[]" class="form-control select2-auto-tokenize" multiple="multiple" required>
                                    @if (isset($exam->subjects) && $exam->subjects)
                                        @foreach ($exam->subjects as $subject)
                                            <option value="{{ $subject }}" selected>{{ __($subject) }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>
                        <div class="field-wrapper" id="date_fields_wrapper" style="display: {{ old('exam_type', $exam?->exam_type ?? 'free') == 'paid' ? 'contents' : 'none' }};">
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">@lang('Exam Started At')</p>
                                    <p class="note">@lang('The date and time when the exam will begin. (Required for Paid exams)')</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="start_at" id="start_at" class="form-control" value="{{ old('start_at', isset($exam?->start_at) ? showDateTime($exam?->start_at, 'Y-m-d h:i:s A') : '') }}" placeholder="@lang('Start Date')">
                                </div>
                            </div>
                            <div class="field-wrapper">
                                <div class="field-wrapper-label">
                                    <p class="title">@lang('Result Published At')</p>
                                    <p class="note">@lang('The date and time when the exam results will be released. (Required for Paid exams)')</p>
                                </div>
                                <div class="field-wrapper-view">
                                    <input type="text" name="result_published_at" id="result_published_at" class="form-control" value="{{ old('result_published_at', isset($exam?->result_published_at) ? showDateTime($exam?->result_published_at, 'Y-m-d h:i:s A') : '') }}" placeholder="@lang('End Date')">
                                </div>
                            </div>
                        </div>
                        <button class="btn btn--primary w-100 h-45 mt-3" type="submit">@lang('Submit')</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection


@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.exams.index') }}" />
@endpush

@push('style-lib')
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/admin/css/daterangepicker.css') }}">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/admin/js/moment.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/daterangepicker.min.js') }}"></script>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            const examTypeSelect = $('#exam_type');
            const priceWrapper = $('#price_wrapper');
            const priceInput = $('#price_input');
            const dateFieldsWrapper = $('#date_fields_wrapper');
            const startInput = $('#start_at');
            const endInput = $('#result_published_at');

            function toggleExamTypeFields() {
                if (examTypeSelect.val() === 'paid') {
                    priceWrapper.css('display', 'flex');
                    priceInput.attr('required', 'required');
                    dateFieldsWrapper.css('display', 'contents');
                    startInput.attr('required', 'required');
                    endInput.attr('required', 'required');
                } else {
                    priceWrapper.css('display', 'none');
                    priceInput.removeAttr('required');
                    priceInput.val(0);
                    dateFieldsWrapper.css('display', 'none');
                    startInput.removeAttr('required');
                    endInput.removeAttr('required');
                    startInput.val('');
                    endInput.val('');
                }
            }

            examTypeSelect.on('change', toggleExamTypeFields);
            toggleExamTypeFields();

            const existingStart = startInput.val() ? startInput.val() : moment();
            const existingEnd = endInput.val() ? moment(endInput.val()) : moment().add(7, 'days');

            startInput.daterangepicker({
                singleDatePicker: true,
                timePicker: true,
                showDropdowns: true,
                startDate: existingStart,
                minDate: existingStart,
                locale: {
                    format: 'YYYY-MM-DD hh:mm A'
                }
            });

            endInput.daterangepicker({
                singleDatePicker: true,
                timePicker: true,
                showDropdowns: true,
                startDate: existingEnd,
                minDate: moment(),
                locale: {
                    format: 'YYYY-MM-DD hh:mm A'
                }
            });

            startInput.on('apply.daterangepicker', function(ev, picker) {
                const selectedStart = picker.startDate;
                endInput.data('daterangepicker').minDate = selectedStart;
                if (moment(endInput.val()).isBefore(selectedStart)) {
                    endInput.data('daterangepicker').setStartDate(selectedStart.clone().add(7, 'days'));
                }
            });

        })(jQuery)
    </script>
@endpush

@push('style')
    <style>
        .iconpicker .iconpicker-item {
            color: rgba(0, 0, 0, 0.8);
        }

        .card.bg-card-style .card-heading {
            padding: 12px 16px;
            background-color: rgb(0, 0, 0, 5%);
            border-radius: 8px;
            margin-bottom: 24px
        }

        .card.bg-card-style .card-body {}

        .field-wrapper {
            display: flex;
            align-items: center;
            gap: 16px 24px;
            flex-wrap: wrap;
        }

        .field-wrapper:not(:last-child) {
            margin-bottom: 24px;
        }

        .field-wrapper-label {
            width: 320px;
        }

        .field-wrapper-label .title {
            color: black;
            font-weight: 600;
        }

        .field-wrapper-label .note {
            font-size: 0.75rem;
        }

        .field-wrapper-view {
            flex: 1;
            max-width: 700px;
        }

        .field-wrapper .image-upload-wrapper {
            height: 150px;
            position: relative;
            width: 160px;
        }

        @media (max-width: 767px) {
            .field-wrapper {
                flex-direction: column;
                align-items: flex-start;
            }

            .field-wrapper-label {
                width: 100%;
            }
        }
    </style>
@endpush
