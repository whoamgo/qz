@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php use App\Constants\SocialStatus as S; @endphp

@section('panel')

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label">@lang('Range')</label>
                <select name="range" class="form-control" id="rangeSelect">
                    @foreach(App\Http\Controllers\Admin\Social\AnalyticsController::RANGES as $key => $label)
                        <option value="{{ $key }}" @selected($range === $key)>@lang($label)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 custom-range {{ $range === 'custom' ? '' : 'd-none' }}">
                <label class="form-label">@lang('From')</label>
                <input type="date" name="from" class="form-control" value="{{ request('from', $from->toDateString()) }}">
            </div>
            <div class="col-md-3 custom-range {{ $range === 'custom' ? '' : 'd-none' }}">
                <label class="form-label">@lang('To')</label>
                <input type="date" name="to" class="form-control" value="{{ request('to', $to->toDateString()) }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn--primary flex-grow-1">@lang('Apply')</button>
                <a href="{{ route('admin.social.analytics.top') }}" class="btn btn-outline--primary">@lang('Top content')</a>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <small class="social-counter">
                @lang('Showing'): {{ $from->format('d M Y') }} — {{ $to->format('d M Y') }}.
                @lang('Last sync'): {{ $lastSync ? diffForHumans($lastSync) : __('never') }}.
            </small>
            <form action="{{ route('admin.social.analytics.sync') }}" method="POST">
                @csrf
                <button class="btn btn-sm btn-outline--primary"><i class="las la-sync"></i> @lang('Sync now')</button>
            </form>
        </div>
    </div>
</div>

