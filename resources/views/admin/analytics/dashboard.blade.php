@extends('admin.layouts.app')
@section('panel')
    @include('admin.analytics._filter')

    {{-- ---- Summary cards ---- --}}
    <div class="row gy-4 mb-2">
        @foreach ([
            ['Total Page Views', $cards['total_page_views'], 'las la-eye', 'primary', false],
            ['Unique Visitors', $cards['unique_visitors'], 'las la-users', 'success', false],
            ['Total Valid Clicks', $cards['total_clicks'], 'las la-mouse-pointer', 'info', false],
            ["Today's Page Views", $cards['today_page_views'], 'las la-calendar-day', 'warning', false],
            ["Today's Valid Clicks", $cards['today_clicks'], 'las la-hand-pointer', 'dark', false],
            ['Most Viewed Page', $cards['most_viewed_page'], 'las la-file-alt', 'primary', true],
            ['Most Clicked Element', $cards['most_clicked'], 'las la-bullseye', 'info', true],
            ['Top Country', $cards['top_country'], 'las la-globe', 'success', true],
        ] as [$label, $value, $icon, $color, $isText])
            <div class="col-xxl-3 col-sm-6">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <span class="icon-box bg--{{ $color }} text-white rounded d-inline-flex align-items-center justify-content-center"
                              style="width:52px;height:52px;font-size:24px;flex-shrink:0;">
                            <i class="{{ $icon }}"></i>
                        </span>
                        <div class="overflow-hidden">
                            <h4 class="mb-0 text-truncate" title="{{ $value }}">
                                {{ ($isText ?? false) ? $value : number_format((int) $value) }}
                            </h4>
                            <span class="text-muted">@lang($label)</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ---- Charts ---- --}}
    <div class="row gy-4 mt-1">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">@lang('Page Views vs Clicks — Today')</h5></div>
                <div class="card-body"><div id="chartToday" style="min-height:320px;"></div></div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">@lang('Page Views vs Clicks — Last 7 Days')</h5></div>
                <div class="card-body"><div id="chart7d" style="min-height:320px;"></div></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">@lang('Top Countries by Visitors')</h5></div>
                <div class="card-body"><div id="chartCountries" style="min-height:320px;"></div></div>
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
    (function () {
        var hasCountries = {{ count($charts['countries']['labels']) ? 'true' : 'false' }};
        var opt = {
            chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            stroke: { curve: 'smooth', width: 2 },
            dataLabels: { enabled: false },
            colors: ['#4634ff', '#20c997'],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            legend: { position: 'top' },
            grid: { borderColor: '#eef0f4' }
        };

        // Today (hourly)
        new ApexCharts(document.querySelector('#chartToday'), Object.assign({}, opt, {
            series: [
                { name: 'Page Views', data: @json($charts['today_hourly']['views']) },
                { name: 'Clicks', data: @json($charts['today_hourly']['clicks']) }
            ],
            xaxis: { categories: @json($charts['today_hourly']['labels']) }
        })).render();

        // Last 7 days (daily) — column style
        new ApexCharts(document.querySelector('#chart7d'), Object.assign({}, opt, {
            chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            fill: { type: 'solid', opacity: 1 },
            series: [
                { name: 'Page Views', data: @json($charts['last7_daily']['views']) },
                { name: 'Clicks', data: @json($charts['last7_daily']['clicks']) }
            ],
            xaxis: { categories: @json($charts['last7_daily']['labels']) }
        })).render();

        // Countries (donut) — empty-state safe
        if (hasCountries) {
            new ApexCharts(document.querySelector('#chartCountries'), {
                chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
                labels: @json($charts['countries']['labels']),
                series: @json($charts['countries']['data']),
                legend: { position: 'bottom' },
                colors: ['#4634ff', '#20c997', '#fd7e14', '#0dcaf0', '#6f42c1', '#dc3545', '#ffc107', '#198754', '#6c757d', '#d63384'],
                dataLabels: { enabled: true }
            }).render();
        } else {
            document.querySelector('#chartCountries').innerHTML =
                '<div class="text-center text-muted py-5">@lang('No country data available.')</div>';
        }
    })();
</script>
@endpush
