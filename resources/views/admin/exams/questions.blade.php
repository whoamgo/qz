@extends('admin.layouts.app')

@section('panel')
    <div class="row gy-4">
        <div class="col-xxl-4">
            <div class="card">

                <div class="card-header d-flex justify-content-between flex-wrap">
                    <h5>@lang('Exam Information')</h5>
                    <div>@php echo $exam->statusBadge;@endphp</div>
                </div>

                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3 flex-wrap gap-3">
                        <div>
                            <small class="text-muted"> @lang('Category')</small>
                            <h6 class="f-size-16px">{{ $exam?->category?->name ?? '' }}</h6>
                        </div>
                        <div class="text-end">
                            <small class="text-muted"> @lang('Duration')</small>
                            <h6 class="f-size-16px">{{ getAmount($exam->duration) }} @lang('Minutes')</h6>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between flex-wrap gap-3">
                        <div>
                            <small class="text-muted"> @lang('Exam Starts From')</small>
                            <h6 class="f-size-16px"> {{ showDateTime($exam->start_at, 'd M Y, h:i A') }}</h6>
                        </div>

                        <div class="text-end">
                            <small class="text-muted"> @lang('Result Published')</small>
                            <h6 class="f-size-16px"> {{ showDateTime($exam->result_published_at, 'd M Y, h:i A') }}</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-8">
            <div class="card b-radius--10">
                <div class="card-body p-0">
                    <div class="table-responsive--sm table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Title')</th>
                                    <th>@lang('Total Options')</th>
                                    <th>@lang('Answer')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($questions as $question)
                                    <tr>
                                        <td>{{ strLimit(__($question->title), 30) }}</td>
                                        <td>{{ $question->options->count() }}</td>
                                        <td>{{ strLimit($question?->result?->title ?? 'N/A', 30) }}</td>
                                        <td> @php echo $question->statusBadge @endphp </td>
                                        <td>

                                            <div class="button--group">
                                                <button class="btn btn--sm btn-outline--info" type="button"
                                                        data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="las la-ellipsis-v"></i> @lang('Actions')
                                                </button>
                                                <div class="dropdown-menu">

                                                    <button class="dropdown-item editQuestion" data-question="{{ $question }}" data-modal_title="@lang('Edit Question')" type="button">
                                                        <i class="la la-pencil"></i> @lang('Edit')
                                                    </button>

                                                    <button class="dropdown-item option-btn" type="button" data-question="{{ $question }}">
                                                        <i class="la la-list-ol"></i> @lang('Options')
                                                    </button>

                                                    <button class="dropdown-item resultBtn" data-question="{{ $question }}" type="button">
                                                        <i class="las la-check-circle"></i> @lang('Answer')
                                                    </button>

                                                    @if ($question->status)
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.exams.question.status', $question->id) }}" data-question="@lang('Are you sure to disable this question')?">
                                                            <i class="la la-eye-slash"></i> @lang('Disable')
                                                        </button>
                                                    @else
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.exams.question.status', $question->id) }}" data-question="@lang('Are you sure to enable this question')?">
                                                            <i class="la la-eye"></i> @lang('Enable')
                                                        </button>
                                                    @endif

                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">@lang('Question not found yet!')</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($questions->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($questions) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Create or Update Modal --}}
    <div class="modal fade" id="questionModal" role="dialog" tabindex="-1">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="" method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <label>@lang('Title')</label>
                                    <input class="form-control" name="title" type="text" value="{{ old('title') }}" required />
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Option Modal --}}

    <div class="modal" id="optionListModal" role="dialog" tabindex="-1">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">


                        <div class="result-area"></div>

                        <div class="action-area"></div>
                    </div>
                    <div class="table-responsive--sm table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Option')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Option --}}
    <div class="modal" id="optionStoreModal" data-bs-backdrop="static" data-bs-keyboard="false" role="dialog" tabindex="-1">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"></h5>
                    <button class="close close-option-modal" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form id="optionStore" action="" method="POST">
                    <div class="modal-body">
                        <input name="question_id" type="hidden">
                        <div class="form-group">
                            <label>@lang('Option')</label>
                            <input class="form-control" name="title" type="text" value="{{ old('title') }}" required />
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal" id="optionStatusModal" data-bs-backdrop="static" data-bs-keyboard="false" role="dialog" tabindex="-1">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Confirmation Alert!')</h5>
                    <button class="close close-option-modal" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form id="optionStatusForm" action="" method="POST">
                    <div class="modal-body">
                        <p class="question"></p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn--dark close-option-modal" data-bs-dismiss="modal" type="button">@lang('No')</button>
                        <button class="btn btn--primary" type="submit">@lang('Yes')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="promptModal" data-bs-backdrop="static" data-bs-keyboard="false" role="dialog" tabindex="-1">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Make Question with AI')</h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form id="promptForm">
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Prompt')</label>
                            <textarea name="prompt" class="form-control" rows="5" required placeholder="@lang('Describe the type of questions you want. AI will generate questions, options, and answers.')"></textarea>
                        </div>
                        <button class="w-100 btn btn--lg btn--gen generateBtn" type="submit">
                            <span class="btn__border"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="sparkle">
                                <path class="path" stroke-linejoin="round" stroke-linecap="round" stroke="currentColor"
                                      fill="currentColor"
                                      d="M14.187 8.096L15 5.25L15.813 8.096C16.0231 8.83114 16.4171 9.50062 16.9577 10.0413C17.4984 10.5819 18.1679 10.9759 18.903 11.186L21.75 12L18.904 12.813C18.1689 13.0231 17.4994 13.4171 16.9587 13.9577C16.4181 14.4984 16.0241 15.1679 15.814 15.903L15 18.75L14.187 15.904C13.9769 15.1689 13.5829 14.4994 13.0423 13.9587C12.5016 13.4181 11.8321 13.0241 11.097 12.814L8.25 12L11.096 11.187C11.8311 10.9769 12.5006 10.5829 13.0413 10.0423C13.5819 9.50162 13.9759 8.83214 14.186 8.097L14.187 8.096Z">
                                </path>
                                <path class="path" stroke-linejoin="round" stroke-linecap="round" stroke="currentColor"
                                      fill="currentColor"
                                      d="M6 14.25L5.741 15.285C5.59267 15.8785 5.28579 16.4206 4.85319 16.8532C4.42059 17.2858 3.87853 17.5927 3.285 17.741L2.25 18L3.285 18.259C3.87853 18.4073 4.42059 18.7142 4.85319 19.1468C5.28579 19.5794 5.59267 20.1215 5.741 20.715L6 21.75L6.259 20.715C6.40725 20.1216 6.71398 19.5796 7.14639 19.147C7.5788 18.7144 8.12065 18.4075 8.714 18.259L9.75 18L8.714 17.741C8.12065 17.5925 7.5788 17.2856 7.14639 16.853C6.71398 16.4204 6.40725 15.8784 6.259 15.285L6 14.25Z">
                                </path>
                                <path class="path" stroke-linejoin="round" stroke-linecap="round" stroke="currentColor"
                                      fill="currentColor"
                                      d="M6.5 4L6.303 4.5915C6.24777 4.75718 6.15472 4.90774 6.03123 5.03123C5.90774 5.15472 5.75718 5.24777 5.5915 5.303L5 5.5L5.5915 5.697C5.75718 5.75223 5.90774 5.84528 6.03123 5.96877C6.15472 6.09226 6.24777 6.24282 6.303 6.4085L6.5 7L6.697 6.4085C6.75223 6.24282 6.84528 6.09226 6.96877 5.96877C7.09226 5.84528 7.24282 5.75223 7.4085 5.697L8 5.5L7.4085 5.303C7.24282 5.24777 7.09226 5.15472 6.96877 5.03123C6.84528 4.90774 6.75223 4.75718 6.697 4.5915L6.5 4Z">
                                </path>
                            </svg>
                            <span class="btn__text">@lang('Generate')</span>
                            <span class="btn__spinner"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="answerModal" data-bs-backdrop="static" data-bs-keyboard="false" role="dialog" tabindex="-1">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Select The Answer')</h5>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form method="POST">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Question')</label>
                            <p class="question"></p>
                        </div>
                        <div class="form-group">
                            <label>@lang('Correct Answer')</label>
                            <select name="option_id" class="form-control select2"></select>
                        </div>
                        <button class="w-100 btn btn--lg btn--gen generateBtn" type="submit">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.exams.index') }}" />
    <button class="btn btn-sm btn-outline--primary addQuestion" type="button">
        <i class="las la-plus"></i>@lang('Add New')
    </button>
    <button class="btn btn-sm btn-outline--info promptBtn" type="button">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
        @lang('Generate with AI')
    </button>
