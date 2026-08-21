@extends('admin.layouts.app')
@section('panel')
    @include('admin.analytics._filter')

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">@lang('Page Analytics')</h5>
                    <form method="GET" class="d-flex gap-2">
                        @foreach (request()->except(['search', 'page']) as $qk => $qv)
                            <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                        @endforeach
                        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="@lang('Search page…')">
                        <button class="btn btn-sm btn--primary"><i class="las la-search"></i></button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Page')</th>
                                    <th>@include('admin.analytics._sort', ['col' => 'views', 'label' => 'Page Views'])</th>
                                    <th>@include('admin.analytics._sort', ['col' => 'visitors', 'label' => 'Unique Visitors'])</th>
                                    <th>@lang('Valid Clicks')</th>
                                    <th>@include('admin.analytics._sort', ['col' => 'last_visited', 'label' => 'Last Visited'])</th>
                                    <th>@lang('Top Country')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td><span class="fw-bold">{{ $row->page_path }}</span></td>
                                        <td>{{ number_format($row->views) }}</td>
                                        <td>{{ number_format($row->visitors) }}</td>
                                        <td>{{ number_format($row->clicks) }}</td>
                                        <td>{{ $row->last_visited ? \Carbon\Carbon::parse($row->last_visited)->diffForHumans() : '—' }}</td>
                                        <td>{{ $row->top_country }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">@lang('No analytics data available.')</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($rows->hasPages())
                    <div class="card-footer py-4">{{ paginateLinks($rows) }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
