@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Constants\SocialStatus;
    use App\Services\Social\SocialPermission;
    $can = SocialPermission::current();
@endphp

@section('panel')

    {{-- Anything that needs attention is said once, at the top, with the fix. --}}
    @if($counts['accounts_bad'] > 0)
        <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="las la-exclamation-triangle"></i>
                <strong>{{ $counts['accounts_bad'] }}</strong>
                @lang('account(s) need attention - an expired token or a missing permission. Scheduled posts for them will fail until they are reconnected.')
            </div>
            <a href="{{ route('admin.social.accounts.index') }}" class="btn btn-sm btn--warning">@lang('Review accounts')</a>
        </div>
    @endif

    @if($pendingApproval > 0 && ($can[SocialPermission::APPROVE] ?? false))
        <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div><i class="las la-user-check"></i> <strong>{{ $pendingApproval }}</strong> @lang('post(s) are waiting for your approval.')</div>
            <a href="{{ route('admin.social.posts.index', ['status' => SocialStatus::PENDING_APPROVAL]) }}" class="btn btn-sm btn--info">@lang('Review')</a>
        </div>
    @endif

    {{-- ------------------------------------------------------ Quick actions --}}
    <div class="d-flex flex-wrap gap-2 mb-4">
        @if($can[SocialPermission::CREATE] ?? false)
            <a href="{{ route('admin.social.posts.create') }}" class="btn btn--primary"><i class="las la-plus"></i> @lang('Create Social Post')</a>
            <a href="{{ route('admin.social.video.index') }}" class="btn btn-outline--primary"><i class="las la-video"></i> @lang('Upload Video')</a>
        @endif
        @if($can[SocialPermission::SCHEDULE] ?? false)
            <a href="{{ route('admin.social.calendar.index') }}" class="btn btn-outline--primary"><i class="las la-calendar"></i> @lang('Calendar')</a>
            <a href="{{ route('admin.social.bulk.index') }}" class="btn btn-outline--primary"><i class="las la-layer-group"></i> @lang('Bulk Scheduler')</a>
        @endif
        @if($can[SocialPermission::MANAGE_ACCOUNTS] ?? false)
            <a href="{{ route('admin.social.accounts.index') }}" class="btn btn-outline--primary"><i class="las la-link"></i> @lang('Connect Account')</a>
        @endif
        @if($can[SocialPermission::MANAGE_LIBRARY] ?? false)
            <a href="{{ route('admin.social.campaigns.index') }}" class="btn btn-outline--primary"><i class="las la-bullhorn"></i> @lang('Create Campaign')</a>
        @endif
    </div>

    {{-- ---------------------------------------------------------- KPI cards --}}
    <div class="social-kpi-grid mb-4">
        @include('admin.social.partials.kpi', ['label' => 'Published today', 'value' => $counts['published_today'], 'icon' => 'las la-paper-plane', 'colour' => '#28a745'])
        @include('admin.social.partials.kpi', ['label' => 'Scheduled',       'value' => $counts['scheduled'],       'icon' => 'las la-clock',       'colour' => '#ffc107'])
        @include('admin.social.partials.kpi', ['label' => 'Drafts',          'value' => $counts['drafts'],          'icon' => 'las la-edit',        'colour' => '#17a2b8'])
        @include('admin.social.partials.kpi', ['label' => 'Failed',          'value' => $counts['failed'],          'icon' => 'las la-times-circle','colour' => '#dc3545'])
        @include('admin.social.partials.kpi', ['label' => 'Connected',       'value' => $counts['accounts_ok'],     'icon' => 'las la-plug',        'colour' => '#5b6ef5', 'hint' => $counts['accounts_bad'] . ' ' . __('need attention')])
        @include('admin.social.partials.kpi', ['label' => 'Followers',       'value' => $summary['followers'],      'icon' => 'las la-users',       'colour' => '#6f42c1'])
    </div>

    {{-- ------------------------------------------------- 30-day performance --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">@lang('Performance') <small class="text-muted">@lang('last 30 days')</small></h5>
            <a href="{{ route('admin.social.analytics.index') }}" class="btn btn-sm btn-outline--primary">@lang('Full analytics')</a>
        </div>
        <div class="card-body">
            <div class="social-kpi-grid mb-4">
                @include('admin.social.partials.kpi', ['label' => 'Reach',       'value' => $summary['reach'],       'icon' => 'las la-bullseye',      'colour' => '#0d6efd'])
                @include('admin.social.partials.kpi', ['label' => 'Views',       'value' => $summary['views'],       'icon' => 'las la-eye',           'colour' => '#20c997'])
                @include('admin.social.partials.kpi', ['label' => 'Likes',       'value' => $summary['likes'],       'icon' => 'las la-heart',         'colour' => '#e1306c'])
                @include('admin.social.partials.kpi', ['label' => 'Comments',    'value' => $summary['comments'],    'icon' => 'las la-comment',       'colour' => '#fd7e14'])
                @include('admin.social.partials.kpi', ['label' => 'Shares',      'value' => $summary['shares'],      'icon' => 'las la-share',         'colour' => '#6610f2'])
                @include('admin.social.partials.kpi', ['label' => 'Clicks',      'value' => $summary['clicks'],      'icon' => 'las la-mouse-pointer', 'colour' => '#198754'])
                @include('admin.social.partials.kpi', [
                    'label'  => 'Engagement rate',
                    // Null means no reach data came back, which is different
                    // from a genuine zero - say so rather than printing "0%".
                    'value'  => $summary['engagement_rate'] !== null ? $summary['engagement_rate'] . '%' : '—',
                    'icon'   => 'las la-percentage',
                    'colour' => '#0dcaf0',
                    'hint'   => $summary['engagement_rate'] === null ? __('No reach data yet') : null,
                ])
                @include('admin.social.partials.kpi', ['label' => 'Posts published', 'value' => $summary['posts'], 'icon' => 'las la-stream', 'colour' => '#6c757d'])
            </div>

            <div id="socialTrend"></div>
        </div>
    </div>

    {{-- ------------------------------------------------------ Platform cards --}}
    <h5 class="mb-3">@lang('Platforms')</h5>
    <div class="social-platform-grid mb-4">
        @foreach($platformCards as $card)
            <div class="social-platform">
                <div class="social-platform__head">
                    @if($card['account'] && $card['account']->avatar_url)
                        <img src="{{ $card['account']->avatar_url }}" class="social-avatar" alt="{{ $card['name'] }}">
                    @else
                        <div class="social-platform__icon" style="background: {{ $card['color'] }}">
                            <i class="{{ $card['icon'] }}"></i>
                        </div>
                    @endif
                    <div class="flex-grow-1 min-w-0">
                        <div class="social-platform__name">{{ $card['name'] }}</div>
                        <div class="social-platform__meta text-truncate">
                            @if($card['account'])
                                {{ $card['account']->display_name }}
                            @elseif(!$card['configured'])
                                @lang('Not configured')
                            @else
                                @lang('No account connected')
                            @endif
                        </div>
                    </div>
                </div>

                <div class="social-platform__body">
                    @if($card['account'])
                        <div class="mb-2">
                            <span class="badge badge--{{ $card['account']->status_class }}">{{ $card['account']->status_name }}</span>
                            @if($card['total'] > 1)
                                <span class="social-chip social-chip--muted">+{{ $card['total'] - 1 }} @lang('more')</span>
                            @endif
                        </div>
                        <div class="social-platform__stats">
                            <div class="social-platform__stat">
                                <b>{{ number_format($card['account']->followers) }}</b>
                                <span>@lang('Followers')</span>
                            </div>
                            <div class="social-platform__stat">
                                <b>{{ number_format($card['performance']['posts'] ?? 0) }}</b>
                                <span>@lang('Posts 30d')</span>
                            </div>
                            <div class="social-platform__stat">
                                <b>{{ $card['performance']['engagement_rate'] !== null ? ($card['performance']['engagement_rate'] ?? 0) . '%' : '—' }}</b>
                                <span>@lang('Engagement')</span>
                            </div>
                        </div>
                        <div class="social-platform__meta mt-2">
                            @lang('Last sync'):
                            {{ $card['account']->last_sync_at ? diffForHumans($card['account']->last_sync_at) : __('never') }}
                        </div>
                    @elseif(!$card['configured'])
                        <p class="social-platform__meta mb-0">
                            @lang('Add the API credentials for this platform to the server environment, then connect an account.')
                        </p>
                    @else
                        <p class="social-platform__meta mb-0">
                            @lang('Credentials are configured. Connect an account to start publishing.')
                        </p>
                    @endif
                </div>

                <div class="social-platform__foot">
                    @if($card['account'])
                        <a href="{{ route('admin.social.posts.index', ['platform' => $card['key']]) }}" class="btn btn-sm btn-outline--primary">@lang('Posts')</a>
                    @endif
                    @if($can[SocialPermission::MANAGE_ACCOUNTS] ?? false)
                        <a href="{{ route('admin.social.accounts.index') }}#platform-{{ $card['key'] }}" class="btn btn-sm btn-outline--dark">
                            {{ $card['account'] ? __('Manage') : __('Connect') }}
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        {{-- -------------------------------------------------- Publishing queue --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">@lang('Publishing Queue')</h6>
                    <a href="{{ route('admin.social.queue.index') }}" class="btn btn-sm btn-outline--primary">@lang('Open')</a>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush" id="queueStats">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>@lang('Waiting')</span><b data-queue="queued">{{ $queue['queued'] }}</b>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>@lang('Due now')</span><b data-queue="due">{{ $queue['due'] }}</b>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>@lang('Processing')</span><b data-queue="processing">{{ $queue['processing'] }}</b>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span>@lang('Failed')</span><b class="text--danger" data-queue="failed">{{ $queue['failed'] }}</b>
                        </li>
                    </ul>

                    @if($queue['stuck'] > 0)
                        <div class="alert alert-warning mt-3 mb-0 py-2 px-3 small">
                            {{ $queue['stuck'] }} @lang('job(s) were left mid-run by a worker that stopped. They are picked up automatically on the next run.')
                        </div>
                    @endif

                    @if($can[SocialPermission::PUBLISH] ?? false)
                        <form action="{{ route('admin.social.dashboard.process.queue') }}" method="POST" class="mt-3">
                            @csrf
                            <button class="btn btn--primary btn-sm w-100" type="submit">
                                <i class="las la-play"></i> @lang('Process queue now')
                            </button>
                        </form>
                        <p class="social-counter mt-2 mb-0">
                            @lang('Scheduled posts publish on their own through cron. This is for when you do not want to wait.')
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- ------------------------------------------------------------ Upcoming --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">@lang('Coming up')</h6>
                    <a href="{{ route('admin.social.calendar.index') }}" class="btn btn-sm btn-outline--primary">@lang('Calendar')</a>
                </div>
                <div class="card-body p-0">
                    @forelse($upcoming as $post)
                        <a href="{{ route('admin.social.posts.show', $post->id) }}" class="d-flex align-items-center gap-2 p-3 border-bottom text-decoration-none">
                            <div class="flex-grow-1 min-w-0">
                                <div class="text-truncate text--dark">{{ $post->title ?: strLimit(strip_tags($post->caption), 40) }}</div>
                                <small class="text-muted">{{ showDateTime($post->scheduled_at, 'd M, h:i A') }}</small>
                            </div>
                            @include('admin.social.partials.platform-chips', ['targets' => $post->targets])
                        </a>
                    @empty
                        @include('admin.social.partials.empty', [
                            'icon' => 'las la-calendar-check',
                            'title' => 'Nothing scheduled',
                            'message' => 'Create a post and pick a publish time to fill the calendar.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ---------------------------------------------------- Recent failures --}}
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">@lang('Recent failures')</h6>
                    <a href="{{ route('admin.social.failed.index') }}" class="btn btn-sm btn-outline--danger">@lang('All failures')</a>
                </div>
                <div class="card-body p-0">
                    @forelse($recentFailures as $failure)
                        <div class="p-3 border-bottom">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="{{ SocialStatus::platformIcon($failure->platform) }}" style="color: {{ SocialStatus::platformColor($failure->platform) }}"></i>
                                <a href="{{ $failure->post ? route('admin.social.posts.show', $failure->social_post_id) : '#' }}" class="text-truncate text--dark">
                                    {{ $failure->post?->title ?: __('Post') . ' #' . $failure->social_post_id }}
                                </a>
                            </div>
                            <small class="text--danger d-block">{{ strLimit($failure->error_message, 110) }}</small>
                            <small class="text-muted">{{ $failure->last_attempt_at ? diffForHumans($failure->last_attempt_at) : '' }}</small>
                        </div>
                    @empty
                        @include('admin.social.partials.empty', [
                            'icon' => 'las la-check-circle',
                            'title' => 'No failures',
                            'message' => 'Every publish attempt so far has succeeded.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- --------------------------------------------------------- Recent posts --}}
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">@lang('Recent posts')</h6>
            <a href="{{ route('admin.social.posts.index') }}" class="btn btn-sm btn-outline--primary">@lang('All posts')</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table--light style--two mb-0">
                    <thead>
                        <tr>
                            <th>@lang('Post')</th>
                            <th>@lang('Platforms')</th>
                            <th>@lang('Status')</th>
                            <th>@lang('When')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentPosts as $post)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($post->previewImage())
                                            <img src="{{ $post->previewImage() }}" alt="" style="width:38px;height:38px;object-fit:cover;border-radius:6px;">
                                        @endif
                                        <a href="{{ route('admin.social.posts.show', $post->id) }}">
                                            {{ $post->title ?: strLimit(strip_tags($post->caption), 50) }}
                                        </a>
                                    </div>
                                </td>
                                <td>@include('admin.social.partials.platform-chips', ['targets' => $post->targets])</td>
                                <td><span class="badge badge--{{ $post->status_class }}">{{ $post->status_name }}</span></td>
                                <td>{{ showDateTime($post->published_at ?: ($post->scheduled_at ?: $post->created_at), 'd M Y, h:i A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    @include('admin.social.partials.empty', [
                                        'icon' => 'las la-share-alt',
                                        'title' => 'No posts yet',
                                        'message' => 'Create your first post to start publishing across your platforms.',
                                        'actionUrl' => route('admin.social.posts.create'),
                                        'actionLabel' => 'Create Social Post',
                                    ])
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('script-lib')
    <script src="{{ asset('assets/admin/js/vendor/apexcharts.min.js') }}"></script>
@endpush

@push('script')
<script>
    "use strict";
    (function ($) {
        var timeline = @json($timeline);
        var target   = document.querySelector('#socialTrend');

        if (target && window.ApexCharts) {
            new ApexCharts(target, {
                chart:   { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'inherit' },
                colors:  ['#5b6ef5', '#e1306c', '#20c997'],
                stroke:  { curve: 'smooth', width: 2 },
                fill:    { type: 'gradient', gradient: { opacityFrom: .25, opacityTo: 0 } },
                dataLabels: { enabled: false },
                xaxis:   { categories: timeline.labels, tickAmount: 10 },
                yaxis:   { labels: { formatter: function (v) { return Math.round(v); } } },
                legend:  { position: 'top', horizontalAlign: 'right' },
                tooltip: { shared: true, intersect: false },
                series: [
                    { name: '{{ __('Reach') }}',       data: timeline.series.reach },
                    { name: '{{ __('Engagements') }}', data: timeline.series.engagements },
                    { name: '{{ __('Posts') }}',       data: timeline.series.posts }
                ]
            }).render();
        }

        // Keep the queue widget honest while a run is in progress, without
        // reloading the whole dashboard under the admin. Capped so an idle tab
        // does not poll the server all day.
        var polls = 0;
        var poll  = setInterval(function () {
            if (++polls > 20) { clearInterval(poll); return; }

            $.get('{{ route('admin.social.dashboard.queue.status') }}', function (data) {
                $('#queueStats [data-queue]').each(function () {
                    var key = $(this).data('queue');
                    $(this).text(data.queue[key] != null ? data.queue[key] : 0);
                });
            });
        }, 15000);
    })(jQuery);
</script>
@endpush
