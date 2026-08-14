@extends('admin.layouts.app')
@section('panel')
    {{-- ============ AI QUESTION PROMPT GENERATOR ============ --}}
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0">
                        <i class="las la-robot"></i> @lang('AI Question Prompt Generator')
                    </h5>
                    <small class="text-muted">
                        @lang('Builds a ready-to-paste prompt that produces a CSV matching this importer exactly.')
                    </small>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="aiCategory">
                                @lang('Category') <span class="text-danger">*</span>
                            </label>
                            <select id="aiCategory" class="form-select">
                                <option value="">@lang('Select category')</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" data-name="{{ $category->name }}">
                                        {{ __($category->name) }} (ID: {{ $category->id }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold" for="aiSubCategory">
                                @lang('Sub-category')
                                <span class="text-danger d-none" id="aiSubRequired">*</span>
                            </label>
                            <select id="aiSubCategory" class="form-select" disabled>
                                <option value="">@lang('Select a category first')</option>
                            </select>
                            <small class="text-muted" id="aiSubHint"></small>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold" for="aiCount">
                                @lang('Question Count') <span class="text-danger">*</span>
                            </label>
                            <input type="number" id="aiCount" class="form-control" value="100" min="1" max="5000">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold" for="aiType">
                                @lang('Question Type') <span class="text-danger">*</span>
                            </label>
                            <select id="aiType" class="form-select">
                                <option value="mcq_single">@lang('MCQ Single')</option>
<!--                                 <option value="mcq_multi">@lang('MCQ Multi')</option>
                                <option value="true_false">@lang('True / False')</option>
 -->                            </select>
                        </div>

                        <div class="col-md-2">
                            <button type="button" class="btn btn--primary w-100" id="aiGenerateBtn">
                                <i class="las la-magic"></i> @lang('Generate AI Prompt')
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-danger mt-3 d-none" id="aiError"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">@lang('Import Quizzes')</h5></div>
                <div class="card-body">
                    <div class="row gy-4">
                        <div class="col-md-5">
                            <h6 class="mb-2">@lang('Step 1 — Get the template')</h6>
                            <p class="text-muted mb-3">
                                @lang('One row per question. Rows that share the same quiz_slug (or quiz_title) are grouped into a single quiz, so a file can create many quizzes at once.')
                            </p>
                            <a href="{{ route('admin.quiz-import.template') }}" class="btn btn-outline--primary">
                                <i class="las la-download"></i> @lang('Download CSV Template')
                            </a>
                        </div>

                        <div class="col-md-7">
                            <h6 class="mb-2">@lang('Step 2 — Upload your file')</h6>
                            <form action="{{ route('admin.quiz-import.upload') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="form-group">
                                    <label class="form-label">@lang('CSV File')</label>
                                    <input type="file" name="file" class="form-control" accept=".csv,text/csv" required>
                                    <small class="text-muted">@lang('CSV only, max 10 MB. Nothing is created until you approve the import.')</small>
                                </div>
                                <button type="submit" class="btn btn--primary w-100">
                                    <i class="las la-upload"></i> @lang('Upload & Validate')
                                </button>
                            </form>
                        </div>
                    </div>

                    <hr class="my-4">
                    <h6 class="mb-3">@lang('Columns')</h6>
                    <div class="table-responsive">
                        <table class="table table--light style--two">
                            <thead><tr><th>@lang('Column')</th><th>@lang('Required')</th><th>@lang('Notes')</th></tr></thead>
                            <tbody>
                                @foreach ([
                                    ['quiz_title','yes','Rows sharing a title/slug become one quiz'],
                                    ['quiz_slug','no','Auto-generated from the title if blank'],
                                    ['quiz_description','no','Shown on the quiz page'],
                                    ['category_id','yes','Category id, name or slug — must be top-level'],
                                    ['sub_category_id','conditional','Required when the category HAS sub-categories; must be blank when it does not'],
                                    ['quiz_type','no','free / paid / subscription (default free)'],
                                    ['price','no','Required when quiz_type is paid'],
                                    ['quiz_difficulty','no','easy / medium / hard (default medium)'],
                                    ['time_limit','no','Minutes, 0 = no limit'],
                                    ['question_limit','no','Questions served per attempt, chosen at random. 0 = all'],
                                    ['pass_percentage','no','0-100'],
                                    ['marks_per_correct','no','Default 1'],
                                    ['negative_marking','no','Default 0'],
                                    ['quiz_status','no','draft / published / archived (default draft)'],
                                    ['question','yes','Minimum 10 characters'],
                                    ['question_type','yes','mcq_single / mcq_multi / true_false'],
                                    ['option_a … option_d','yes','All four for MCQ; A and B only for true_false'],
                                    ['correct_answer','yes','A–D, or "A,C" for mcq_multi'],
                                    ['explanation','no','Up to 5000 characters'],
                                    ['question_difficulty','no','Falls back to quiz_difficulty'],
                                ] as [$col,$req,$note])
                                    <tr>
                                        <td><code>{{ $col }}</code></td>
                                        <td><span class="badge badge--{{ $req === 'yes' ? 'danger' : ($req === 'conditional' ? 'warning' : 'secondary') }}">{{ ucfirst($req) }}</span></td>
                                        <td class="text-muted">{{ $note }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">@lang('Recent Quiz Imports')</h5>
                    <a href="{{ route('admin.quiz-import.history') }}" class="btn btn--sm btn-outline--primary">@lang('Full History')</a>
                </div>
                <div class="card-body p-0">
                    @include('admin.quiz_import.partials.table', ['imports' => $imports])
                </div>
                @if ($imports->hasPages())
                    <div class="card-footer py-4">{{ paginateLinks($imports) }}</div>
                @endif
            </div>
        </div>
    </div>

{{-- Generated prompt --}}
<div class="modal fade" id="aiPromptModal" tabindex="-1" aria-labelledby="aiPromptLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aiPromptLabel">
                    <i class="las la-robot"></i> @lang('Generated AI Prompt')
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="@lang('Close')"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3 mb-3" id="aiSummary">
                    @foreach ([
                        ['Category','aiSumCategory'], ['Sub-category','aiSumSub'],
                        ['Questions','aiSumCount'], ['Question Type','aiSumType'], ['CSV Columns','aiSumCols'],
                    ] as [$label,$id])
                        <div class="col-6 col-md">
                            <div class="border rounded p-2 h-100">
                                <small class="text-muted d-block">@lang($label)</small>
                                <strong id="{{ $id }}">—</strong>
                            </div>
                        </div>
                    @endforeach
                </div>

                <label class="form-label fw-semibold" for="aiPromptText">@lang('Prompt')</label>
                <textarea id="aiPromptText" class="form-control" rows="18" readonly
                          style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 0.82rem;"></textarea>
                <small class="text-muted d-block mt-2">
                    @lang('Paste this into ChatGPT, Claude or Gemini. Save the reply as .csv and upload it below.')
                </small>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn--primary" id="aiCopyBtn">
                    <i class="las la-copy"></i> @lang('Copy Prompt')
                </button>
                <button type="button" class="btn btn--success" id="aiDownloadBtn">
                    <i class="las la-download"></i> @lang('Download Prompt')
                </button>
                <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Close')</button>
            </div>
        </div>
    </div>
</div>

    <x-confirmation-modal />
@endsection

@push('script')
<script>
    "use strict";
    (function ($) {
        var $cat = $('#aiCategory'), $sub = $('#aiSubCategory');
        var $err = $('#aiError'), promptText = '', fileName = 'ai-prompt.txt';

        function fail(msg) { $err.removeClass('d-none').text(msg); }
        function clearError() { $err.addClass('d-none').text(''); }

        // Sub-categories follow the chosen category, and are required only
        // when that category actually has any — same rule the importer applies.
        $cat.on('change', function () {
            clearError();
            var id = $(this).val();
            $sub.prop('disabled', true).html('<option value="">@lang('Loading...')</option>');

            if (!id) {
                $sub.html('<option value="">@lang('Select a category first')</option>');
                $('#aiSubRequired').addClass('d-none');
                $('#aiSubHint').text('');
                return;
            }

            $.get("{{ route('admin.quiz.subcategories') }}", { category_id: id }, function (res) {
                var items = res.data || [];
                var required = items.length > 0;
                var html = '<option value="">' + (required ? '@lang('Select sub-category')' : '@lang('None')') + '</option>';

                $.each(items, function (i, item) {
                    html += '<option value="' + item.id + '" data-name="' + item.name + '">' +
                            item.name + ' (ID: ' + item.id + ')</option>';
                });

                $sub.html(html).prop('disabled', !required);
                $('#aiSubRequired').toggleClass('d-none', !required);
                $('#aiSubHint').text(required
                    ? '@lang('Required — this category has sub-categories.')'
                    : '@lang('This category has no sub-categories.')');
            }).fail(function () {
                $sub.html('<option value="">@lang('Could not load sub-categories')</option>');
            });
        });

        $('#aiGenerateBtn').on('click', function () {
            clearError();
            var $btn = $(this), original = $btn.html();
            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.ajax({
                url: "{{ route('admin.quiz-import.generate.prompt') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}",
                    category_id: $cat.val(),
                    sub_category_id: $sub.val(),
                    question_count: $('#aiCount').val(),
                    question_type: $('#aiType').val()
                },
                dataType: 'json'
            }).done(function (res) {
                promptText = res.prompt;
                fileName   = res.filename || 'ai-prompt.txt';
                var s = res.summary;

                $('#aiSumCategory').text(s.category + ' (ID: ' + s.category_id + ')');
                $('#aiSumSub').text(s.sub_category ? s.sub_category + ' (ID: ' + s.sub_category_id + ')' : '—');
                $('#aiSumCount').text(s.count);
                $('#aiSumType').text(s.type);
                $('#aiSumCols').text(s.columns);
                $('#aiPromptText').val(promptText);

                new bootstrap.Modal(document.getElementById('aiPromptModal')).show();
            }).fail(function (xhr) {
                fail((xhr.responseJSON && xhr.responseJSON.message) || 'Could not generate the prompt.');
            }).always(function () {
                $btn.prop('disabled', false).html(original);
            });
        });

        $('#aiCopyBtn').on('click', function () {
            var $btn = $(this);
            var done = function () {
                var o = $btn.html();
                $btn.html('<i class="las la-check"></i> @lang('Copied')');
                setTimeout(function () { $btn.html(o); }, 1800);
            };

            if (navigator.clipboard) {
                navigator.clipboard.writeText(promptText).then(done);
            } else {
                // Fallback for non-secure contexts, where the Clipboard API is absent.
                var el = document.getElementById('aiPromptText');
                el.removeAttribute('readonly'); el.select();
                document.execCommand('copy');
                el.setAttribute('readonly', 'readonly');
                done();
            }
        });

        $('#aiDownloadBtn').on('click', function () {
            var blob = new Blob([promptText], { type: 'text/plain;charset=utf-8' });
            var url  = URL.createObjectURL(blob);
            var a    = document.createElement('a');
            a.href = url; a.download = fileName;
            document.body.appendChild(a); a.click();
            document.body.removeChild(a); URL.revokeObjectURL(url);
        });
    })(jQuery);
</script>
@endpush
