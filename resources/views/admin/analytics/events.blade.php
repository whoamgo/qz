@extends('admin.layouts.app')
@section('panel')
    @include('admin.analytics._filter')

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0">@lang('Analytics Events (Raw)')</h5>
                    <form method="GET" class="d-flex gap-2">
                        @foreach (request()->except(['status', 'type', 'page']) as $qk => $qv)
                            <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                        @endforeach
                        <select name="status" class="form-control form-control-sm w-auto" onchange="this.form.submit()">
                            <option value="">@lang('All statuses')</option>
                            @foreach ($statuses as $s)
                                <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="type" value="{{ $type }}" class="form-control form-control-sm w-auto" placeholder="@lang('Event type')">
                        <button class="btn btn-sm btn--primary"><i class="las la-filter"></i></button>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Type')</th>
                                    <th>@lang('Page')</th>
                                    <th>@lang('Element')</th>
                                    <th>@lang('User')</th>
                                    <th>@lang('Country')</th>
                                    <th>@lang('IP')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Time')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    @php
                                        $badge = [
                                            'valid' => 'success', 'duplicate' => 'warning',
                                            'rate_limited' => 'dark', 'bot' => 'danger',
                                        ][$row->status] ?? 'secondary';
                                    @endphp
                                    <tr>
                                        <td>{{ $row->event_type }}</td>
                                        <td class="text-truncate" style="max-width:200px;" title="{{ $row->page_path }}">{{ $row->page_path }}</td>
                                        <td>{{ $row->element_name ?: '—' }}</td>
                                        <td>{{ $row->user?->username ?? ($row->visitor_id ? 'anon' : '—') }}</td>
                                        <td>{{ $row->country_name ?: 'Unknown' }}</td>
                                        <td><span class="text-muted small">{{ $row->ip_address ?: '—' }}</span></td>
                                        <td><span class="badge badge--{{ $badge }}">{{ str_replace('_', ' ', $row->status) }}</span></td>
                                        <td>{{ $row->created_at ? $row->created_at->diffForHumans() : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-muted py-4">@lang('No events recorded.')</td></tr>
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
