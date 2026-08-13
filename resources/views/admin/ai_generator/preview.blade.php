@extends('admin.layouts.app')
@section('panel')
    @php
        $statuses = [
            null                                                        => 'All',
            \App\Models\AiGeneratedQuestion::STATUS_PENDING_REVIEW      => 'Pending Review',
            \App\Models\AiGeneratedQuestion::STATUS_APPROVED            => 'Approved',
            \App\Models\AiGeneratedQuestion::STATUS_PUBLISHED           => 'Published',
            \App\Models\AiGeneratedQuestion::STATUS_DUPLICATE           => 'Duplicate',
            \App\Models\AiGeneratedQuestion::STATUS_REJECTED            => 'Rejected',
        ];
    @endphp

    {{-- ------------------------------------------------ generation summary --}}
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0">
                        @lang('Generation') #{{ $generation->id }}
                        @if ($generation->topic)
                            <small class="text-muted">{{ $generation->topic }}</small>
                        @endif
                    </h5>
                    @php echo $generation->statusBadge; @endphp
                </div>
                <div class="card-body">
                    @if ($generation->error_message)
                        <div class="alert alert-danger">
                            <strong>@lang('Error'):</strong> {{ $generation->error_message }}
                        </div>
                    @endif

                    <div class="row g-3">
                        @foreach ([
                            'Category'     => $generation->category?->name ?? '-',
                            'Sub-category' => $generation->subCategory?->name ?? '-',
                            'Difficulty'   => ucfirst($generation->difficulty),
                            'Type'         => $generation->question_type,
                            'Language'     => ucfirst($generation->language),
                            'Requested'    => $generation->requested_count,
                            'Provider'     => $generation->provider,
                            'Model'        => $generation->model,
                            'Created By'   => $generation->creator?->name ?? '-',
                            'Generated'    => showDateTime($generation->created_at),
                        ] as $label => $value)
                            <div class="col-6 col-md-3 col-lg-2">
                                <small class="text-muted d-block">@lang($label)</small>
                                <strong>{{ $value }}</strong>
                            </div>
                        @endforeach
                    </div>

                    @if ($generation->total_tokens)
                        <hr>
                        <div class="row g-3">
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block">@lang('Input Tokens')</small>
                                <strong>{{ number_format($generation->input_tokens) }}</strong>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block">@lang('Output Tokens')</small>
                                <strong>{{ number_format($generation->output_tokens) }}</strong>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block">@lang('Total Tokens')</small>
                                <strong>{{ number_format($generation->total_tokens) }}</strong>
                            </div>
                            <div class="col-6 col-md-3">
                                <small class="text-muted d-block">@lang('Estimated Cost')</small>
                                <strong>{{ $generation->estimated_cost !== null ? '$' . number_format($generation->estimated_cost, 4) : __('n/a') }}</strong>
                            </div>
                        </div>
                    @endif

                    {{-- counters --}}
                    <hr>
                    <div class="row g-3 text-center">
                        @foreach ([
                            ['Generated', $generation->generated_count, 'text--dark'],
                            ['Approved',  $generation->approved_count,  'text--primary'],
                            ['Published', $generation->published_count, 'text--success'],
                            ['Rejected',  $generation->rejected_count,  'text--danger'],
                            ['Duplicate', $generation->duplicate_count, 'text--warning'],
                        ] as [$label, $count, $class])
                            <div class="col">
                                <div class="border rounded py-3">
                                    <h4 class="mb-0 {{ $class }}">{{ $count }}</h4>
                                    <small class="text-muted">@lang($label)</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card-footer d-flex flex-wrap gap-2">
                    @if ($generation->published_count > 0)
                        <button type="button" class="btn btn--success" data-bs-toggle="modal" data-bs-target="#addToQuizModal">
                            <i class="las la-plus-circle"></i> @lang('Add Published Questions to Quiz')
                        </button>
                    @endif
                    <a href="{{ route('admin.ai-generator.raw', $generation->id) }}" target="_blank" class="btn btn-outline--info">
                        <i class="las la-code"></i> @lang('View Raw AI Response')
                    </a>
                    <a href="{{ route('admin.ai-generator.create') }}" class="btn btn-outline--primary ms-auto">
                        <i class="las la-magic"></i> @lang('New Generation')
                    </a>
                    <a href="{{ route('admin.ai-generator.history') }}" class="btn btn-outline--dark">
                        <i class="las la-history"></i> @lang('History')
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------ bulk toolbar --}}
    <form action="{{ route('admin.ai-generator.bulk', $generation->id) }}" method="POST" id="bulkForm">
        @csrf
        <input type="hidden" name="action" id="bulkActionInput">

        <div class="row mt-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
                        <div class="form-check me-3">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="selectAll">@lang('Select All')</label>
                        </div>
                        <span class="text-muted me-auto"><span id="selectedCount">0</span> @lang('selected')</span>

                        <button type="button" class="btn btn--sm btn--success bulkBtn" data-action="approve"
                                data-confirm="@lang('Import the selected questions into the Question Bank?')">
                            <i class="las la-check"></i> @lang('Approve Selected')
                        </button>
                        <button type="button" class="btn btn--sm btn--danger bulkBtn" data-action="reject"
                                data-confirm="@lang('Reject the selected questions?')">
                            <i class="las la-times"></i> @lang('Reject Selected')
                        </button>
                        <button type="button" class="btn btn--sm btn--warning bulkBtn" data-action="regenerate"
                                data-confirm="@lang('Regenerate the selected questions? This calls the AI provider again.')">
                            <i class="las la-sync"></i> @lang('Regenerate Selected')
                        </button>
                        <button type="button" class="btn btn--sm btn--dark bulkBtn" data-action="delete"
                                data-confirm="@lang('Delete the selected generated questions? Questions already in the Question Bank are kept.')">
                            <i class="las la-trash"></i> @lang('Delete Selected')
                        </button>
                    </div>

                    <div class="card-footer">
                        <ul class="nav nav-pills gap-2">
                            @foreach ($statuses as $value => $label)
                                <li class="nav-item">
                                    <a class="nav-link @if ($filter == $value) active @endif"
                                       href="{{ route('admin.ai-generator.preview', $generation->id) }}{{ $value ? '?status=' . $value : '' }}">
                                        @lang($label)
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- ------------------------------------------------ question cards --}}
        <div class="row mt-4">
            @forelse ($questions as $question)
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 @if ($question->duplicate_flag && !$question->duplicate_overridden) border-warning @endif">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div class="form-check">
                                <input class="form-check-input questionCheck" type="checkbox"
                                       name="question_ids[]" value="{{ $question->id }}"
                                       id="q{{ $question->id }}" @disabled($question->isPublished())>
                                <label class="form-check-label fw-bold" for="q{{ $question->id }}">
                                    Q{{ $question->sort_order }}
                                </label>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="badge badge--secondary">{{ ucfirst($question->difficulty) }}</span>
                                @php echo $question->statusBadge; @endphp
                            </div>
                        </div>

                        <div class="card-body">
                            @if ($question->duplicate_flag)
                                <div class="alert alert-warning py-2">
                                    <strong><i class="las la-copy"></i> @lang('Possible duplicate question')</strong>
                                    <span class="badge badge--warning">{{ $question->similarity_score }}% @lang('similar')</span>
                                    @if ($question->duplicateOf)
                                        <div class="small mt-1">
                                            @lang('Matches Question Bank') #{{ $question->duplicate_question_id }}:
                                            "{{ \Illuminate\Support\Str::limit($question->duplicateOf->question_text, 70) }}"
                                        </div>
                                    @elseif ($question->duplicate_generated_id)
                                        <div class="small mt-1">@lang('Matches a previously generated AI question') #{{ $question->duplicate_generated_id }}</div>
                                    @endif

                                    @if ($question->duplicate_overridden)
                                        <div class="small mt-1 text--success"><i class="las la-check"></i> @lang('Kept anyway by an admin - eligible for import.')</div>
                                    @endif
                                </div>
                            @endif

                            @if ($question->validation_errors)
                                <div class="alert alert-danger py-2 small">
                                    <strong>@lang('Invalid'):</strong> {{ $question->validation_errors }}
                                </div>
                            @endif

                            <p class="fw-bold">{{ $question->question }}</p>

                            <ul class="list-unstyled mb-3">
                                @foreach ($question->optionList() as $letter => $text)
                                    <li class="mb-1 @if ($letter === $question->correct_answer) text--success fw-bold @endif">
                                        <span class="badge {{ $letter === $question->correct_answer ? 'badge--success' : 'badge--secondary' }}">{{ $letter }}</span>
                                        {{ $text }}
                                        @if ($letter === $question->correct_answer)
                                            <i class="las la-check-circle"></i>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            @if ($question->explanation)
                                <div class="bg-light rounded p-2 small">
                                    <strong>@lang('Explanation'):</strong> {{ $question->explanation }}
                                </div>
                            @endif

                            @if ($question->isPublished())
                                <div class="small text-muted mt-2">
                                    <i class="las la-database"></i>
                                    @lang('In Question Bank as') #{{ $question->question_id }}
                                    @if ($question->reviewer) — @lang('by') {{ $question->reviewer->name }} @endif
                                    @if ($question->reviewed_at) {{ showDateTime($question->reviewed_at) }} @endif
                                </div>
                            @endif
                        </div>

                        <div class="card-footer d-flex flex-wrap gap-1">
                            @if (!$question->isPublished())
                                <button type="button" class="btn btn--sm btn-outline--primary editBtn"
                                        data-question='@json($question->editPayload())'>
                                    <i class="la la-pencil"></i> @lang('Edit')
                                </button>
                                <button type="button" class="btn btn--sm btn-outline--warning singleActionBtn"
                                        data-url="{{ route('admin.ai-generator.regenerate', [$generation->id, $question->id]) }}"
                                        data-confirm="@lang('Regenerate this question?')">
                                    <i class="la la-sync"></i> @lang('Regenerate')
                                </button>
                                <button type="button" class="btn btn--sm btn-outline--success singleBulkBtn"
                                        data-action="approve" data-id="{{ $question->id }}"
                                        data-confirm="@lang('Import this question into the Question Bank?')">
                                    <i class="la la-check"></i> @lang('Approve')
                                </button>
                                <button type="button" class="btn btn--sm btn-outline--danger singleBulkBtn"
                                        data-action="reject" data-id="{{ $question->id }}"
                                        data-confirm="@lang('Reject this question?')">
                                    <i class="la la-times"></i> @lang('Reject')
                                </button>
                                @if ($question->duplicate_flag && !$question->duplicate_overridden)
                                    <button type="button" class="btn btn--sm btn-outline--dark singleBulkBtn"
                                            data-action="keep_anyway" data-id="{{ $question->id }}"
                                            data-confirm="@lang('Keep this question despite the duplicate warning?')">
                                        <i class="la la-shield-alt"></i> @lang('Keep Anyway')
                                    </button>
                                @endif
                                @if ($question->duplicateOf)
                                    <a class="btn btn--sm btn-outline--info" target="_blank"
                                       href="{{ route('admin.question-bank.index') }}?search={{ urlencode(\Illuminate\Support\Str::limit($question->duplicateOf->question_text, 40, '')) }}">
                                        <i class="la la-external-link-alt"></i> @lang('View Existing')
                                    </a>
                                @endif
                            @endif
                            <button type="button" class="btn btn--sm btn-outline--dark singleActionBtn"
                                    data-url="{{ route('admin.ai-generator.question.delete', [$generation->id, $question->id]) }}"
                                    data-confirm="@lang('Delete this generated question?')">
                                <i class="la la-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center text-muted py-5">
                            @lang('No generated questions with this status.')
                        </div>
                    </div>
                </div>
            @endforelse
        </div>
    </form>

    {{-- helper form for single-question POST actions --}}
    <form method="POST" id="singleActionForm" class="d-none">@csrf</form>

    {{-- ------------------------------------------------ edit modal --}}
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="editForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Edit Generated Question')</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">@lang('Question')</label>
                                <textarea name="question" rows="3" class="form-control" required></textarea>
                            </div>
                            @foreach (['A', 'B', 'C', 'D'] as $letter)
                                <div class="col-md-6" data-option-wrap="{{ $letter }}">
                                    <label class="form-label">@lang('Option') {{ $letter }}</label>
                                    <input type="text" name="options[{{ $letter }}]" class="form-control">
                                </div>
                            @endforeach
                            <div class="col-md-6">
                                <label class="form-label">@lang('Correct Answer')</label>
                                <select name="correct_answer" class="form-control" id="editCorrect" required>
                                    @foreach (['A', 'B', 'C', 'D'] as $letter)
                                        <option value="{{ $letter }}">{{ $letter }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Difficulty')</label>
                                <select name="difficulty" class="form-control" required>
                                    @foreach (\App\Models\AiGenerationSetting::DIFFICULTIES as $value => $label)
                                        <option value="{{ $value }}">@lang($label)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">@lang('Explanation')</label>
                                <textarea name="explanation" rows="3" class="form-control" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
                        <button type="submit" class="btn btn--primary">@lang('Save & Re-validate')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------ add to quiz modal --}}
    <div class="modal fade" id="addToQuizModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.ai-generator.add.to.quiz', $generation->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Add Published Questions to Quiz')</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>
                            @lang('This adds the') <strong>{{ $generation->published_count }}</strong>
                            @lang('published question(s) from this generation to the selected quiz, appended after any existing questions.')
                        </p>
                        <label class="form-label">@lang('Quiz')</label>
                        <select name="quiz_id" class="form-control select2" required>
                            @foreach ($quizzes as $quiz)
                                <option value="{{ $quiz->id }}" @selected($generation->quiz_id == $quiz->id)>{{ $quiz->title }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted d-block mt-2">
                            @lang('Questions already in the quiz are skipped rather than duplicated.')
                        </small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                        <button type="submit" class="btn btn--success">@lang('Add to Quiz')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
    <script>
        (function () {
            "use strict";

            const bulkForm   = document.getElementById('bulkForm');
            const actionInput = document.getElementById('bulkActionInput');
            const checks     = () => Array.from(document.querySelectorAll('.questionCheck'));
            const countLabel = document.getElementById('selectedCount');

            const refreshCount = () => {
                countLabel.textContent = checks().filter(c => c.checked).length;
            };

            document.getElementById('selectAll').addEventListener('change', function () {
                checks().forEach(c => { if (!c.disabled) c.checked = this.checked; });
                refreshCount();
            });

            checks().forEach(c => c.addEventListener('change', refreshCount));

            // Bulk buttons submit the surrounding form with the chosen action.
            document.querySelectorAll('.bulkBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (checks().filter(c => c.checked).length === 0) {
                        alert("@lang('Select at least one question first.')");
                        return;
                    }
                    if (!confirm(this.dataset.confirm)) return;
                    actionInput.value = this.dataset.action;
                    bulkForm.submit();
                });
            });

            // Per-card action that reuses the bulk endpoint for a single id.
            document.querySelectorAll('.singleBulkBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (!confirm(this.dataset.confirm)) return;
                    checks().forEach(c => c.checked = false);
                    const target = document.getElementById('q' + this.dataset.id);
                    if (target) target.checked = true;
                    actionInput.value = this.dataset.action;
                    bulkForm.submit();
                });
            });

            // Per-card action posting to its own dedicated route.
            const singleForm = document.getElementById('singleActionForm');
            document.querySelectorAll('.singleActionBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (!confirm(this.dataset.confirm)) return;
                    singleForm.action = this.dataset.url;
                    singleForm.submit();
                });
            });

            // Edit modal.
            const editModalEl = document.getElementById('editModal');
            const editModal = new bootstrap.Modal(editModalEl);
            const editForm = document.getElementById('editForm');
            const baseUrl = "{{ url('admin/ai-generator/question') }}/{{ $generation->id }}/";

            document.querySelectorAll('.editBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const q = JSON.parse(this.dataset.question);
                    editForm.action = baseUrl + q.id;

                    editForm.querySelector('[name="question"]').value = q.question ?? '';
                    editForm.querySelector('[name="explanation"]').value = q.explanation ?? '';
                    editForm.querySelector('[name="difficulty"]').value = q.difficulty ?? 'medium';

                    // True/False questions only use A and B - hide C and D.
                    const options = q.options || {};
                    ['A', 'B', 'C', 'D'].forEach(letter => {
                        const field = editForm.querySelector('[name="options[' + letter + ']"]');
                        const wrap = editForm.querySelector('[data-option-wrap="' + letter + '"]');
                        const opt = document.querySelector('#editCorrect option[value="' + letter + '"]');
                        const present = Object.prototype.hasOwnProperty.call(options, letter);

                        field.value = options[letter] ?? '';
                        wrap.classList.toggle('d-none', q.question_type === 'true_false' && !present);
                        if (opt) opt.hidden = q.question_type === 'true_false' && !present;
                    });

                    editForm.querySelector('[name="correct_answer"]').value = q.correct_answer ?? 'A';
                    editModal.show();
                });
            });

            refreshCount();
        })();
    </script>
@endpush
