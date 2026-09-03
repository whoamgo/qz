@extends('admin.layouts.app')
@include('admin.social.partials.head')

@section('panel')

<div class="row g-4">
    <div class="col-lg-8">
        <form action="{{ route('admin.social.settings.update') }}" method="POST">
            @csrf

            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">@lang('General')</h6></div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1" id="setEnabled" @checked($settings->enabled)>
                        <label class="form-check-label" for="setEnabled">
                            <strong>@lang('Social Media Center enabled')</strong>
                            <small class="d-block text-muted">@lang('Switching this off hides the section from everyone except the Super Admin, and stops the background publisher.')</small>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="require_approval" value="1" id="setApproval" @checked($settings->require_approval)>
                        <label class="form-check-label" for="setApproval">
                            <strong>@lang('Require approval before publishing')</strong>
                            <small class="d-block text-muted">@lang('Posts must be approved by an Admin or Super Admin before they can be scheduled or published. Prevents accidental publishing.')</small>
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="auto_draft_from_quiz" value="1" id="setAutoDraft" @checked($settings->auto_draft_from_quiz)>
                        <label class="form-check-label" for="setAutoDraft">
                            <strong>@lang('Create a social draft when a new quiz is published')</strong>
                            <small class="d-block text-muted">@lang('Creates a draft only - it is never published without a person.')</small>
                        </label>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">@lang('Default timezone')</label>
                            <input type="text" name="default_timezone" class="form-control"
                                   value="{{ $settings->default_timezone }}" placeholder="{{ config('app.timezone') }}">
                            <small class="social-counter">@lang('Scheduling times in the panel are read in this timezone.')</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">@lang('UTM tracking')</h6></div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="utm_enabled" value="1" id="setUtm" @checked($settings->utm_enabled)>
                        <label class="form-check-label" for="setUtm">
                            <strong>@lang('Add UTM parameters to Quiz Mitra links')</strong>
                            <small class="d-block text-muted">@lang('Only links pointing at this site are tagged - external URLs are left untouched.')</small>
                        </label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">@lang('utm_medium')</label>
                            <input type="text" name="utm_medium" class="form-control" value="{{ $settings->utm_medium }}">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">@lang('utm_source overrides')</label>
                            <input type="text" name="utm_source_map" class="form-control"
                                   value="{{ $settings->utm_source_map }}" placeholder="x:twitter, threads:threads_app">
                            <small class="social-counter">@lang('Comma separated platform:source pairs. Defaults to the platform key.')</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">@lang('AI content assistant')</h6></div>
                <div class="card-body">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="ai_enabled" value="1" id="setAi" @checked($settings->ai_enabled)>
                        <label class="form-check-label" for="setAi">
                            <strong>@lang('Enable the AI content assistant')</strong>
                            <small class="d-block text-muted">
                                @lang('Uses the provider configured under Quiz Manager → AI Settings. Generated copy always lands in editable fields.')
                            </small>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="ai_auto_publish" value="1" id="setAiPublish" @checked($settings->ai_auto_publish)>
                        <label class="form-check-label" for="setAiPublish">
                            <strong>@lang('Allow automations to publish AI-written copy unattended')</strong>
                            <small class="d-block text--danger">
                                @lang('With this off (recommended), AI-written content always reaches a person before it reaches an audience.')
                            </small>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">@lang('Publishing engine')</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">@lang('Max attempts per platform')</label>
                            <input type="number" name="max_attempts" class="form-control" min="1" max="10" value="{{ $settings->max_attempts }}">
                            <small class="social-counter">@lang('Only transient failures are retried.')</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">@lang('Retry base delay (seconds)')</label>
                            <input type="number" name="retry_base_seconds" class="form-control" min="10" max="3600" value="{{ $settings->retry_base_seconds }}">
                            <small class="social-counter">@lang('Doubles each attempt, with jitter.')</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">@lang('API timeout (seconds)')</label>
                            <input type="number" name="request_timeout" class="form-control" min="15" max="600" value="{{ $settings->request_timeout }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">@lang('Job lease (seconds)')</label>
                            <input type="number" name="job_lease_seconds" class="form-control" min="60" max="3600" value="{{ $settings->job_lease_seconds }}">
                            <small class="social-counter">@lang('How long before a job held by a crashed worker is picked up again.')</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">@lang('Schedule grace (minutes)')</label>
                            <input type="number" name="schedule_grace_minutes" class="form-control" min="5" max="1440" value="{{ $settings->schedule_grace_minutes }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">@lang('Upload limits')</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">@lang('Max image size (MB)')</label>
                            <input type="number" name="max_image_mb" class="form-control" min="1" max="100" value="{{ $settings->max_image_mb }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">@lang('Max video size (MB)')</label>
                            <input type="number" name="max_video_mb" class="form-control" min="1" max="2048" value="{{ $settings->max_video_mb }}">
                            <small class="social-counter">
                                @lang('The server also enforces upload_max_filesize and post_max_size - currently')
                                {{ ini_get('upload_max_filesize') }} / {{ ini_get('post_max_size') }}.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <button class="btn btn--primary"><i class="las la-save"></i> @lang('Save settings')</button>
        </form>
    </div>

    {{-- =========================================================== Sidebar --}}
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">@lang('Environment check')</h6></div>
            <div class="card-body">
                @php
                    $checks = [
                        ['ok' => $environment['https'],       'label' => __('Site served over HTTPS'),   'hint' => __('Instagram and Threads refuse to fetch media over plain HTTP.')],
                        ['ok' => $environment['public_host'], 'label' => __('Publicly reachable APP_URL'), 'hint' => __('Platforms that fetch media by URL cannot reach a localhost address.')],
                        ['ok' => $environment['ffprobe'],     'label' => __('ffprobe installed'),        'hint' => __('Without it, video duration and dimensions cannot be checked before publishing.')],
                        ['ok' => (bool) $environment['last_cron'], 'label' => __('Background cron has run'), 'hint' => __('Scheduled posts only publish when cron runs.')],
                    ];
                @endphp

                @foreach($checks as $check)
                    <div class="d-flex gap-2 align-items-start mb-3">
                        <i class="las la-{{ $check['ok'] ? 'check-circle text--success' : 'exclamation-triangle text--warning' }}" style="font-size:20px"></i>
                        <div>
                            <div><strong>{{ $check['label'] }}</strong></div>
                            @unless($check['ok'])
                                <small class="text-muted">{{ $check['hint'] }}</small>
                            @endunless
                        </div>
                    </div>
                @endforeach

                <table class="table table-sm mb-0">
                    <tr><td>@lang('APP_URL')</td><td class="text-end"><code class="small">{{ $environment['app_url'] }}</code></td></tr>
                    <tr><td>@lang('Timezone')</td><td class="text-end">{{ $environment['timezone'] }}</td></tr>
                    <tr><td>@lang('Last cron')</td><td class="text-end">{{ $environment['last_cron'] ? diffForHumans($environment['last_cron']) : __('never') }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">@lang('Platform credentials')</h6></div>
            <div class="card-body">
                <p class="social-counter">
                    @lang('Credentials are read from the server environment and are never stored in the database or shown in the panel. This is presence only.')
                </p>

                @foreach($credentials as $credential)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="{{ $credential['icon'] }}" style="font-size:18px"></i>
                        <span class="flex-grow-1">{{ $credential['name'] }}</span>
                        @if($credential['configured'])
                            <span class="badge badge--success">@lang('Configured')</span>
                        @elseif($credential['auth'] === 'token')
                            <span class="badge badge--info">@lang('Enter on Accounts')</span>
                        @else
                            <span class="badge badge--secondary">@lang('Missing')</span>
                        @endif
                    </div>
                @endforeach

                <hr>
                <p class="social-counter mb-1">@lang('OAuth redirect URIs to register:')</p>
                @foreach($credentials->where('auth', '!=', 'token') as $credential)
                    <code class="small d-block mb-1" style="word-break:break-all">{{ $credential['redirect'] }}</code>
                @endforeach
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">@lang('Roles')</h6></div>
            <div class="card-body">
                <p class="social-counter">@lang('Control who can draft, schedule, publish, approve and connect accounts.')</p>
                <a href="{{ route('admin.social.settings.roles') }}" class="btn btn-outline--primary btn-sm w-100">
                    <i class="las la-user-shield"></i> @lang('Manage roles & permissions')
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
