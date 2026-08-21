@extends('admin.layouts.app')
@section('panel')
    @include('admin.analytics._filter')

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header"><h5 class="mb-0">@lang('Geography / Countries')</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Country')</th>
                                    <th>@lang('Unique Visitors')</th>
                                    <th>@lang('Page Views')</th>
                                    <th>@lang('Valid Clicks')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>
                                            <span class="fw-bold">{{ $row->country_name ?: 'Unknown' }}</span>
                                            @if ($row->country_code)<span class="text-muted">({{ $row->country_code }})</span>@endif
                                        </td>
                                        <td>{{ number_format($row->visitors) }}</td>
                                        <td>{{ number_format($row->views) }}</td>
                                        <td>{{ number_format($row->clicks) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">@lang('No analytics data available.')</td></tr>
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
