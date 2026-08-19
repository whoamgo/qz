@extends('admin.layouts.app')

@section('panel')
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">

                    {{-- Step indicator --}}
                    <ol class="qw-steps" id="qwSteps" role="tablist" aria-label="Quiz form steps">
                        @foreach ([
                            1 => ['Basic Details', 'la-info-circle'],
                            2 => ['Access & Difficulty', 'la-sliders-h'],
                            3 => ['Scoring & Timing', 'la-stopwatch'],
                            4 => ['Display Settings', 'la-eye'],
                        ] as $n => [$label, $icon])
                            <li class="qw-step {{ $n === 1 ? 'is-active' : '' }}" data-step="{{ $n }}"
                                role="tab" tabindex="0" aria-selected="{{ $n === 1 ? 'true' : 'false' }}">
                                <span class="qw-step-dot"><i class="las {{ $icon }}"></i></span>
                                <span class="qw-step-text">
                                    <span class="qw-step-num">@lang('Step') {{ $n }}</span>
                                    <span class="qw-step-label">@lang($label)</span>
                                </span>
                            </li>
                        @endforeach
                    </ol>

                    <div class="qw-progress" aria-hidden="true">
                        <div class="qw-progress-bar" id="qwProgressBar" style="width: 25%"></div>
                    </div>

                    <form action="{{ route('admin.quiz.store', $quiz?->id ?? 0) }}" method="POST"
                          enctype="multipart/form-data" id="qwForm" novalidate>
                        @csrf

                        {{-- ============ STEP 1 — BASIC DETAILS ============ --}}
                        <section class="qw-panel is-active" data-panel="1">
                            <div class="row g-4">
                                <div class="col-lg-4">
                                    <label class="form-label fw-semibold">@lang('Quiz Image')</label>
                                    <div class="text-center">
                                        <x-image-uploader class="w-100" type="exam" image="{{ $quiz?->image ?? '' }}" :required="false" />
                                    </div>
                                    <small class="text-muted d-block mt-2">
                                        @lang('Supported'): .png, .jpg, .jpeg &middot; @lang('Resized to') {{ getFileSize('exam') }}px
                                    </small>
                                </div>

                                <div class="col-lg-8">
                                    <div class="row g-3">
                                        <div class="col-md-7">
                                            <label class="form-label fw-semibold" for="title">
                                                @lang('Quiz Title') <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="title" id="title" class="form-control"
                                                   value="{{ old('title', $quiz?->title) }}" required
                                                   placeholder="@lang('e.g. Indian Polity Practice Test')">
                                            <small class="text-muted">@lang('The name shown to users')</small>
                                        </div>

                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold" for="slug">
                                                @lang('Slug') <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" name="slug" id="slug" class="form-control"
                                                   value="{{ old('slug', $quiz?->slug) }}" required>
                                            <small class="text-muted">@lang('Auto-filled from the title')</small>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label fw-semibold" for="description">@lang('Description')</label>
                                            <textarea name="description" id="description" class="form-control" rows="3"
                                                      placeholder="@lang('Short summary shown on the quiz page')">{{ old('description', $quiz?->description) }}</textarea>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold" for="category_id">
                                                @lang('Category') <span class="text-danger">*</span>
                                            </label>
                                            <select name="category_id" id="category_id" class="form-select" required>
                                                <option value="">@lang('Select category')</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}"
                                                        @selected(old('category_id', $quiz?->category_id) == $category->id)>
                                                        {{ __($category->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold" for="sub_category_id">
                                                @lang('Sub-category')
                                                <span class="text-danger d-none" id="subCatRequired">*</span>
                                            </label>
                                            <select name="sub_category_id" id="sub_category_id" class="form-select">
                                                <option value="">@lang('None')</option>
                                                @foreach ($subCategories as $sub)
                                                    <option value="{{ $sub->id }}"
                                                        @selected(old('sub_category_id', $quiz?->sub_category_id) == $sub->id)>
                                                        {{ __($sub->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted" id="subCatHint">
                                                @lang('Required when the selected category has sub-categories.')
                                            </small>
                                            @error('sub_category_id')
                                                <div class="text-danger small mt-1">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        {{-- ============ STEP 2 — ACCESS & DIFFICULTY ============ --}}
                        <section class="qw-panel" data-panel="2">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="quiz_type">
                                        @lang('Quiz Type') <span class="text-danger">*</span>
                                    </label>
                                    <select name="quiz_type" id="quiz_type" class="form-select" required>
                                        @foreach (['free' => 'Free', 'paid' => 'Paid', 'subscription' => 'Subscription'] as $v => $l)
                                            <option value="{{ $v }}" @selected(old('quiz_type', $quiz?->quiz_type ?? 'free') == $v)>@lang($l)</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">@lang('How users get access')</small>
                                </div>

                                <div class="col-md-4" id="price_wrapper"
                                     style="display: {{ old('quiz_type', $quiz?->quiz_type ?? 'free') == 'paid' ? 'block' : 'none' }};">
                                    <label class="form-label fw-semibold" for="price_input">
                                        @lang('Price') <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ gs('cur_sym') }}</span>
                                        <input type="number" step="any" min="0" name="price" id="price_input"
                                               class="form-control" value="{{ old('price', $quiz?->price ?? 0) }}">
                                    </div>
                                    <small class="text-muted">@lang('Required for paid quizzes')</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="difficulty">
                                        @lang('Difficulty') <span class="text-danger">*</span>
                                    </label>
                                    <select name="difficulty" id="difficulty" class="form-select" required>
                                        @foreach (['easy' => 'Easy', 'medium' => 'Medium', 'hard' => 'Hard'] as $v => $l)
                                            <option value="{{ $v }}" @selected(old('difficulty', $quiz?->difficulty ?? 'medium') == $v)>@lang($l)</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="status">
                                        @lang('Status') <span class="text-danger">*</span>
                                    </label>
                                    <select name="status" id="status" class="form-select" required>
                                        @foreach (['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'] as $v => $l)
                                            <option value="{{ $v }}" @selected(old('status', $quiz?->status ?? 'draft') == $v)>@lang($l)</option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">@lang('Only published quizzes appear on the website')</small>
                                </div>
                            </div>
                        </section>

                        {{-- ============ STEP 3 — SCORING & TIMING ============ --}}
                        <section class="qw-panel" data-panel="3">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="total_questions">
                                        @lang('Total Questions') <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" min="0" name="total_questions" id="total_questions" class="form-control"
                                           value="{{ old('total_questions', $quiz?->total_questions ?? 0) }}" required>
                                    <small class="text-muted">@lang('Updated automatically as you attach questions')</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="question_limit">
                                        @lang('Question Limit')
                                    </label>
                                    <input type="number" min="0" name="question_limit" id="question_limit" class="form-control"
                                           value="{{ old('question_limit', $quiz?->question_limit ?? 0) }}">
                                    <small class="text-muted">
                                        @lang('How many questions each attempt shows, picked at random. 0 = show all.')
                                        @if ($quiz)
                                            <span class="d-block mt-1">
                                                @lang('Bank has'): <strong>{{ $quiz->questions()->count() }}</strong> @lang('questions')
                                            </span>
                                        @endif
                                    </small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="time_limit">
                                        @lang('Time Limit') <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" min="0" name="time_limit" id="time_limit" class="form-control"
                                               value="{{ old('time_limit', $quiz?->time_limit ?? 0) }}" required>
                                        <span class="input-group-text">@lang('Min')</span>
                                    </div>
                                    <small class="text-muted">@lang('0 = no time limit')</small>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="pass_percentage">
                                        @lang('Passing %') <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number" min="0" max="100" name="pass_percentage" id="pass_percentage"
                                               class="form-control" value="{{ old('pass_percentage', $quiz?->pass_percentage ?? 0) }}" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="marks_per_correct">
                                        @lang('Marks / Answer') <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" step="any" min="0" name="marks_per_correct" id="marks_per_correct"
                                           class="form-control" value="{{ old('marks_per_correct', $quiz?->marks_per_correct ?? 1) }}" required>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-semibold" for="negative_marking">
                                        @lang('Negative Marking') <span class="text-danger">*</span>
                                    </label>
                                    <input type="number" step="any" min="0" name="negative_marking" id="negative_marking"
                                           class="form-control" value="{{ old('negative_marking', $quiz?->negative_marking ?? 0) }}" required>
                                    <small class="text-muted">@lang('Marks deducted per wrong answer. 0 = none')</small>
                                </div>
                            </div>
                        </section>

                        {{-- ============ STEP 4 — DISPLAY SETTINGS ============ --}}
                        <section class="qw-panel" data-panel="4">
                            {{-- Home-page promotion: feature this quiz in the
                                 Most Popular Quizzes slider on the website. --}}
                            <div class="qw-toggle-card qw-toggle-feature mb-3">
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           name="is_popular" id="is_popular" value="1"
                                           @checked(old('is_popular', $quiz?->is_popular ?? false))>
                                    <label class="form-check-label fw-semibold" for="is_popular">
                                        <i class="las la-star text-warning"></i> @lang('Show in Most Popular')
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-1">
                                    @lang('Feature this quiz in the Most Popular Quizzes slider on the home page. Only published quizzes with questions appear there.')
                                </small>
                            </div>

                            <div class="row g-3">
                                @foreach ([
                                    ['randomize_questions', 'Randomize Questions', 'Shuffle question order on every attempt', false],
                                    ['randomize_options',   'Randomize Options',   'Shuffle answer options on every attempt', false],
                                    ['show_result',         'Show Result',         'Show the score immediately after submitting', true],
                                    ['show_correct_answers','Show Correct Answers', 'Let users review the correct answers', true],
                                    ['show_explanation',    'Show Explanation',    'Include the written explanation in the review', true],
                                ] as [$name, $label, $note, $default])
                                    <div class="col-md-6 col-xl-4">
                                        <div class="qw-toggle-card">
                                            <div class="form-check form-switch m-0">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                       name="{{ $name }}" id="{{ $name }}" value="1"
                                                       @checked(old($name, $quiz?->{$name} ?? $default))>
                                                <label class="form-check-label fw-semibold" for="{{ $name }}">@lang($label)</label>
                                            </div>
                                            <small class="text-muted d-block mt-1">@lang($note)</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            {{-- Read-only recap so the admin can confirm before saving --}}
                            <div class="qw-review mt-4">
                                <h6 class="mb-3"><i class="las la-clipboard-check"></i> @lang('Review')</h6>
                                <div class="row g-3" id="qwReview">
                                    @foreach ([
                                        'title' => 'Title', 'category_id' => 'Category', 'quiz_type' => 'Type',
                                        'difficulty' => 'Difficulty', 'total_questions' => 'Questions',
                                        'time_limit' => 'Time (min)', 'pass_percentage' => 'Pass %', 'status' => 'Status',
                                    ] as $field => $label)
                                        <div class="col-6 col-md-3">
                                            <small class="text-muted d-block">@lang($label)</small>
                                            <strong data-review="{{ $field }}">—</strong>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>

                        {{-- Navigation --}}
                        <div class="qw-actions">
                            <button type="button" class="btn btn--dark" id="qwPrev" style="visibility:hidden;">
                                <i class="las la-arrow-left"></i> @lang('Back')
                            </button>
                            <span class="text-muted qw-count" id="qwCount">@lang('Step') 1 @lang('of') 4</span>
                            <button type="button" class="btn btn--primary" id="qwNext">
                                @lang('Next') <i class="las la-arrow-right"></i>
                            </button>
                            <button type="submit" class="btn btn--success" id="qwSubmit" style="display:none;">
                                <i class="las la-check"></i> {{ $quiz ? __('Update Quiz') : __('Create Quiz') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('breadcrumb-plugins')
    <a href="{{ route('admin.quiz.index') }}" class="btn btn-sm btn-outline--primary">
        <i class="las la-list"></i>@lang('All Quizzes')
    </a>
@endpush

@push('style')
<style>
/* Quiz wizard — scoped to this page only. */
.qw-steps { display: flex; gap: .5rem; list-style: none; padding: 0; margin: 0 0 1rem; flex-wrap: wrap; }
.qw-step { flex: 1 1 160px; display: flex; align-items: center; gap: .6rem; padding: .7rem .9rem;
           border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; cursor: pointer;
           transition: border-color .2s, background .2s, box-shadow .2s; }
.qw-step:hover { border-color: #c7d2fe; }
.qw-step-dot { width: 32px; height: 32px; flex: 0 0 32px; border-radius: 50%; display: grid; place-items: center;
               background: #f1f5f9; color: #64748b; font-size: 1rem; transition: background .2s, color .2s; }
.qw-step-text { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
.qw-step-num { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
.qw-step-label { font-size: .86rem; font-weight: 600; color: #334155; white-space: nowrap;
                 overflow: hidden; text-overflow: ellipsis; }
.qw-step.is-active { border-color: #4f46e5; background: #eef2ff; box-shadow: 0 2px 8px rgba(79,70,229,.12); }
.qw-step.is-active .qw-step-dot { background: #4f46e5; color: #fff; }
.qw-step.is-done .qw-step-dot { background: #16a34a; color: #fff; }
.qw-step.is-invalid { border-color: #dc2626; background: #fef2f2; }

.qw-progress { height: 4px; background: #e5e7eb; border-radius: 4px; overflow: hidden; margin-bottom: 1.75rem; }
.qw-progress-bar { height: 100%; background: #4f46e5; border-radius: 4px; transition: width .3s ease; }

.qw-panel { display: none; animation: qwIn .25s ease; }
.qw-panel.is-active { display: block; }
@keyframes qwIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

.qw-toggle-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: .9rem 1rem; height: 100%;
                  transition: border-color .2s, background .2s; }
.qw-toggle-card:hover { border-color: #c7d2fe; }
.qw-toggle-card .form-check-label { color: #334155; cursor: pointer; }
.qw-toggle-card .form-check-input { cursor: pointer; }
.qw-toggle-feature { border-color: #fcd34d; background: #fffbeb; }
.qw-toggle-feature:hover { border-color: #f59e0b; }

.qw-review { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem 1.25rem; }
.qw-review strong { color: #0f172a; word-break: break-word; }

.qw-actions { display: flex; align-items: center; justify-content: space-between; gap: 1rem;
              margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #e5e7eb; }
.qw-count { font-size: .85rem; }

.qw-panel .form-control.is-invalid,
.qw-panel .form-select.is-invalid { border-color: #dc2626; }
.qw-error { color: #dc2626; font-size: .8rem; margin-top: .25rem; display: block; }

@media (max-width: 767.98px) {
    .qw-step { flex: 1 1 100%; }
    .qw-actions { flex-wrap: wrap; }
    .qw-actions .btn { flex: 1 1 auto; }
    .qw-count { order: -1; width: 100%; text-align: center; }
}
</style>
@endpush

@push('script')
<script>
    "use strict";
    (function ($) {
        var TOTAL = 4;
        var current = 1;

        var $panels = $('.qw-panel');
        var $steps  = $('.qw-step');

        function show(step) {
            current = Math.min(Math.max(step, 1), TOTAL);

            $panels.removeClass('is-active').filter('[data-panel="' + current + '"]').addClass('is-active');

            $steps.each(function () {
                var n = parseInt($(this).data('step'), 10);
                $(this).toggleClass('is-active', n === current)
                       .removeClass('is-done')
                       .attr('aria-selected', n === current ? 'true' : 'false');
                if (n < current) { $(this).addClass('is-done'); }
            });

            $('#qwProgressBar').css('width', (current / TOTAL * 100) + '%');
            $('#qwCount').text('@lang('Step') ' + current + ' @lang('of') ' + TOTAL);
            $('#qwPrev').css('visibility', current === 1 ? 'hidden' : 'visible');
            $('#qwNext').toggle(current < TOTAL);
            $('#qwSubmit').toggle(current === TOTAL);

            if (current === TOTAL) { buildReview(); }
            $('html, body').animate({ scrollTop: $('#qwSteps').offset().top - 90 }, 200);
        }

        // Validates only the fields in the current step, so the admin is not
        // blocked by a required field on a step they have not reached yet.
        function validateStep(step) {
            var ok = true;
            $('.qw-panel[data-panel="' + step + '"]').find('[required]:visible').each(function () {
                var $f = $(this);
                $f.next('.qw-error').remove();
                if (!$.trim($f.val())) {
                    ok = false;
                    $f.addClass('is-invalid').after('<span class="qw-error">@lang('This field is required')</span>');
                } else {
                    $f.removeClass('is-invalid');
                }
            });
            $('.qw-step[data-step="' + step + '"]').toggleClass('is-invalid', !ok);
            return ok;
        }

        function buildReview() {
            $('[data-review]').each(function () {
                var name = $(this).data('review');
                var $field = $('[name="' + name + '"]');
                var val = $field.is('select') ? $field.find(':selected').text() : $field.val();
                $(this).text($.trim(val) || '—');
            });
        }

        $('#qwNext').on('click', function () {
            if (validateStep(current)) { show(current + 1); }
        });
        $('#qwPrev').on('click', function () { show(current - 1); });

        // Step chips are clickable, but only validate on the way forward.
        $steps.on('click keypress', function (e) {
            if (e.type === 'keypress' && e.which !== 13) { return; }
            var target = parseInt($(this).data('step'), 10);
            if (target <= current || validateStep(current)) { show(target); }
        });

        // Final guard: check every step before submitting.
        $('#qwForm').on('submit', function (e) {
            for (var s = 1; s <= TOTAL; s++) {
                if (!validateStep(s)) {
                    e.preventDefault();
                    show(s);
                    return false;
                }
            }
        });

        // ---- slug auto-fill (unchanged behaviour) ----
        var slugTouched = {{ $quiz ? 'true' : 'false' }};
        $('#slug').on('input', function () { slugTouched = true; });
        $('#title').on('keyup change', function () {
            if (slugTouched) { return; }
            $('#slug').val(
                $(this).val().toLowerCase().trim()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
            );
        });

        // ---- price shows only for paid quizzes ----
        function toggleQuizTypeFields() {
            var paid = $('#quiz_type').val() === 'paid';
            $('#price_wrapper').toggle(paid);
            if (paid) { $('#price_input').attr('required', 'required'); }
            else { $('#price_input').removeAttr('required').val(0); }
        }
        $('#quiz_type').on('change', toggleQuizTypeFields);
        toggleQuizTypeFields();

        // ---- sub-category list follows the chosen category ----
        $('#category_id').on('change', function () {
            var categoryId = $(this).val();
            var $sub = $('#sub_category_id');
            $sub.html('<option value="">@lang('Loading...')</option>');

            if (!categoryId) {
                $sub.html('<option value="">@lang('None')</option>');
                return;
            }

            $.get("{{ route('admin.quiz.subcategories') }}", { category_id: categoryId }, function (res) {
                var items = res.data || [];
                var required = items.length > 0;

                var html = '<option value="">' + (required ? '@lang('Select sub-category')' : '@lang('None')') + '</option>';
                $.each(items, function (i, item) {
                    html += '<option value="' + item.id + '">' + item.name + '</option>';
                });
                $sub.html(html);

                // Mirror the server rule in the UI: mandatory only when the
                // category actually has sub-categories.
                $sub.prop('required', required).prop('disabled', !required);
                $('#subCatRequired').toggleClass('d-none', !required);
                $('#subCatHint').text(required
                    ? '@lang('This category has sub-categories — choose one.')'
                    : '@lang('This category has no sub-categories.')');
            }).fail(function () {
                $sub.html('<option value="">@lang('Could not load sub-categories')</option>');
            });
        });

        show(1);
    })(jQuery);
</script>
@endpush
