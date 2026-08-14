@extends('admin.layouts.app')
@section('panel')
    <div class="row gy-4">
        <div class="col-xxl-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between flex-wrap">
                    <h5>@lang('Quiz Information')</h5>
                    <div>@php echo $quiz->quizStatusBadge; @endphp</div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="mb-1">{{ $quiz->title }}</h6>
                        <small class="text-muted">{{ $quiz->slug }}</small>
                    </div>
                    @if($quiz->description)
                        <div class="mb-3">
                            <small class="text-muted">@lang('Description')</small>
                            <p>{{ strLimit($quiz->description, 200) }}</p>
                        </div>
                    @endif
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">@lang('Category')</small>
                            <h6 class="f-size-14px">{{ __($quiz?->category?->name ?? '-') }}</h6>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted">@lang('Sub-Category')</small>
                            <h6 class="f-size-14px">{{ __($quiz?->subCategory?->name ?? '-') }}</h6>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">@lang('Type')</small>
                            <div>@php echo $quiz->typeBadge; @endphp</div>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted">@lang('Difficulty')</small>
                            <div>@php echo $quiz->difficultyBadge; @endphp</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">@lang('Questions')</small>
                            <h6 class="f-size-14px"><span class="badge badge--info">{{ $questions->count() }} / {{ $quiz->total_questions }}</span></h6>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted">@lang('Time Limit')</small>
                            <h6 class="f-size-14px">{{ $quiz->time_limit }} @lang('Minutes')</h6>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">@lang('Passing %')</small>
                            <h6 class="f-size-14px">{{ $quiz->pass_percentage }}%</h6>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted">@lang('Total Marks')</small>
                            <h6 class="f-size-14px">{{ showAmount($totalMarks) }}</h6>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <small class="text-muted">@lang('Marks/Correct')</small>
                            <h6 class="f-size-14px">{{ showAmount($quiz->marks_per_correct) }}</h6>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted">@lang('Negative Marking')</small>
                            <h6 class="f-size-14px">{{ showAmount($quiz->negative_marking) }}</h6>
                        </div>
                    </div>
                    <div class="border-top pt-3 mt-3">
                        <small class="d-block text-muted mb-2">@lang('Display Settings')</small>
                        <div class="d-flex flex-wrap gap-2">
                            @if($quiz->randomize_questions)
                                <span class="badge badge--primary">@lang('Random Questions')</span>
                            @endif
                            @if($quiz->randomize_options)
                                <span class="badge badge--primary">@lang('Random Options')</span>
                            @endif
                            @if($quiz->show_result)
                                <span class="badge badge--success">@lang('Show Result')</span>
                            @endif
                            @if($quiz->show_correct_answers)
                                <span class="badge badge--success">@lang('Show Answers')</span>
                            @endif
                            @if($quiz->show_explanation)
                                <span class="badge badge--success">@lang('Show Explanation')</span>
                            @endif
                        </div>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <a href="{{ route('admin.quiz.create', $quiz->id) }}" class="btn btn--primary">
                            <i class="la la-edit"></i> @lang('Edit Quiz')
                        </a>
                        <a href="{{ route('admin.quiz.preview', $quiz->id) }}" target="_blank" class="btn btn--info">
                            <i class="la la-play-circle"></i> @lang('Preview Quiz')
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-8">
            <div class="card b-radius--10">
                <div class="card-header d-flex justify-content-between flex-wrap gap-2">
                    <h5 class="mb-0">@lang('Questions Manager')</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn--sm btn-outline--primary addQuestionBtn">
                            <i class="las la-plus"></i> @lang('Add New Question')
                        </button>
                        <button type="button" class="btn btn--sm btn-outline--info" data-bs-toggle="modal" data-bs-target="#addFromBankModal">
                            <i class="las la-database"></i> @lang('Add From Bank')
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div id="questions-container">
                        @forelse($questions as $question)
                            <div class="question-item border-bottom p-3" data-question-id="{{ $question->id }}">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="drag-handle cursor-move p-2 text-muted">
                                        <i class="las la-grip-vertical fa-lg"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <span class="badge badge--info me-1">#{{ $question->pivot->question_order }}</span>
                                                <span class="badge badge--{{ $question->difficulty == 'easy' ? 'success' : ($question->difficulty == 'medium' ? 'warning' : 'danger') }} me-1">{{ ucfirst($question->difficulty) }}</span>
                                                <span class="badge badge--dark">{{ ucfirst(str_replace('_', ' ', $question->question_type)) }}</span>
                                            </div>
                                            <div class="d-flex gap-1">
                                                <button type="button" class="btn btn--xs btn-outline--primary previewQuestionBtn" data-question="{{ $question }}">
                                                    <i class="la la-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn--xs btn-outline--info editQuestionBtn" data-question="{{ $question }}">
                                                    <i class="la la-pencil"></i>
                                                </button>
                                                <button type="button" class="btn btn--xs btn-outline--danger removeQuestionBtn" data-question-id="{{ $question->id }}">
                                                    <i class="la la-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <h6 class="mb-2">{{ $question->question_text }}</h6>
                                       <ul class="list-unstyled mb-2 ps-0">
                                           @foreach($question->options as $opt)

                                               <li class="mb-1 d-flex align-items-center gap-2">

                                                   @if($question->correct_option_id == $opt->id)
                                                       <i class="las la-check-circle text--success"></i>
                                                   @else
                                                       <span class="text-muted" style="width: 18px; display: inline-block;">•</span>
                                                   @endif

                                                   <span class="{{ $question->correct_option_id == $opt->id ? 'fw-bold text--success' : '' }}">
                                                       {{ $opt->option_text }}
                                                   </span>

                                               </li>
                                           @endforeach
                                       </ul>
                                        @if($question->explanation)
                                            <small class="text-muted"><strong>@lang('Explanation:')</strong> {{ strLimit($question->explanation, 150) }}</small>
                                        @endif
                                    </div>
                                    <div class="text-center" style="min-width: 80px;">
                                        <label class="text-muted small d-block mb-1">@lang('Marks')</label>
                                        <input type="number" class="form-control form-control-sm question-marks-input" value="{{ $question->pivot->marks ?? $quiz->marks_per_correct }}" min="0" step="any" data-question-id="{{ $question->id }}">
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="p-5 text-center text-muted">
                                <i class="las la-question-circle fa-4x mb-3"></i>
                                <h5>@lang('No questions added yet')</h5>
                                <p class="mb-0">@lang('Click "Add New Question" or "Add From Bank" to add questions to this quiz.')</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="questionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="questionModalTitle">@lang('Add Question')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form action="{{ route('admin.question-bank.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="question_id" id="question_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Difficulty') <span class="text-danger">*</span></label>
                                    <select name="difficulty" id="difficulty" class="form-control select2" required>
                                        <option value="easy">@lang('Easy')</option>
                                        <option value="medium" selected>@lang('Medium')</option>
                                        <option value="hard">@lang('Hard')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Question Type')</label>
                                    <select name="question_type" id="question_type" class="form-control select2">
                                        <option value="mcq_single">@lang('Single Choice MCQ')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Default Marks')</label>
                                    <input type="number" step="any" name="default_marks" id="default_marks" class="form-control" value="{{ $quiz->marks_per_correct }}" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>@lang('Category')</label>
                            <select name="category_id" class="form-control select2">
                                <option value="">@lang('Select')</option>
                                @if($quiz->category_id)
                                    <option value="{{ $quiz->category_id }}" selected>{{ $quiz->category->name }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('Sub-Category')</label>
                            <select name="sub_category_id" class="form-control select2">
                                <option value="">@lang('Select')</option>
                                @if($quiz->sub_category_id)
                                    <option value="{{ $quiz->sub_category_id }}" selected>{{ $quiz->subCategory->name }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('Question Text') <span class="text-danger">*</span></label>
                            <textarea name="question_text" id="question_text" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>@lang('Hint')</label>
                            <input type="text" name="hint" id="hint" class="form-control" maxlength="255">
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">@lang('Options')</h6>
                            <button type="button" class="btn btn--sm btn-outline--primary addOptionRow">
                                <i class="las la-plus"></i> @lang('Add Option')
                            </button>
                        </div>
                        <div id="optionsContainer">
                        </div>
                        <hr>
                        <div class="form-group">
                            <label>@lang('Explanation')</label>
                            <textarea name="explanation" id="explanation" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--primary">@lang('Save Question')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="previewQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Question Preview')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="previewQuestionBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addFromBankModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Add Questions From Bank')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>@lang('Search')</label>
                                <input type="text" id="bankSearch" class="form-control" placeholder="@lang('Search questions...')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>@lang('Difficulty')</label>
                                <select id="bankDifficulty" class="form-control select2">
                                    <option value="">@lang('All')</option>
                                    <option value="easy">@lang('Easy')</option>
                                    <option value="medium">@lang('Medium')</option>
                                    <option value="hard">@lang('Hard')</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" class="btn btn--primary w-100" id="searchBankBtn">
                                <i class="las la-search"></i> @lang('Search')
                            </button>
                        </div>
                    </div>
                    <div id="bankQuestionsContainer">
                        <div class="text-center py-5 text-muted">
                            @lang('Click search to load available questions from the bank.')
                        </div>
                    </div>
                    <div id="bankPagination" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                </div>
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-back route="{{ route('admin.quiz.index') }}" />
@endpush

@push('style')
    <style>
        .question-item {
            transition: background-color 0.2s;
        }
        .question-item:hover {
            background-color: #f8f9fa;
        }
        .question-item.dragging {
            opacity: 0.5;
            background-color: #e3f2fd;
        }
        .question-item.drag-over {
            border-top: 2px solid #4634ff !important;
        }
        .drag-handle {
            cursor: move;
        }
        .btn--xs {
            padding: 0.15rem 0.4rem;
            font-size: 0.75rem;
        }
        .option-row {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
        }
        .option-row.correct-option {
            border-color: #198754;
            background-color: #d1e7dd;
        }
        .bank-question-item.selected {
            background-color: #e3f2fd;
            border-color: #4634ff;
        }
    </style>
@endpush

@push('script')
    <script src="{{ asset('assets/admin/js/jquery-ui.min.js') }}"></script>
    <script>
        (function($) {
            "use strict";

            let quizId = {{ $quiz->id }};
            let optionCounter = 0;

            function addOptionRow(text = '', isCorrect = false) {
                optionCounter++;
                let idx = optionCounter;
                let html = `
                    <div class="option-row ${isCorrect ? 'correct-option' : ''}" data-idx="${idx}">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <div class="form-check mb-0">
                                    <input class="form-check-input correct-option-radio" type="radio" name="options_correct" data-idx="${idx}" ${isCorrect ? 'checked' : ''}>
                                    <label class="form-check-label text-muted">@lang('Correct')</label>
                                </div>
                            </div>
                            <div class="col">
                                <input type="hidden" name="options[${idx}][is_correct]" value="${isCorrect ? 1 : 0}">
                                <input type="text" class="form-control" name="options[${idx}][text]" value="${text}" placeholder="@lang('Option text')">
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn--sm btn-outline--danger removeOptionRow">
                                    <i class="las la-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#optionsContainer').append(html);
            }

            $('.addOptionRow').on('click', function() {
                addOptionRow();
            });

            $(document).on('click', '.removeOptionRow', function() {
                $(this).closest('.option-row').remove();
            });

            $(document).on('change', '.correct-option-radio', function() {
                let idx = $(this).data('idx');
                $('.option-row').removeClass('correct-option');
                $('.option-row').each(function() {
                    $(this).find('input[name$="[is_correct]"]').val(0);
                });
                $(this).closest('.option-row').addClass('correct-option');
                $(this).closest('.option-row').find('input[name$="[is_correct]"]').val(1);
            });

            $('.addQuestionBtn').on('click', function() {
                $('#questionModalTitle').text('@lang('Add New Question')');
                $('#question_id').val('');
                $('#difficulty').val('medium').trigger('change');
                $('#question_type').val('mcq_single').trigger('change');
                $('#default_marks').val({{ $quiz->marks_per_correct }});
                $('#question_text').val('');
                $('#hint').val('');
                $('#explanation').val('');
                $('#optionsContainer').html('');
                optionCounter = 0;
                addOptionRow('', false);
                addOptionRow('', false);
                addOptionRow('', false);
                addOptionRow('', false);
                $('#questionModal').modal('show');
            });

            $(document).on('click', '.editQuestionBtn', function() {
                let q = $(this).data('question');
                $('#questionModalTitle').text('@lang('Edit Question')');
                $('#question_id').val(q.id);
                $('#difficulty').val(q.difficulty).trigger('change');
                $('#question_type').val(q.question_type).trigger('change');
                $('#default_marks').val(q.default_marks);
                $('#question_text').val(q.question_text);
                $('#hint').val(q.hint || '');
                $('#explanation').val(q.explanation || '');
                $('#optionsContainer').html('');
                optionCounter = 0;
                if (q.options && q.options.length) {
                    $.each(q.options, function(i, opt) {
                        let isCorrect = q.correct_option_id == opt.id;
                        addOptionRow(opt.option_text, isCorrect);
                    });
                }
                $('#questionModal').modal('show');
            });

            let questionFormSubmitted = false;
            $('#questionModal form').on('submit', function(e) {
                if (questionFormSubmitted) return;
                e.preventDefault();
                let form = this;
                questionFormSubmitted = true;

                $.ajax({
                    type: 'POST',
                    url: $(form).attr('action'),
                    data: $(form).serialize(),
                    success: function(response) {
                        questionFormSubmitted = false;
                        if (response.success) {
                            let questionId = $('#question_id').val();
                            if (!questionId) {
                                let createdId = response.data ? response.data.id : null;
                                if (createdId) {
                                    $.ajax({
                                        type: 'POST',
                                        url: "{{ route('admin.question-bank.quiz.add', '') }}/" + quizId,
                                        data: {
                                            _token: "{{ csrf_token() }}",
                                            bank_question_id: createdId,
                                            marks: $('#default_marks').val()
                                        },
                                        success: function() {
                                            location.reload();
                                        },
                                        error: function() {
                                            location.reload();
                                        }
                                    });
                                    return;
                                }
                            }
                            location.reload();
                        } else {
                            notify('error', 'Error saving question');
                        }
                    },
                    error: function(xhr) {
                        questionFormSubmitted = false;
                        let errors = xhr.responseJSON && xhr.responseJSON.errors ? xhr.responseJSON.errors : {};
                        let msgs = Object.values(errors).flat();
                        if (msgs.length) notify('error', msgs[0]);
                        else notify('error', 'Something went wrong');
                    }
                });
            });

            $(document).on('click', '.previewQuestionBtn', function() {
                let q = $(this).data('question');
                let html = '<div class="mb-3">';
                html += '<div class="d-flex gap-2 mb-2">';
                html += '<span class="badge badge--' + (q.difficulty == 'easy' ? 'success' : (q.difficulty == 'medium' ? 'warning' : 'danger')) + '">' + q.difficulty.charAt(0).toUpperCase() + q.difficulty.slice(1) + '</span>';
                html += '</div>';
                html += '<h6>' + q.question_text + '</h6>';
                html += '</div><ul class="list-unstyled">';
                if (q.options) {
                    $.each(q.options, function(i, opt) {
                        let isCorrect = q.correct_option_id == opt.id;
                        html += '<li class="mb-2 d-flex align-items-center gap-2 p-2 rounded ' + (isCorrect ? 'bg-success text-white' : '') + '">';
                        html += isCorrect ? '<i class="las la-check-circle"></i>' : '<span style="width:18px;">•</span>';
                        html += '<span>' + opt.option_text + '</span></li>';
                    });
                }
                html += '</ul>';
                if (q.explanation) {
                    html += '<div class="mt-3 p-3 bg-light rounded"><small class="d-block text-muted mb-1"><strong>@lang("Explanation")</strong></small>' + q.explanation + '</div>';
                }
                $('#previewQuestionBody').html(html);
                $('#previewQuestionModal').modal('show');
            });

            $(document).on('click', '.removeQuestionBtn', function() {
                let questionId = $(this).data('question-id');
                if (!confirm('@lang('Are you sure to remove this question from the quiz?')')) return;
                $.ajax({
                    type: 'POST',
                    url: "{{ route('admin.question-bank.quiz.remove', ['', '']) }}/" + quizId + "/" + questionId,
                    data: { _token: "{{ csrf_token() }}" },
                    success: function() {
                        location.reload();
                    },
                    error: function() {
                        notify('error', 'Failed to remove question');
                    }
                });
            });

            $(document).on('change', '.question-marks-input', function() {
                let questionId = $(this).data('question-id');
                let marks = $(this).val();
                $.ajax({
                    type: 'POST',
                    url: "{{ route('admin.question-bank.quiz.marks', ['', '']) }}/" + quizId + "/" + questionId,
                    data: {
                        _token: "{{ csrf_token() }}",
                        marks: marks
                    },
                    success: function(response) {
                        if (response.success) {
                            notify('success', response.message);
                        }
                    }
                });
            });

            function setupDragAndDrop() {
                let container = document.getElementById('questions-container');
                if (!container) return;
                let draggedItem = null;

                container.querySelectorAll('.question-item').forEach(item => {
                    item.addEventListener('dragstart', function(e) {
                        draggedItem = item;
                        item.classList.add('dragging');
                        e.dataTransfer.effectAllowed = 'move';
                        item.style.opacity = '0.5';
                    });

                    item.addEventListener('dragend', function() {
                        item.classList.remove('dragging');
                        item.style.opacity = '';
                        container.querySelectorAll('.question-item').forEach(i => i.classList.remove('drag-over'));
                    });

                    item.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        if (draggedItem && draggedItem !== item) {
                            item.classList.add('drag-over');
                        }
                    });

                    item.addEventListener('dragleave', function() {
                        item.classList.remove('drag-over');
                    });

                    item.addEventListener('drop', function(e) {
                        e.preventDefault();
                        if (draggedItem && draggedItem !== item) {
                            container.querySelectorAll('.question-item').forEach(i => i.classList.remove('drag-over'));
                            const rect = item.getBoundingClientRect();
                            const after = (e.clientY - rect.top) > (rect.height / 2);
                            if (after) {
                                item.parentNode.insertBefore(draggedItem, item.nextSibling);
                            } else {
                                item.parentNode.insertBefore(draggedItem, item);
                            }
                            saveNewOrder();
                        }
                    });

                    let handle = item.querySelector('.drag-handle');
                    if (handle) {
                        item.setAttribute('draggable', 'false');
                        handle.addEventListener('mouseover', () => item.setAttribute('draggable', 'true'));
                        handle.addEventListener('mouseout', () => item.setAttribute('draggable', 'false'));
                    }
                });
            }

            function saveNewOrder() {
                let orders = [];
                $('.question-item').each(function() {
                    orders.push($(this).data('question-id'));
                });
                $.ajax({
                    type: 'POST',
                    url: "{{ route('admin.question-bank.quiz.reorder', '') }}/" + quizId,
                    data: {
                        _token: "{{ csrf_token() }}",
                        orders: orders
                    },
                    success: function(response) {
                        if (response.success) {
                            $('.question-item').each(function(idx) {
                                $(this).find('.badge--info').first().text('#' + (idx + 1));
                            });
                            notify('success', response.message);
                        }
                    }
                });
            }

            setupDragAndDrop();

            $('#searchBankBtn').on('click', function() {
                searchBankQuestions(1);
            });

            function searchBankQuestions(page = 1) {
                let container = $('#bankQuestionsContainer');
                container.html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');

                let params = new URLSearchParams();
                if ($('#bankSearch').val()) params.append('search', $('#bankSearch').val());
                if ($('#bankDifficulty').val()) params.append('difficulty', $('#bankDifficulty').val());
                if (page > 1) params.append('page', page);

                $.ajax({
                    type: 'GET',
                    url: "{{ route('admin.question-bank.quiz.available', '') }}/" + quizId,
                    data: params.toString(),
                    success: function(response) {
                        if (response.success) {
                            renderBankQuestions(response.data.questions);
                            let p = response.data.pagination;
                            let paginationHtml = '';
                            if (p.last_page > 1) {
                                paginationHtml = '<nav><ul class="pagination justify-content-center mb-0">';
                                for (let i = 1; i <= p.last_page; i++) {
                                    paginationHtml += `<li class="page-item ${p.current_page == i ? 'active' : ''}">
                                        <button class="page-link bank-page-btn" data-page="${i}">${i}</button>
                                    </li>`;
                                }
                                paginationHtml += '</ul></nav>';
                            }
                            $('#bankPagination').html(paginationHtml);
                        }
                    },
                    error: function() {
                        container.html('<div class="text-center py-5 text-muted">@lang('Error loading questions')</div>');
                    }
                });
            }

            function renderBankQuestions(questions) {
                let container = $('#bankQuestionsContainer');
                if (!questions || questions.length === 0) {
                    container.html('<div class="text-center py-5 text-muted">@lang('No available questions found.')</div>');
                    return;
                }
                let html = '';
                $.each(questions, function(i, q) {
                    html += `<div class="bank-question-item border rounded p-3 mb-2">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="d-flex gap-1 mb-2">
                                    <span class="badge badge--${q.difficulty == 'easy' ? 'success' : (q.difficulty == 'medium' ? 'warning' : 'danger')}">${q.difficulty}</span>
                                    <span class="badge badge--dark">${q.question_type.replace(/_/g, ' ')}</span>
                                </div>
                                <h6 class="mb-1">${q.question_text}</h6>
                                <small class="text-muted">${q.category ? q.category.name : ''} ${q.sub_category ? ' / ' + q.sub_category.name : ''}</small>
                            </div>
                            <button type="button" class="btn btn--sm btn-outline--primary addBankQuestionBtn" data-question-id="${q.id}" data-marks="${q.default_marks}">
                                <i class="las la-plus"></i> @lang('Add')
                            </button>
                        </div>
                    </div>`;
                });
                container.html(html);
            }

            $(document).on('click', '.bank-page-btn', function() {
                searchBankQuestions($(this).data('page'));
            });

            $(document).on('click', '.addBankQuestionBtn', function() {
                let btn = $(this);
                let questionId = btn.data('question-id');
                let marks = btn.data('marks');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    type: 'POST',
                    url: "{{ route('admin.question-bank.quiz.add', '') }}/" + quizId,
                    data: {
                        _token: "{{ csrf_token() }}",
                        bank_question_id: questionId,
                        marks: marks
                    },
                    success: function() {
                        notify('success', '@lang('Question added to quiz')');
                        btn.closest('.bank-question-item').fadeOut();
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    },
                    error: function() {
                        notify('error', '@lang('Failed to add question')');
                        btn.prop('disabled', false).html('<i class="las la-plus"></i> @lang('Add')');
                    }
                });
            });
        })(jQuery)
    </script>
@endpush
