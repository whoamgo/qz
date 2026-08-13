@extends('admin.layouts.app')
@section('panel')
    @php
        $isProcessing = $import->isProcessing();
        $reviewable   = $import->isReviewable();
    @endphp

    <div class="row" id="importRoot" data-import-id="{{ $import->id }}" data-processing="{{ $isProcessing ? 1 : 0 }}">
        {{-- ------------------------------------------------ progress --}}
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0">
                        {{ $import->file_name }}
                        <small class="text-muted">@lang('Import') #{{ $import->id }}</small>
                    </h5>
                    <span id="importStatusBadge">@php echo $import->statusBadge; @endphp</span>
                </div>
                <div class="card-body">
                    <div id="progressWrap" class="@if (!$isProcessing) d-none @endif">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>@lang('Uploading')</span>
                                <span id="pctUpload">100%</span>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar bg--success" id="barUpload" style="width:100%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>@lang('Processing')</span>
                                <span id="pctProcess">0%</span>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar bg--primary" id="barProcess" style="width:0%"></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span>@lang('Validating')</span>
                                <span id="pctValidate">0%</span>
                            </div>
                            <div class="progress" style="height:10px;">
                                <div class="progress-bar bg--warning" id="barValidate" style="width:0%"></div>
                            </div>
                        </div>
                        <p class="text-muted mb-0">
                            <i class="las la-info-circle"></i>
                            @lang('Keep this page open while the file is processed. Nothing is written to the question bank during this step.')
                        </p>
                    </div>

                    @if ($import->error_message)
                        <div class="alert alert-danger mb-3" id="importError">
                            <strong>@lang('Error'):</strong> {{ $import->error_message }}
                        </div>
                    @else
                        <div class="alert alert-danger mb-3 d-none" id="importError"></div>
                    @endif

                    {{-- ------------------------------------------------ counters --}}
                    <div class="row g-3 text-center mt-1">
                        @foreach ([
                            ['key' => 'total',     'label' => 'Total',      'class' => 'text--dark'],
                            ['key' => 'processed', 'label' => 'Processed',  'class' => 'text--info'],
                            ['key' => 'valid',     'label' => 'Valid',      'class' => 'text--success'],
                            ['key' => 'invalid',   'label' => 'Invalid',    'class' => 'text--danger'],
                            ['key' => 'duplicate', 'label' => 'Duplicate',  'class' => 'text--warning'],
                            ['key' => 'imported',  'label' => 'Imported',   'class' => 'text--primary'],
                        ] as $stat)
                            <div class="col-6 col-md-2">
                                <div class="border rounded py-3">
                                    <h4 class="mb-0 {{ $stat['class'] }}" data-stat="{{ $stat['key'] }}">
                                        {{ $import->{$stat['key'] === 'total' ? 'total_records' : ($stat['key'] . '_records')} ?? 0 }}
                                    </h4>
                                    <small class="text-muted">@lang($stat['label'])</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="card-footer d-flex flex-wrap gap-2" id="actionBar">
                    @if ($import->invalid_records || $import->duplicate_records || $import->failed_records)
                        <a href="{{ route('admin.question-import.error.report', $import->id) }}" class="btn btn-outline--danger">
                            <i class="las la-file-download"></i> @lang('Download Error Report')
                        </a>
                    @endif

                    @if ($import->canApprove())
                        <button type="button" class="btn btn--success" id="approveBtn">
                            <i class="las la-check-circle"></i>
                            @lang('Approve Import') ({{ $import->valid_records }})
                        </button>
                    @endif

                    @if (!$import->isFinished() && $import->status != \App\Models\QuestionImport::STATUS_COMPLETED)
                        <button class="btn btn-outline--dark confirmationBtn"
                                data-action="{{ route('admin.question-import.cancel', $import->id) }}"
                                data-question="@lang('Cancel this import? Nothing will be written to the question bank.')">
                            <i class="las la-ban"></i> @lang('Cancel Import')
                        </button>
                    @endif

                    <a href="{{ route('admin.question-import.index') }}" class="btn btn-outline--primary ms-auto">
                        <i class="las la-list"></i> @lang('All Imports')
                    </a>
                </div>
            </div>
        </div>

        {{-- ------------------------------------------------ row review --}}
        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-pills gap-2">
                        @foreach ([
                            'valid'     => ['Valid', $import->valid_records],
                            'invalid'   => ['Invalid', $import->invalid_records],
                            'duplicate' => ['Duplicate', $import->duplicate_records],
                            'imported'  => ['Imported', $import->imported_records],
                            'removed'   => ['Removed', null],
                        ] as $key => [$label, $count])
                            <li class="nav-item">
                                <a class="nav-link @if ($filter === $key) active @endif"
                                   href="{{ route('admin.question-import.preview', $import->id) }}?filter={{ $key }}">
                                    @lang($label)
                                    @if ($count !== null)
                                        <span class="badge bg-light text-dark">{{ $count }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Row')</th>
                                    <th>@lang('Question')</th>
                                    <th>@lang('Category')</th>
                                    <th>@lang('Type')</th>
                                    <th>@lang('Answer')</th>
                                    <th>@lang('Difficulty')</th>
                                    <th>@lang('Status')</th>
                                    @if ($reviewable)
                                        <th>@lang('Action')</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $row)
                                    <tr>
                                        <td>{{ $row->row_number }}</td>
                                        <td style="max-width:340px;">
                                            <span class="d-block">{{ \Illuminate\Support\Str::limit($row->question, 90) }}</span>
                                            @if ($row->validation_errors)
                                                <small class="text--danger d-block mt-1">{{ $row->errorList() }}</small>
                                            @endif
                                            @if ($row->duplicate_flag)
                                                <small class="text--warning d-block mt-1">
                                                    @if ($row->duplicate_question_id)
                                                        @lang('Already in the question bank as') #{{ $row->duplicate_question_id }}
                                                    @else
                                                        @lang('Repeats an earlier row in this same file')
                                                    @endif
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="d-block">{{ $row->category_name ?? '-' }}</span>
                                            <small class="text-muted">{{ $row->sub_category_name ?? '-' }}</small>
                                        </td>
                                        <td><small>{{ $row->question_type ?? '-' }}</small></td>
                                        <td>{{ $row->correct_answer ?? '-' }}</td>
                                        <td>{{ ucfirst($row->difficulty ?? '-') }}</td>
                                        <td>@php echo $row->statusBadge; @endphp</td>
                                        @if ($reviewable)
                                            <td>
                                                <div class="button--group">
                                                    <button type="button" class="btn btn--sm btn-outline--primary editRowBtn"
                                                            data-row='@json($row->editPayload())'>
                                                        <i class="la la-pencil"></i> @lang('Edit')
                                                    </button>
                                                    <button class="btn btn--sm btn-outline--danger confirmationBtn"
                                                            data-action="{{ route('admin.question-import.row.remove', [$import->id, $row->id]) }}"
                                                            data-question="@lang('Remove row') {{ $row->row_number }} @lang('from this import?')">
                                                        <i class="la la-times"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">
                                            @lang('No rows with this status.')
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($rows->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($rows) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ------------------------------------------------ edit modal --}}
    <div class="modal fade" id="editRowModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="editRowForm">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Edit Row') <span id="editRowNumber"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">@lang('Category')</label>
                                <input type="text" name="category_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('Sub-category')</label>
                                <input type="text" name="sub_category_name" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">@lang('Question')</label>
                                <textarea name="question" rows="2" class="form-control" required></textarea>
                            </div>
                            @foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $key => $letter)
                                <div class="col-md-6">
                                    <label class="form-label">@lang('Option') {{ $letter }}</label>
                                    <input type="text" name="option_{{ $key }}" class="form-control">
                                </div>
                            @endforeach
                            <div class="col-md-4">
                                <label class="form-label">@lang('Correct Answer')</label>
                                <input type="text" name="correct_answer" class="form-control" placeholder="A" required>
                                <small class="text-muted">@lang('Use A–D, or A,C for multi-answer')</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">@lang('Question Type')</label>
                                <select name="question_type" class="form-control" required>
                                    <option value="mcq_single">mcq_single</option>
                                    <option value="mcq_multi">mcq_multi</option>
                                    <option value="true_false">true_false</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">@lang('Difficulty')</label>
                                <select name="difficulty" class="form-control" required>
                                    <option value="easy">@lang('Easy')</option>
                                    <option value="medium">@lang('Medium')</option>
                                    <option value="hard">@lang('Hard')</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">@lang('Explanation')</label>
                                <textarea name="explanation" rows="3" class="form-control"></textarea>
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

    {{-- ------------------------------------------------ approve confirmation --}}
    <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('admin.question-import.approve', $import->id) }}">
                    @csrf
                    <input type="hidden" name="expected_valid" id="expectedValid" value="{{ $import->valid_records }}">
                    <div class="modal-header">
                        <h5 class="modal-title">@lang('Confirm Import')</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">
                            @lang('Are you sure you want to import')
                            <strong id="approveCount">{{ $import->valid_records }}</strong>
                            @lang('valid questions into the Question Bank?')
                        </p>
                        <p class="text-muted mb-0">
                            @lang('Invalid and duplicate rows are skipped. The insert runs in a single transaction — if any row fails, the whole import is rolled back and nothing is added.')
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                        <button type="submit" class="btn btn--success">@lang('Yes, Import Now')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('script')
    <script>
        (function () {
            "use strict";

            const root = document.getElementById('importRoot');
            if (!root) return;

            const importId  = root.dataset.importId;
            const processUrl = "{{ route('admin.question-import.process', $import->id) }}";
            const csrf = "{{ csrf_token() }}";

            const setBar = (barId, pctId, percent) => {
                const bar = document.getElementById(barId);
                const txt = document.getElementById(pctId);
                if (bar) bar.style.width = percent + '%';
                if (txt) txt.textContent = percent + '%';
            };

            const setStat = (key, value) => {
                document.querySelectorAll('[data-stat="' + key + '"]').forEach(el => el.textContent = value);
            };

            const render = (d) => {
                const total = d.total || 0;
                const processPct  = total ? Math.round((d.processed / total) * 100) : 0;
                const validatePct = total ? Math.round(((d.valid + d.invalid + d.duplicate) / total) * 100) : 0;

                setBar('barProcess', 'pctProcess', processPct);
                setBar('barValidate', 'pctValidate', validatePct);

                setStat('total', d.total);
                setStat('processed', d.processed);
                setStat('valid', d.valid);
                setStat('invalid', d.invalid);
                setStat('duplicate', d.duplicate);
                setStat('imported', d.imported);

                const expected = document.getElementById('expectedValid');
                if (expected) expected.value = d.valid;
                const approveCount = document.getElementById('approveCount');
                if (approveCount) approveCount.textContent = d.valid;
            };

            const showError = (msg) => {
                const box = document.getElementById('importError');
                if (!box) return;
                box.classList.remove('d-none');
                box.innerHTML = '<strong>@lang("Error"):</strong> ' + msg;
            };

            // Drives chunked processing: each response reports real progress, and
            // the next chunk is only requested once the previous one has landed.
            const runChunk = () => {
                fetch(processUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(async (res) => {
                    const payload = await res.json().catch(() => null);
                    if (!payload) throw new Error('Unexpected server response.');
                    return payload;
                })
                .then((payload) => {
                    if (payload.data) render(payload.data);

                    if (!payload.success) {
                        showError(payload.message || 'Processing failed.');
                        return;
                    }

                    if (payload.done) {
                        // Reload so the row tables, tabs and action bar reflect
                        // the finished import rather than the empty initial state.
                        window.location.reload();
                        return;
                    }

                    runChunk();
                })
                .catch((err) => showError(err.message));
            };

            if (root.dataset.processing === '1') {
                runChunk();
            }

            // ---- edit modal wiring
            const editModalEl = document.getElementById('editRowModal');
            const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;

            document.querySelectorAll('.editRowBtn').forEach((btn) => {
                btn.addEventListener('click', function () {
                    const data = JSON.parse(this.dataset.row);
                    const form = document.getElementById('editRowForm');
                    form.action = "{{ url('admin/question-import/row') }}/" + importId + "/" + data.id;
                    document.getElementById('editRowNumber').textContent = '#' + data.row_number;

                    Object.keys(data).forEach((key) => {
                        const field = form.querySelector('[name="' + key + '"]');
                        if (field) field.value = data[key] ?? '';
                    });

                    editModal.show();
                });
            });

            // ---- approve confirmation
            const approveBtn = document.getElementById('approveBtn');
            const approveModalEl = document.getElementById('approveModal');
            if (approveBtn && approveModalEl) {
                const approveModal = new bootstrap.Modal(approveModalEl);
                approveBtn.addEventListener('click', () => approveModal.show());
            }
        })();
    </script>
@endpush