<div class="social-kpi-grid mb-4">
    @include('admin.social.partials.kpi', ['label' => 'Followers',   'value' => $summary['followers'],  'icon' => 'las la-users',    'colour' => '#6f42c1'])
    @include('admin.social.partials.kpi', ['label' => 'Reach',       'value' => $summary['reach'],      'icon' => 'las la-bullseye', 'colour' => '#0d6efd'])
    @include('admin.social.partials.kpi', ['label' => 'Views',       'value' => $summary['views'],      'icon' => 'las la-eye',      'colour' => '#20c997'])
    @include('admin.social.partials.kpi', ['label' => 'Likes',       'value' => $summary['likes'],      'icon' => 'las la-heart',    'colour' => '#e1306c'])
    @include('admin.social.partials.kpi', ['label' => 'Comments',    'value' => $summary['comments'],   'icon' => 'las la-comment',  'colour' => '#fd7e14'])
    @include('admin.social.partials.kpi', ['label' => 'Shares',      'value' => $summary['shares'],     'icon' => 'las la-share',    'colour' => '#6610f2'])
    @include('admin.social.partials.kpi', ['label' => 'Saves',       'value' => $summary['saves'],      'icon' => 'las la-bookmark', 'colour' => '#0dcaf0'])
    @include('admin.social.partials.kpi', ['label' => 'Clicks',      'value' => $summary['clicks'],     'icon' => 'las la-mouse-pointer', 'colour' => '#198754'])
    @include('admin.social.partials.kpi', [
        'label'  => 'Engagement rate',
        'value'  => $summary['engagement_rate'] !== null ? $summary['engagement_rate'] . '%' : '—',
        'icon'   => 'las la-percentage',
        'colour' => '#dc3545',
        'hint'   => $summary['engagement_rate'] === null ? __('No reach data') : null,
    ])
    @include('admin.social.partials.kpi', ['label' => 'Posts published', 'value' => $summary['posts'], 'icon' => 'las la-stream', 'colour' => '#6c757d'])
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">@lang('Trend')</h6></div>
            <div class="card-body"><div id="trendChart"></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><h6 class="mb-0">@lang('Followers by platform')</h6></div>
            <div class="card-body"><div id="followerChart"></div></div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h6 class="mb-0">@lang('Platform comparison')</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Platform')</th>
                        <th>@lang('Followers')</th>
                        <th>@lang('Posts')</th>
                        <th>@lang('Reach')</th>
                        <th>@lang('Views')</th>
                        <th>@lang('Likes')</th>
                        <th>@lang('Comments')</th>
                        <th>@lang('Shares')</th>
                        <th>@lang('Clicks')</th>
                        <th>@lang('Engagement')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($byPlatform as $key => $row)
                        <tr>
                            <td>
                                <i class="{{ $row['icon'] }}" style="color: {{ $row['color'] }}"></i>
                                {{ $row['name'] }}
                                @if($noMetrics->contains($key))
                                    <span class="social-chip social-chip--muted" title="@lang('This platform\'s API exposes no post metrics.')">
                                        @lang('no metrics API')
                                    </span>
                                @endif
                            </td>
                            <td>{{ number_format($row['followers']) }}</td>
                            <td>{{ number_format($row['posts']) }}</td>
                            <td>{{ number_format($row['reach']) }}</td>
                            <td>{{ number_format($row['views']) }}</td>
                            <td>{{ number_format($row['likes']) }}</td>
                            <td>{{ number_format($row['comments']) }}</td>
                            <td>{{ number_format($row['shares']) }}</td>
                            <td>{{ number_format($row['clicks']) }}</td>
                            <td>{{ $row['engagement_rate'] !== null ? $row['engagement_rate'] . '%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($noMetrics->count())
    <div class="social-note mb-4">
        <strong>@lang('A note on missing numbers.')</strong>
        @lang('These platforms do not expose post-level metrics through their official API, so nothing is reported for them rather than an invented zero:')
        {{ $noMetrics->map(fn ($p) => S::platformName($p))->implode(', ') }}.
    </div>
@endif

<div class="card">
    <div class="card-header"><h6 class="mb-0">@lang('Connected accounts')</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Account')</th>
                        <th>@lang('Platform')</th>
                        <th>@lang('Followers')</th>
                        <th>@lang('Posts')</th>
                        <th>@lang('Last sync')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($account->avatar_url)
                                        <img src="{{ $account->avatar_url }}" class="social-avatar" style="width:30px;height:30px" alt="">
                                    @endif
                                    {{ $account->display_name }}
                                </div>
                            </td>
                            <td><i class="{{ S::platformIcon($account->platform) }}" style="color: {{ S::platformColor($account->platform) }}"></i> {{ $account->platform_name }}</td>
                            <td>{{ number_format($account->followers) }}</td>
                            <td>{{ number_format($account->media_count) }}</td>
                            <td>{{ $account->last_sync_at ? diffForHumans($account->last_sync_at) : __('never') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            @include('admin.social.partials.empty', [
                                'icon' => 'las la-plug',
                                'title' => 'No connected accounts',
                                'message' => 'Analytics appear once at least one account is connected and synced.',
                                'actionUrl' => route('admin.social.accounts.index'),
                                'actionLabel' => 'Connect an account',
                            ])
                        </td></tr>
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
    $('#rangeSelect').on('change', function () {
        $('.custom-range').toggleClass('d-none', this.value !== 'custom');
    });

    var timeline = @json($timeline);

    if (window.ApexCharts) {
        new ApexCharts(document.querySelector('#trendChart'), {
            chart:  { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            colors: ['#0d6efd', '#e1306c', '#20c997'],
            stroke: { curve: 'smooth', width: 2 },
            fill:   { type: 'gradient', gradient: { opacityFrom: .25, opacityTo: 0 } },
            dataLabels: { enabled: false },
            xaxis:  { categories: timeline.labels, tickAmount: 12 },
            legend: { position: 'top', horizontalAlign: 'right' },
            tooltip:{ shared: true, intersect: false },
            series: [
                { name: '{{ __('Reach') }}',       data: timeline.series.reach },
                { name: '{{ __('Engagements') }}', data: timeline.series.engagements },
                { name: '{{ __('Views') }}',       data: timeline.series.views }
            ]
        }).render();

        var platforms = @json(collect($byPlatform)->filter(fn ($p) => $p['followers'] > 0)->values());

        if (platforms.length) {
            new ApexCharts(document.querySelector('#followerChart'), {
                chart:  { type: 'donut', height: 320, fontFamily: 'inherit' },
                labels: platforms.map(function (p) { return p.name; }),
                colors: platforms.map(function (p) { return p.color; }),
                series: platforms.map(function (p) { return p.followers; }),
                legend: { position: 'bottom' },
                dataLabels: { enabled: false }
            }).render();
        } else {
            $('#followerChart').html('<div class="social-empty"><i class="las la-users"></i>'
                + '<h6>{{ __('No follower data yet') }}</h6>'
                + '<p>{{ __('Connect an account and run a sync.') }}</p></div>');
        }
    }
})(jQuery);
</script>
@endpush
