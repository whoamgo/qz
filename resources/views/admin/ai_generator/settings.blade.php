@extends('admin.layouts.app')
@section('panel')
    <form action="{{ route('admin.ai-settings.update') }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-lg-8">
                {{-- ------------------------------------------- provider --}}
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">@lang('Provider & Model')</h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="enabled" value="1"
                                   id="enabledSwitch" @checked($settings->enabled)>
                            <label class="form-check-label" for="enabledSwitch">@lang('Enable AI Generator')</label>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">@lang('AI Provider')</label>
                                <select name="provider" class="form-control" id="providerSelect">
                                    @foreach (\App\Models\AiGenerationSetting::PROVIDERS as $value => $label)
                                        <option value="{{ $value }}" @selected($settings->provider == $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">@lang('AI Model')</label>
                                <input type="text" name="model" class="form-control" value="{{ old('model', $settings->model) }}" required>
                                <small class="text-muted" id="modelHint"></small>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">@lang('Temperature')</label>
                                <input type="number" step="0.05" min="0" max="2" name="temperature" class="form-control"
                                       value="{{ old('temperature', $settings->temperature) }}" required>
                                <small class="text-muted">@lang('Lower is more factual. 0.7 is a good default.')</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">@lang('Max Tokens')</label>
                                <input type="number" min="256" max="200000" name="max_tokens" class="form-control"
                                       value="{{ old('max_tokens', $settings->max_tokens) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">@lang('Request Timeout (seconds)')</label>
                                <input type="number" min="10" max="600" name="request_timeout" class="form-control"
                                       value="{{ old('request_timeout', $settings->request_timeout) }}" required>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ------------------------------------------- api keys --}}
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">@lang('API Keys')</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info py-2 small">
                            <i class="las la-lock"></i>
                            @lang('Keys are encrypted at rest and are never sent to the browser. Existing keys are not shown — leave a field blank to keep the stored key unchanged.')
                        </div>

                        @foreach (\App\Models\AiGenerationSetting::PROVIDERS as $value => $label)
                            <div class="row g-2 align-items-end mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">
                                        {{ $label }}
                                        @if ($keyStatus[$value])
                                            <span class="badge badge--success">@lang('Configured')</span>
                                        @else
                                            <span class="badge badge--danger">@lang('Not set')</span>
                                        @endif
                                    </label>
                                    <input type="password" name="{{ $value }}_api_key" class="form-control" autocomplete="new-password"
                                           placeholder="{{ $keyStatus[$value] ? __('Stored — enter a new key to replace it') : __('Paste API key') }}">
                                </div>
                                <div class="col-md-4 d-flex gap-2">
                                    <button type="button" class="btn btn-outline--info flex-grow-1 testBtn" data-provider="{{ $value }}">
                                        <i class="las la-plug"></i> @lang('Test')
                                    </button>
                                    @if ($keyStatus[$value])
                                        <button type="button" class="btn btn-outline--danger clearKeyBtn" data-provider="{{ $value }}">
                                            <i class="las la-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <div id="testResult"></div>
                    </div>
                </div>

                {{-- ------------------------------------------- prompts --}}
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">@lang('Prompts')</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">@lang('System Prompt')</label>
                            <textarea name="system_prompt" rows="10" class="form-control">{{ old('system_prompt', $settings->system_prompt ?: \App\Models\AiGenerationSetting::DEFAULT_SYSTEM_PROMPT) }}</textarea>
                            <small class="text-muted">@lang('Sets the AI\'s role and quality bar. The strict JSON schema is appended automatically and cannot be removed.')</small>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">@lang('Default Additional Instructions')</label>
                            <textarea name="default_user_prompt" rows="4" class="form-control">{{ old('default_user_prompt', $settings->default_user_prompt) }}</textarea>
                            <small class="text-muted">@lang('Pre-filled into the Additional Instructions box on the generate form. Admins can override it per generation.')</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ------------------------------------------- defaults --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">@lang('Generation Defaults')</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">@lang('Default Language')</label>
                            <select name="default_language" class="form-control">
                                @foreach (\App\Models\AiGenerationSetting::LANGUAGES as $value => $label)
                                    <option value="{{ $value }}" @selected($settings->default_language == $value)>@lang($label)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">@lang('Default Difficulty')</label>
                            <select name="default_difficulty" class="form-control">
                                @foreach (\App\Models\AiGenerationSetting::DIFFICULTIES as $value => $label)
                                    <option value="{{ $value }}" @selected($settings->default_difficulty == $value)>@lang($label)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">@lang('Default Question Type')</label>
                            <select name="default_question_type" class="form-control">
                                @foreach (\App\Models\AiGenerationSetting::QUESTION_TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected($settings->default_question_type == $value)>@lang($label)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">@lang('Default Number of Questions')</label>
                            <input type="number" min="1" name="default_quantity" class="form-control"
                                   value="{{ old('default_quantity', $settings->default_quantity) }}" required>
                        </div>
                        <div class="form-group mt-3">
                            <label class="form-label">@lang('Maximum Per Generation')</label>
                            <input type="number" min="1" max="500" name="max_quantity" class="form-control"
                                   value="{{ old('max_quantity', $settings->max_quantity) }}" required>
                            <small class="text-muted">@lang('Large batches risk hitting the model\'s token limit. 100 or fewer is safest.')</small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="submit" class="btn btn--primary w-100">
                            <i class="las la-save"></i> @lang('Save Settings')
                        </button>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-body small">
                        <h6>@lang('Difficulty mapping')</h6>
                        <p class="text-muted mb-0">
                            @lang('The Question Bank stores Easy, Medium and Hard only. Questions generated as')
                            <strong>@lang('Expert')</strong>
                            @lang('stay Expert through review and are saved as Hard on import.')
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.ai-settings.clear.key') }}" id="clearKeyForm" class="d-none">
        @csrf
        <input type="hidden" name="provider" id="clearKeyProvider">
    </form>
@endsection

@push('script')
    <script>
        (function () {
            "use strict";

            const hints = {
                gemini:    "e.g. gemini-flash-latest, gemini-3.5-flash",
                openai:    "e.g. gpt-4o, gpt-4o-mini",
                anthropic: "e.g. claude-sonnet-4-5, claude-opus-4-1"
            };

            const providerSelect = document.getElementById('providerSelect');
            const modelHint = document.getElementById('modelHint');

            const updateHint = () => {
                modelHint.textContent = hints[providerSelect.value] || '';
            };
            providerSelect.addEventListener('change', updateHint);
            updateHint();

            // Connectivity test. The response carries only a pass/fail message,
            // never any key material.
            document.querySelectorAll('.testBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const provider = this.dataset.provider;
                    const box = document.getElementById('testResult');
                    const original = this.innerHTML;

                    this.disabled = true;
                    this.innerHTML = '<i class="las la-spinner la-spin"></i>';
                    box.innerHTML = '';

                    fetch("{{ route('admin.ai-settings.test') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ provider: provider })
                    })
                    .then(res => res.json().then(body => ({ ok: res.ok, body })))
                    .then(({ ok, body }) => {
                        box.innerHTML = '<div class="alert alert-' + (ok ? 'success' : 'danger') + ' py-2 mb-0">'
                            + '<strong>' + provider + ':</strong> ' + body.message + '</div>';
                    })
                    .catch(err => {
                        box.innerHTML = '<div class="alert alert-danger py-2 mb-0">' + err.message + '</div>';
                    })
                    .finally(() => {
                        this.disabled = false;
                        this.innerHTML = original;
                    });
                });
            });

            document.querySelectorAll('.clearKeyBtn').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (!confirm("@lang('Clear the stored API key for this provider?')")) return;
                    document.getElementById('clearKeyProvider').value = this.dataset.provider;
                    document.getElementById('clearKeyForm').submit();
                });
            });
        })();
    </script>
@endpush
