@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Constants\SocialStatus as S;
    use App\Services\Social\SocialPermission;
    $can = SocialPermission::current();
@endphp

@section('panel')

{{--
    Background processing is the thing most likely to be misconfigured on a new
    install, so its health is stated plainly rather than left to be discovered
    when a scheduled post silently never goes out.
--}}
@if(!$cronHealthy)
    <div class="alert alert-warning">
        <h6 class="mb-1"><i class="las la-exclamation-triangle"></i> @lang('Background processing looks inactive')</h6>
        <p class="mb-2 small">
            @lang('Scheduled posts publish through a background worker. The last cron run was')
            <strong>{{ $lastCron ? diffForHumans($lastCron) : __('never') }}</strong>.
            @lang('Until cron runs, nothing will publish on its own.')
        </p>
        <p class="mb-0 small">
            @lang('Set up either of these on the server:')
        </p>
        <pre class="mb-0 mt-2 small">* * * * * cd {{ base_path() }} && php artisan social:work --seconds=50 >> /dev/null 2>&1
* * * * * curl -s {{ url('/cron') }} > /dev/null</pre>
    </div>
@endif

<div class="social-kpi-grid mb-4">
    @include('admin.social.partials.kpi', ['label' => 'Waiting',    'value' => $stats['queued'],     'icon' => 'las la-hourglass-half', 'colour' => '#17a2b8'])
    @include('admin.social.partials.kpi', ['label' => 'Due now',    'value' => $stats['due'],        'icon' => 'las la-bolt',           'colour' => '#ffc107'])
    @include('admin.social.partials.kpi', ['label' => 'Processing', 'value' => $stats['processing'], 'icon' => 'las la-sync',           'colour' => '#0d6efd'])
    @include('admin.social.partials.kpi', ['label' => 'Completed',  'value' => $stats['completed'],  'icon' => 'las la-check',          'colour' => '#28a745'])
    @include('admin.social.partials.kpi', ['label' => 'Failed',     'value' => $stats['failed'],     'icon' => 'las la-times',          'colour' => '#dc3545'])
    @include('admin.social.partials.kpi', ['label' => 'Stuck',      'value' => $stats['stuck'],      'icon' => 'las la-unlink',         'colour' => '#6c757d', 'hint' => __('Lease expired')])
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">@lang('Jobs')</h6>

        <div class="d-flex gap-2">
            <form class="d-flex gap-2" method="GET">
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">@lang('All statuses')</option>
                    @foreach([S::JOB_QUEUED, S::JOB_PROCESSING, S::JOB_COMPLETED, S::JOB_FAILED, S::JOB_CANCELLED] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                    @endforeach
                </select>
                <select name="platform" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">@lang('All platforms')</option>
                    @foreach(S::PLATFORMS as $key => $label)
                        <option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>

            @if($can[SocialPermission::PUBLISH] ?? false)
                @if($stats['stuck'] > 0)
                    <form action="{{ route('admin.social.queue.release.stuck') }}" method="POST">
                        @csrf
                        <button class="btn btn-sm btn-outline--warning"><i class="las la-unlock"></i> @lang('Release stuck')</button>
                    </form>
                @endif
                <form action="{{ route('admin.social.queue.run') }}" method="POST">
                    @csrf
                    <button class="btn btn-sm btn--primary"><i class="las la-play"></i> @lang('Process now')</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Job')</th>
                        <th>@lang('Post')</th>
                        <th>@lang('Platform')</th>
                        <th>@lang('Status')</th>
                        <th>@lang('Attempts')</th>
                        <th>@lang('Runs at')</th>
                        <th>@lang('Last error')</th>
                        <th>@lang('Action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                        <tr>
                            <td>
                                <code>#{{ $job->id }}</code>
                                <small class="d-block text-muted">{{ $job->type }}</small>
                            </td>
                            <td>
                                @if($job->post)
                                    <a href="{{ route('admin.social.posts.show', $job->social_post_id) }}">
                                        {{ strLimit($job->post->title ?: strip_tags($job->post->caption), 40) }}
                                    </a>
                                @else
                                    <span class="text-muted">@lang('Deleted')</span>
                                @endif
                            </td>
                            <td>
                                @if($job->platform)
                                    <i class="{{ S::platformIcon($job->platform) }}" style="color: {{ S::platformColor($job->platform) }}"></i>
                                    {{ S::platformName($job->platform) }}
                                    @if($job->account)
                                        <small class="d-block text-muted">{{ $job->account->display_name }}</small>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="badge badge--{{ $job->status_class }}">{{ ucfirst($job->status) }}</span></td>
                            <td>
                                {{ $job->attempts }} / {{ $job->max_attempts }}
                                @if($job->target && $job->target->next_attempt_at)
                                    <small class="d-block text-muted">
                                        @lang('next'): {{ showDateTime($job->target->next_attempt_at, 'd M, h:i A') }}
                                    </small>
                                @endif
                            </td>
                            <td>{{ $job->available_at ? showDateTime($job->available_at, 'd M Y, h:i A') : '—' }}</td>
                            <td>
                                @if($job->last_error)
                                    <small class="text--danger">{{ strLimit($job->last_error, 90) }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    @if(($can[SocialPermission::RETRY] ?? false) && $job->status === S::JOB_FAILED && $job->target)
                                        <form action="{{ route('admin.social.queue.retry', $job->id) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-sm btn-outline--warning" title="@lang('Retry')">
                                                <i class="las la-redo"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if(($can[SocialPermission::SCHEDULE] ?? false) && $job->status === S::JOB_QUEUED)
                                        <button type="button" class="btn btn-sm btn-outline--danger confirmationBtn"
                                                data-action="{{ route('admin.social.queue.cancel', $job->id) }}"
                                                data-question="@lang('Cancel this queued job?')" title="@lang('Cancel')">
                                            <i class="las la-ban"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                @include('admin.social.partials.empty', [
                                    'icon' => 'las la-stream',
                                    'title' => 'The queue is empty',
                                    'message' => 'Jobs appear here as soon as a post is scheduled or published.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($jobs->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($jobs) }}</div>
    @endif
</div>

<x-confirmation-modal />

@endsection
