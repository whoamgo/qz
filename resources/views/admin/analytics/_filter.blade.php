@php $currentRange = $rangeKey ?? 'last_7_days'; @endphp
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            @foreach ([
                'today' => 'Today', 'yesterday' => 'Yesterday', 'last_7_days' => 'Last 7 Days',
                'last_30_days' => 'Last 30 Days', 'this_month' => 'This Month',
            ] as $k => $label)
                <a href="{{ request()->fullUrlWithQuery(['range' => $k, 'date_from' => null, 'date_to' => null, 'page' => null]) }}"
                   class="btn btn-sm {{ $currentRange === $k ? 'btn--primary' : 'btn-outline--primary' }}">{{ __($label) }}</a>
            @endforeach

            <form method="GET" class="d-flex flex-wrap gap-2 align-items-center ms-1">
                @foreach (request()->except(['range', 'date_from', 'date_to', 'page']) as $qk => $qv)
                    <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                @endforeach
                <input type="hidden" name="range" value="custom">
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm w-auto" title="@lang('From')">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm w-auto" title="@lang('To')">
                <button class="btn btn-sm {{ $currentRange === 'custom' ? 'btn--primary' : 'btn-outline--primary' }}">@lang('Apply')</button>
            </form>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="badge badge--dark">{{ $rangeLabel ?? '' }}</span>
            <button type="button" class="btn btn-sm btn-outline--danger" data-bs-toggle="modal" data-bs-target="#clearAnalyticsModal">
                <i class="las la-trash"></i> @lang('Clear Analytics')
            </button>
        </div>
    </div>
</div>

{{-- Clear-analytics confirmation --}}
<div class="modal fade" id="clearAnalyticsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form action="{{ route('admin.analytics.clear') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">@lang('Clear Analytics Data')</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text--danger fw-bold mb-2">
                    <i class="las la-exclamation-triangle"></i> @lang('This will permanently delete analytics data. This action cannot be undone.')
                </p>
                <label class="form-label">@lang('What to clear')</label>
                <select name="range" class="form-control">
                    <option value="today">@lang('Clear Today')</option>
                    <option value="last_7_days">@lang('Clear Last 7 Days')</option>
                    <option value="last_30_days">@lang('Clear Last 30 Days')</option>
                    <option value="all">@lang('Clear All Data')</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                <button type="submit" class="btn btn--danger">@lang('Clear Data')</button>
            </div>
        </form>
    </div>
</div>
