@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="" method="GET">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Search')</label>
                                    <input type="text" name="search" class="form-control" value="{{ request()->search }}" placeholder="@lang('Search question...')">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>@lang('Category')</label>
                                    <select name="category_id" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ __($category->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>@lang('Difficulty')</label>
                                    <select name="difficulty" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        <option value="easy" @selected(request('difficulty') == 'easy')>@lang('Easy')</option>
                                        <option value="medium" @selected(request('difficulty') == 'medium')>@lang('Medium')</option>
                                        <option value="hard" @selected(request('difficulty') == 'hard')>@lang('Hard')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>@lang('Type')</label>
                                    <select name="question_type" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        <option value="mcq_single" @selected(request('question_type') == 'mcq_single')>@lang('Single MCQ')</option>
                                        <option value="mcq_multi" @selected(request('question_type') == 'mcq_multi')>@lang('Multi MCQ')</option>
                                        <option value="true_false" @selected(request('question_type') == 'true_false')>@lang('True/False')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn--primary h-45 flex-grow-1">
                                    <i class="las la-search"></i>
                                </button>
                                <a href="{{ route('admin.question-bank.index') }}" class="btn btn--dark h-45 flex-grow-1">
                                    <i class="las la-undo"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Question')</th>
                                    <th>@lang('Category')</th>
                                    <th>@lang('Difficulty')</th>
                                    <th>@lang('Type')</th>
                                    <th>@lang('Options')</th>
                                    <th>@lang('Marks')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($questions as $question)
                                    <tr>
                                        <td>
                                            <div>
                                                <span class="d-block">{{ strLimit(__($question->question_text), 80) }}</span>
                                                <small class="text-muted">@lang('#ID:') {{ $question->id }}</small>
                                            </div>
                                        </td>
                                        <td>
                                            <span>{{ __($question?->category?->name ?? '-') }}</span>
                                            @if($question->subCategory)
                                                <small class="d-block text-muted">{{ __($question->subCategory->name) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $badges = [
                                                    'easy' => '<span class="badge badge--success">Easy</span>',
                                                    'medium' => '<span class="badge badge--warning">Medium</span>',
                                                    'hard' => '<span class="badge badge--danger">Hard</span>',
                                                ];
                                                echo $badges[$question->difficulty] ?? $badges['medium'];
                                            @endphp
                                        </td>
                                        <td>
                                            <span class="badge badge--dark">{{ ucfirst(str_replace('_', ' ', $question->question_type)) }}</span>
                                        </td>
                                        <td>
                                            <span class="badge badge--info">{{ $question->options_count }}</span>
                                        </td>
                                        <td>
                                            <span>{{ showAmount($question->default_marks) }}</span>
                                        </td>
                                        <td>
                                            @php echo $question->statusBadge; @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <button class="btn btn--sm btn-outline--info editBankQuestionBtn" type="button" data-question="{{ $question }}">
                                                    <i class="la la-pencil"></i> @lang('Edit')
                                                </button>
                                                <button class="btn btn--sm btn-outline--info" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="las la-ellipsis-v"></i>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <button class="dropdown-item previewBankQuestionBtn" data-question="{{ $question }}">
                                                        <i class="la la-eye"></i> @lang('Preview')
                                                    </button>
                                                    @if($question->status == \App\Constants\Status::ENABLE)
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.question-bank.status', $question->id) }}" data-question="@lang('Are you sure to disable this question?')">
                                                            <i class="la la-eye-slash"></i> @lang('Disable')
                                                        </button>
                                                    @else
                                                        <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.question-bank.status', $question->id) }}" data-question="@lang('Are you sure to enable this question?')">
                                                            <i class="la la-eye"></i> @lang('Enable')
                                                        </button>
                                                    @endif
                                                    <button class="dropdown-item confirmationBtn" data-action="{{ route('admin.question-bank.delete', $question->id) }}" data-question="@lang('Are you sure to delete this question?')">
                                                        <i class="la la-trash"></i> @lang('Delete')
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
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

    <div class="modal fade" id="bankQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bankQuestionModalTitle">@lang('Edit Question')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form id="bankQuestionForm" action="{{ route('admin.question-bank.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="question_id" id="bank_question_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Difficulty') <span class="text-danger">*</span></label>
                                    <select name="difficulty" id="bank_difficulty" class="form-control select2" required>
                                        <option value="easy">@lang('Easy')</option>
                                        <option value="medium">@lang('Medium')</option>
                                        <option value="hard">@lang('Hard')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Question Type')</label>
                                    <select name="question_type" id="bank_question_type" class="form-control select2">
                                        <option value="mcq_single">@lang('Single Choice MCQ')</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label>@lang('Default Marks')</label>
                                    <input type="number" step="any" name="default_marks" id="bank_default_marks" class="form-control" value="1" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Category')</label>
                                    <select name="category_id" id="bank_category_id" class="form-control select2">
                                        <option value="">@lang('Select Category')</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ __($category->name) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>@lang('Sub-Category')</label>
                                    <select name="sub_category_id" id="bank_sub_category_id" class="form-control select2">
                                        <option value="">@lang('Select')</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>@lang('Question Text') <span class="text-danger">*</span></label>
                            <textarea name="question_text" id="bank_question_text" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="form-group">
                            <label>@lang('Hint')</label>
                            <input type="text" name="hint" id="bank_hint" class="form-control" maxlength="255">
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">@lang('Options')</h6>
                            <button type="button" class="btn btn--sm btn-outline--primary addBankOptionRow">
                                <i class="las la-plus"></i> @lang('Add Option')
                            </button>
                        </div>
                        <div id="bankOptionsContainer">
                        </div>
                        <hr>
                        <div class="form-group">
                            <label>@lang('Explanation')</label>
                            <textarea name="explanation" id="bank_explanation" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--primary">@lang('Save')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="previewBankQuestionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">@lang('Question Preview')</h5>
                    <button type="button" class="close" data-bs-dismiss="modal">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <div class="modal-body" id="previewBankQuestionBody">
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
    <button class="btn btn--lg btn-outline--primary addNewBankQuestionBtn" type="button">
        <i class="las la-plus"></i>@lang('Add New Question')
    </button>
@endpush

@push('style')
    <style>
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
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";

            let bankOptionCounter = 0;

            function addBankOptionRow(text = '', isCorrect = false) {
                bankOptionCounter++;
                let idx = bankOptionCounter;
                let html = `
                    <div class="option-row ${isCorrect ? 'correct-option' : ''}" data-idx="${idx}">
                        <div class="row align-items-center g-2">
                            <div class="col-auto">
                                <div class="form-check mb-0">
                                    <input class="form-check-input bank-correct-radio" type="radio" name="bank_options_correct" data-idx="${idx}" ${isCorrect ? 'checked' : ''}>
                                    <label class="form-check-label text-muted">@lang('Correct')</label>
                                </div>
                            </div>
                            <div class="col">
                                <input type="hidden" name="options[${idx}][is_correct]" value="${isCorrect ? 1 : 0}">
                                <input type="text" class="form-control" name="options[${idx}][text]" value="${text}" placeholder="@lang('Option text')">
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn--sm btn-outline--danger removeBankOptionRow">
                                    <i class="las la-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#bankOptionsContainer').append(html);
            }

            $('.addBankOptionRow').on('click', function() {
                addBankOptionRow();
            });

            $(document).on('click', '.removeBankOptionRow', function() {
                $(this).closest('.option-row').remove();
            });

            $(document).on('change', '.bank-correct-radio', function() {
                let idx = $(this).data('idx');
                $('.option-row').removeClass('correct-option');
                $('.option-row').each(function() {
                    $(this).find('input[name$="[is_correct]"]').val(0);
                });
                $(this).closest('.option-row').addClass('correct-option');
                $(this).closest('.option-row').find('input[name$="[is_correct]"]').val(1);
            });

            function resetBankQuestionModal() {
                $('#bankQuestionModalTitle').text('@lang('Add New Question')');
                $('#bank_question_id').val('');
                $('#bank_difficulty').val('medium').trigger('change');
                $('#bank_question_type').val('mcq_single').trigger('change');
                $('#bank_default_marks').val(1);
                $('#bank_category_id').val('').trigger('change');
                $('#bank_sub_category_id').val('').trigger('change');
                $('#bank_question_text').val('');
                $('#bank_hint').val('');
                $('#bank_explanation').val('');
                $('#bankOptionsContainer').html('');
                bankOptionCounter = 0;
                addBankOptionRow('', false);
                addBankOptionRow('', false);
                addBankOptionRow('', false);
                addBankOptionRow('', false);
            }

            $('.addNewBankQuestionBtn').on('click', function() {
                resetBankQuestionModal();
                $('#bankQuestionModal').modal('show');
            });

            $(document).on('click', '.editBankQuestionBtn', function() {
                let q = $(this).data('question');
                $('#bankQuestionModalTitle').text('@lang('Edit Question')');
                $('#bank_question_id').val(q.id);
                $('#bank_difficulty').val(q.difficulty).trigger('change');
                $('#bank_question_type').val(q.question_type).trigger('change');
                $('#bank_default_marks').val(q.default_marks);
                $('#bank_category_id').val(q.category_id || '').trigger('change');
                $('#bank_question_text').val(q.question_text);
                $('#bank_hint').val(q.hint || '');
                $('#bank_explanation').val(q.explanation || '');
                $('#bankOptionsContainer').html('');
                bankOptionCounter = 0;
                if (q.options && q.options.length) {
                    $.each(q.options, function(i, opt) {
                        let isCorrect = q.correct_option_id == opt.id;
                        addBankOptionRow(opt.option_text, isCorrect);
                    });
                }

                if (q.category_id) {
                    setTimeout(function() {
                        loadSubCategoriesForBank(q.category_id, q.sub_category_id);
                    }, 100);
                }

                $('#bankQuestionModal').modal('show');
            });

            $('#bank_category_id').on('change', function() {
                loadSubCategoriesForBank($(this).val(), null);
            });

            function loadSubCategoriesForBank(categoryId, selectedId) {
                let select = $('#bank_sub_category_id');
                select.html('<option value="">@lang('Loading...')</option>');
                if (!categoryId) {
                    select.html('<option value="">@lang('Select')</option>');
                    return;
                }
                $.ajax({
                    url: "{{ route('admin.quiz.subcategories') }}",
                    type: 'GET',
                    data: { category_id: categoryId },
                    success: function(response) {
                        if (response.success) {
                            let options = '<option value="">@lang('Select')</option>';
                            $.each(response.data, function(index, sub) {
                                let sel = selectedId == sub.id ? 'selected' : '';
                                options += `<option value="${sub.id}" ${sel}>${sub.name}</option>`;
                            });
                            select.html(options);
                        }
                    },
                    error: function() {
                        select.html('<option value="">@lang('Error loading')</option>');
                    }
                });
            }

            $(document).on('click', '.previewBankQuestionBtn', function() {
                let q = $(this).data('question');
                let html = '<div class="mb-3">';
                html += '<div class="d-flex gap-2 mb-2">';
                html += '<span class="badge badge--' + (q.difficulty == 'easy' ? 'success' : (q.difficulty == 'medium' ? 'warning' : 'danger')) + '">' + q.difficulty.charAt(0).toUpperCase() + q.difficulty.slice(1) + '</span>';
                html += '<span class="badge badge--dark">' + q.question_type.replace(/_/g, ' ') + '</span>';
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
                $('#previewBankQuestionBody').html(html);
                $('#previewBankQuestionModal').modal('show');
            });

        })(jQuery)
    </script>
@endpush