@endpush


@push('style')
    <style>
        .promptBtn i {
            font-size: 0.8em;

        }

        .btn--gen {
            color: #fff !important;
            display: -webkit-inline-box;
            display: -ms-inline-flexbox;
            display: inline-flex;
            -webkit-box-align: center;
            -ms-flex-align: center;
            align-items: center;
            -webkit-box-pack: center;
            -ms-flex-pack: center;
            justify-content: center;
            gap: 8px;
            position: relative;
            z-index: 1;
            font-size: 1rem;
            padding: 7px 14px;
        }

        .btn--gen::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            -webkit-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            background: #4634ff;
            border-radius: inherit;
            z-index: -2;
            -webkit-transition: 0.2s ease;
            transition: 0.2s ease;
        }

        .btn--gen>svg {
            width: 1.25em;
            height: 1.25em;
        }

        .btn--gen .btn__border {
            --border-width: 4px;
            overflow: hidden;
            position: absolute;
            top: 50%;
            left: 50%;
            -webkit-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
            width: calc(100% + var(--border-width));
            height: calc(100% + var(--border-width));
            background-color: transparent;
            border-radius: inherit;
            z-index: -3;
        }

        .btn--gen .btn__border::before {
            content: "";
            position: absolute;
            top: 30%;
            left: 50%;
            -webkit-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
            -webkit-transform-origin: left;
            transform-origin: left;
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
            width: 100%;
            height: 2rem;
            background: linear-gradient(90deg, #4634ff, #4634ff 50%, #4634ff);
            -webkit-mask: -webkit-gradient(linear, left top, left bottom, from(transparent), color-stop(120%, white));
            -webkit-mask: linear-gradient(transparent 0%, white 120%);
            mask: -webkit-gradient(linear, left top, left bottom, from(transparent), color-stop(120%, white));
            mask: linear-gradient(transparent 0%, white 120%);
            opacity: 0;
            visibility: hidden;
        }

        .btn--gen .btn__spinner {
            --size: 1em;
            --first: hsl(var(--white)/0.2);
            --second: hsl(var(--white));
            width: var(--size);
            height: var(--size);
            position: relative;
            -webkit-animation: spin 3s linear infinite;
            animation: spin 3s linear infinite;
            display: none;
        }

        .btn--gen .btn__spinner::before,
        .btn--gen .btn__spinner::after {
            content: "";
            width: 100%;
            height: 100%;
            display: inline-block;
            border: calc(var(--size) * 0.15) solid var(--first);
            border-top: calc(var(--size) * 0.15) solid var(--second);
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            -webkit-transform: translate(-50%, -50%);
            transform: translate(-50%, -50%);
            -webkit-animation: spinRing 1.5s ease-out infinite;
            animation: spinRing 1.5s ease-out infinite;
        }

        .btn--gen .btn__text {
            color: inherit;
        }

        .btn--gen .sparkle {
            position: relative;
            z-index: 10;
        }

        .btn--gen .sparkle .path {
            fill: currentColor;
            stroke: currentColor;
            -webkit-transform-origin: center;
            transform-origin: center;
            color: #fff;
        }

        .btn--gen .sparkle .path:nth-child(1) {
            --scale_path_1: 1.4;
        }

        .btn--gen .sparkle .path:nth-child(2) {
            --scale_path_2: 1.4;
        }

        .btn--gen .sparkle .path:nth-child(3) {
            --scale_path_3: 1.4;
        }

        .btn--gen:not(.proccessing):hover,
        .btn--gen:not(.proccessing):active,
        .btn--gen:not(.proccessing):focus,
        .btn--gen:not(.proccessing):focus-visible {
            color: #fff;
        }

        .btn--gen:not(.proccessing):hover::before,
        .btn--gen:not(.proccessing):active::before,
        .btn--gen:not(.proccessing):focus::before,
        .btn--gen:not(.proccessing):focus-visible::before {
            background: linear-gradient(90deg, #2b1db6, #4634ff 50%, #4e4cff);
        }

        .btn--gen.proccessing::before {
            -webkit-box-shadow: 0 0 0 0 #4D4D4D inset, 0 4px 4px 0 hsl(var(--black)/0.15) inset;
            box-shadow: 0 0 0 0 #4D4D4D inset, 0 4px 4px 0 hsl(var(--black)/0.15) inset;
        }

        .btn--gen.proccessing .btn__border::before {
            -webkit-animation: rotate 3s ease infinite;
            animation: rotate 3s ease infinite;
            opacity: 1;
            visibility: visible;
        }

        .btn--gen.proccessing .btn__total {
            display: none;
        }

        .btn--gen.proccessing .btn__spinner {
            display: inline-block;
        }

        .btn--gen.proccessing .sparkle .path {
            -webkit-animation: path 1.5s ease 0.5s infinite;
            animation: path 1.5s ease 0.5s infinite;
        }

        .btn--gen:hover,
        .btn--gen:focus {
            border-color: transparent;
        }

        .btn--gen:disabled,
        .btn--gen.disabled {
            opacity: 0.8;
            border-color: transparent;
        }

        @-webkit-keyframes path {

            0%,
            34%,
            71%,
            100% {
                -webkit-transform: scale(1);
                transform: scale(1);
            }

            17% {
                -webkit-transform: scale(var(--scale_path_1, 1));
                transform: scale(var(--scale_path_1, 1));
            }

            49% {
                -webkit-transform: scale(var(--scale_path_2, 1));
                transform: scale(var(--scale_path_2, 1));
            }

            83% {
                -webkit-transform: scale(var(--scale_path_3, 1));
                transform: scale(var(--scale_path_3, 1));
            }
        }

        @keyframes path {

            0%,
            34%,
            71%,
            100% {
                -webkit-transform: scale(1);
                transform: scale(1);
            }

            17% {
                -webkit-transform: scale(var(--scale_path_1, 1));
                transform: scale(var(--scale_path_1, 1));
            }

            49% {
                -webkit-transform: scale(var(--scale_path_2, 1));
                transform: scale(var(--scale_path_2, 1));
            }

            83% {
                -webkit-transform: scale(var(--scale_path_3, 1));
                transform: scale(var(--scale_path_3, 1));
            }
        }

        @-webkit-keyframes rotate {
            to {
                -webkit-transform: rotate(360deg);
                transform: rotate(360deg);
            }
        }

        @keyframes rotate {
            to {
                -webkit-transform: rotate(360deg);
                transform: rotate(360deg);
            }
        }
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            let examId = Number("{{ $exam->id }}");


            $('.promptBtn').on('click', function(e) {
                e.preventDefault();
                let promptModal = $("#promptModal");
                promptModal.modal('show');
            });


            $("#promptForm").on('submit', function(e) {
                e.preventDefault();
                let form = $(this);
                let path = `{{ route('admin.exams.question.generate', '') }}/${examId}`;
                $.ajax({
                    type: "POST",
                    url: path,
                    data: {
                        _token: "{{ csrf_token() }}",
                        prompt: $('[name=prompt]').val(),
                    },
                    beforeSend: function() {
                        $(form).find('.generateBtn').addClass('processing').prop('disabled', true);
                        $(form).find('.btn__text').text('Question generating...')
                    },
                    success: function(response) {
                        console.log(response);
                        $(form).find('.generateBtn').removeClass('processing').prop('disabled', false);
                        $(form).find('.btn__text').text('Generate')
                        if (response.status == 'error') {
                            notify('error', response.message.error);
                        } else {
                            window.location.reload();
                        }
                    }
                });
            });


            $('.resultBtn').on('click', function(e) {
                e.preventDefault();
                let answerModal = $("#answerModal");
                let selectedQuestion = $(this).data('question');
                answerModal.find('form').attr('action', `{{ route('admin.exams.question.result', '') }}/${selectedQuestion.id}`);
                answerModal.find('.question').text(selectedQuestion.title);
                let optionHtml = '<option value="" selected disabled>@lang('Select One')</option>';
                $.each(selectedQuestion.options, function(index, option) {
                    optionHtml += `<option value="${option.id}" ${selectedQuestion.result_option_id == option.id ? 'selected' : ''}>${option.title}</option>`
                });
                answerModal.find('[name=option_id]').html(optionHtml);
                answerModal.modal('show');
            });


            let questionModal = $('#questionModal');
            $('.addQuestion').on('click', function() {
                questionModal.find('.modal-title').text(`@lang('Add New Question')`);
                questionModal.find('form').attr('action', `{{ route('admin.exams.question.store', ['', '']) }}/${examId}`);
                questionModal.modal('show');
            });
            $('.editQuestion').on('click', function() {
                var question = $(this).data('question');
                questionModal.find('.modal-title').text(`@lang('Update Question')`);
                questionModal.find('form').attr('action', `{{ route('admin.exams.question.store', ['', '']) }}/${examId}/${question.id}`);
                questionModal.find('[name=title]').val(question.title);
                questionModal.modal('show')
            });
            questionModal.on('hidden.bs.modal', function() {
                $('#questionModal form')[0].reset();
            });


            let optionListModal = $('#optionListModal');

            $('.option-btn').on('click', function(e) {
                optionListModal.find('tbody').html('')
                var question = $(this).data('question');
                var modalTitle = `Option for - ${question.title}`;
                optionListModal.find('.modal-title').text(modalTitle);
                optionListModal.find('tbody').html('');

                console.log(question);


                var tableRow = optionTable(question.options);
                optionListModal.find('tbody').html(tableRow)

                var actionBtn = `<div class="button--group m-0">
                                        <button class="btn btn-sm btn-outline--primary add-option" data-question_id="${question.id}" data-modal_title="@lang('Add New Option')" type="button">
                                            <i class="las la-plus"></i>@lang('Add New')
                                        </button>
                                </div>`;
                optionListModal.find('.action-area').html(actionBtn);
                optionListModal.modal('show')
            });

            function optionTable(options) {
                var tableRow = ``;
                if (!options.length) {
                    tableRow = `<tr>
                                    <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                </tr>`
                }
                $.each(options, function(index, option) {
                    tableRow += `<tr>
                    <td data-label="@lang('Option')">${option.title}</td>
                    <td data-label="@lang('Status')">
                        ${option.status == 1 ?
                            `<span class="badge badge--success">@lang('Enabled')</span>` :
                            `<span class="badge badge--danger">@lang('Disabled')</span>`
                        }
                    </td>
                    <td data-label="@lang('Action')"> <div class="button--group option-button-group">`;

                    tableRow += `<button class="btn btn-sm btn-outline--primary edit-option" data-resource='${JSON.stringify(option)}'data-modal_title="@lang('Update Option')" type="button">
                                    <i class="las la-pen"></i>@lang('Edit')
                                </button>`;

                    if (option.status == 1) {
                        tableRow += `<button class="btn btn-sm btn-outline--danger option-status-btn"
                         data-action="{{ route('admin.exams.option.status', '') }}/${option.id}"
                         data-question="@lang('Are you sure to disable this option')?">
                         <i class="la la-eye-slash"></i>@lang('Disable')
                     </button>`;
                    } else {
                        tableRow += `<button class="btn btn-sm btn-outline--success option-status-btn"
                         data-action="{{ route('admin.exams.option.status', '') }}/${option.id}"
                         data-question="@lang('Are you sure to enable this option')?">
                         <i class="la la-eye"></i>@lang('Enable')
                     </button>`;
                    }

                    tableRow += `</div></td></tr>`;
                });

                return tableRow;
            }


            let optionStoreModal = $("#optionStoreModal");
            $(document).on('click', '.add-option', function(e) {
                optionStoreModal.find('.modal-title').text('Add New Option');
                optionStoreModal.find('form').attr('action', `{{ route('admin.exams.option.store', '') }}`)
                optionStoreModal.find('[name=question_id]').val($(this).data('question_id'));
                optionStoreModal.modal('show');
                optionListModal.modal('hide');
            });

            $(document).on('click', '.edit-option', function() {
                var data = $(this).data('resource');
                optionStoreModal.find('.modal-title').text('Update Option');
                optionStoreModal.find('form').attr('action', `{{ route('admin.exams.option.store', '') }}/${data.id}`)
                optionStoreModal.find('[name=question_id]').val(data.question_id);
                optionStoreModal.find('[name=title]').val(data.title);
                optionStoreModal.modal('show');
                optionListModal.modal('hide');
            })


            optionStoreModal.on('hidden.bs.modal', function() {
                $('#optionStoreModal form')[0].reset();
            });

            $(document).on('click', '.close-option-modal', function() {
                optionListModal.modal('show')
            });


            $(document).on('submit', '#optionStore', function(event) {
                event.preventDefault();
                let data = {
                    _token: '{{ csrf_token() }}',
                    title: $(this).find('[name=title]').val(),
                    question_id: $(this).find('[name=question_id]').val()
                };
                var action = $(this).attr('action')
                $.ajax({
                    type: 'POST',
                    url: action,
                    data: data,
                    success: function(response) {
                        if (response.error) {
                            notify('error', response.error);
                        } else {
                            optionListModal.find('tbody').html('');
                            var tableRow = optionTable(response.options);
                            optionListModal.find('tbody').html(tableRow)
                            optionStoreModal.modal('hide')
                            optionListModal.modal('show')
                            notify('success', response.success);
                        }
                    }
                });
            });

            let optionStatusModal = $("#optionStatusModal");

            $(document).on('click', '.option-status-btn', function() {
                optionListModal.modal('hide');
                let data = $(this).data();
                optionStatusModal.find('.question').text(`${data.question}`);
                optionStatusModal.find('form').attr('action', `${data.action}`);
                optionStatusModal.modal('show');
            })

            $(document).on('submit', '#optionStatusForm', function(event) {
                event.preventDefault();
                let data = {
                    _token: '{{ csrf_token() }}',
                };
                var action = $(this).attr('action')
                $.ajax({
                    type: 'POST',
                    url: action,
                    data: data,
                    success: function(response) {
                        if (response.error) {
                            notify('error', response.error);
                        } else {
                            optionListModal.find('tbody').html('');
                            var tableRow = optionTable(response.options);
                            optionListModal.find('tbody').html(tableRow)
                            optionStatusModal.modal('hide')
                            optionListModal.modal('show')
                            notify('success', response.success);
                        }
                    }
                });
            })
        })(jQuery)
    </script>
@endpush
