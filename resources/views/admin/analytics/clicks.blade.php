@extends('admin.layouts.app')
@section('panel')
    @include('admin.analytics._filter')

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">@lang('Click Analytics')</h5>
                    <form method="GET" class="d-flex gap-2">
                        @foreach (request()->except(['search', 'page']) as $qk => $qv)
                            <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                        @endforeach
                        <input type="text" name="search" value="{{ $search }}" class="form-control form-control-sm" placeholder="@lang('Search element / page…')">
                        <button class="btn btn-sm btn--primary"><i class="las la-search"></i></button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@include('admin.analytics._sort', ['col' => 'element', 'label' => 'Element Name'])</th>
                                    <th>@lang('Category')</th>
                                    <th>@lang('Page')</th>
                                    <th>@include('admin.analytics._sort', ['col' => 'clicks', 'label' => 'Valid Clicks'])</th>
                                    <th>@include('admin.analytics._sort', ['col' => 'users', 'label' => 'Unique Users'])</th>
                                    <th>@include('admin.analytics._sort', ['col' => 'last_clicked', 'label' => 'Last Clicked'])</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td><span class="fw-bold">{{ $row->element_name }}</span></td>
                                        <td>{{ $row->element_category ?: '—' }}</td>
                                        <td>{{ $row->page_path }}</td>
                                        <td>{{ number_format($row->clicks) }}</td>
                                        <td>{{ number_format($row->users) }}</td>
                                        <td>{{ $row->last_clicked ? \Carbon\Carbon::parse($row->last_clicked)->diffForHumans() : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">@lang('No click data available.')</td></tr>
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
