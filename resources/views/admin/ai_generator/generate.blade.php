@extends('admin.layouts.app')
@section('panel')
    @if (!$settings->enabled)
        <div class="alert alert-warning">
            <i class="las la-exclamation-triangle"></i>
            @lang('The AI Question Generator is currently disabled.')
            <a href="{{ route('admin.ai-settings.index') }}" class="alert-link">@lang('Enable it in AI Settings')</a>.
        </div>
    @elseif (!$settings->hasKeyFor($settings->provider))
        <div class="alert alert-danger">
            <i class="las la-key"></i>
            @lang('No API key is configured for') <strong>{{ \App\Models\AiGenerationSetting::PROVIDERS[$settings->provider] ?? $settings->provider }}</strong>.
            <a href="{{ route('admin.ai-settings.index') }}" class="alert-link">@lang('Add one in AI Settings')</a>.
        </div>
    @endif

    <form action="{{ route('admin.ai-generator.generate') }}" method="POST" id="generateForm">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="las la-robot"></i> @lang('Generate Questions')</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">@lang('Category') <span class="text--danger">*</span></label>
                                <select name="category_id" id="categorySelect" class="form-control select2" required>
                                    <option value="">@lang('Select category')</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ __($category->name) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">@lang('Sub-category')</label>
                                <select name="sub_category_id" id="subCategorySelect" class="form-control">
                                    <option value="">@lang('Select a category first')</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">@lang('Topic')</label>
                                <input type="text" name="topic" class="form-control" value="{{ old('topic') }}"
                                       placeholder="@lang('e.g. Rivers of India (optional)')">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">@lang('Add to Quiz')</label>
                                <select name="quiz_id" class="form-control select2">
                                    <option value="">@lang('None - review only')</option>
                                    @foreach ($quizzes as $quiz)
                                        <option value="{{ $quiz->id }}" @selected(old('quiz_id') == $quiz->id)>{{ $quiz->title }}</option>
                                    @endforeach
                                </select>
                                <small class="text-muted">@lang('Approved questions can be added to this quiz afterwards.')</small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">@lang('Question Type')</label>
                                <select name="question_type" class="form-control">
                                    @foreach (\App\Models\AiGenerationSetting::QUESTION_TYPES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('question_type', $settings->default_question_type) == $value)>@lang($label)</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">@lang('Difficulty')</label>
                                <select name="difficulty" class="form-control">
                                    @foreach (\App\Models\AiGenerationSetting::DIFFICULTIES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('difficulty', $settings->default_difficulty) == $value)>@lang($label)</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">@lang('Language')</label>
                                <select name="language" class="form-control">
                                    @foreach (\App\Models\AiGenerationSetting::LANGUAGES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('language', $settings->default_language) == $value)>@lang($label)</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">@lang('Number of Questions')</label>
                                <select name="quantity" class="form-control">
                                    @foreach (\App\Models\AiGenerationSetting::QUANTITIES as $qty)
                                        @if ($qty <= $settings->max_quantity)
                                            <option value="{{ $qty }}" @selected(old('quantity', $settings->default_quantity) == $qty)>{{ $qty }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label">@lang('Additional Instructions')</label>
                                <textarea name="additional_instructions" rows="5" class="form-control"
                                          placeholder="@lang('Optional. e.g. Focus on questions asked in SSC CGL. Avoid current affairs after 2023.')">{{ old('additional_instructions', $settings->default_user_prompt) }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn--primary w-100" id="generateBtn"
                                @disabled(!$settings->enabled || !$settings->hasKeyFor($settings->provider))>
                            <i class="las la-magic"></i> @lang('Generate Questions')
                        </button>
                        <small class="text-muted d-block mt-2 text-center">
                            @lang('Generated questions go to a review screen. Nothing is written to the Question Bank until you approve it.')
                        </small>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">@lang('Active Configuration')</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>@lang('Provider')</span>
                                <strong>{{ \App\Models\AiGenerationSetting::PROVIDERS[$settings->provider] ?? $settings->provider }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>@lang('Model')</span>
                                <strong>{{ $settings->model }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>@lang('Temperature')</span>
                                <strong>{{ $settings->temperature }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>@lang('Max Tokens')</span>
                                <strong>{{ number_format($settings->max_tokens) }}</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between px-0">
                                <span>@lang('API Key')</span>
                                <strong class="{{ $settings->hasKeyFor($settings->provider) ? 'text--success' : 'text--danger' }}">
                                    {{ $settings->hasKeyFor($settings->provider) ? __('Configured') : __('Missing') }}
                                </strong>
                            </li>
                        </ul>
                        <a href="{{ route('admin.ai-settings.index') }}" class="btn btn-outline--primary btn--sm w-100 mt-3">
                            <i class="las la-cog"></i> @lang('AI Settings')
                        </a>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="mb-2">@lang('Note on Expert difficulty')</h6>
                        <p class="text-muted mb-0 small">
                            @lang('The Question Bank stores only Easy, Medium and Hard. Questions generated as Expert are kept as Expert during review, then saved as Hard when imported.')
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('script')
    <script>
        (function () {
            "use strict";

            const categorySelect = document.getElementById('categorySelect');
            const subSelect = document.getElementById('subCategorySelect');
            const subUrl = "{{ route('admin.ai-generator.subcategories') }}";
            const preselected = "{{ old('sub_category_id') }}";

            const loadSubCategories = (categoryId) => {
                subSelect.innerHTML = '<option value="">@lang("Loading...")</option>';

                if (!categoryId) {
                    subSelect.innerHTML = '<option value="">@lang("Select a category first")</option>';
                    return;
                }

                fetch(subUrl + '?category_id=' + encodeURIComponent(categoryId), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                })
                .then(res => res.json())
                .then(payload => {
                    let html = '<option value="">@lang("All sub-categories")</option>';
                    (payload.data || []).forEach(item => {
                        const selected = String(item.id) === preselected ? ' selected' : '';
                        html += '<option value="' + item.id + '"' + selected + '>' + item.name + '</option>';
                    });
                    subSelect.innerHTML = html;
                })
                .catch(() => {
                    subSelect.innerHTML = '<option value="">@lang("Could not load sub-categories")</option>';
                });
            };

            categorySelect.addEventListener('change', function () {
                loadSubCategories(this.value);
            });

            if (categorySelect.value) {
                loadSubCategories(categorySelect.value);
            }

            // Generation is a synchronous request that can take a while; block
            // double submits and show that work is happening.
            const form = document.getElementById('generateForm');
            const btn = document.getElementById('generateBtn');
            form.addEventListener('submit', function () {
                btn.disabled = true;
                btn.innerHTML = '<i class="las la-spinner la-spin"></i> @lang("Generating, please wait...")';
            });
        })();
    </script>
@endpush
