@extends('admin.layouts.app')
@include('admin.social.partials.head')

@section('panel')

<div class="social-note mb-4">
    <i class="las la-shield-alt"></i>
    @lang('Access tokens, secrets and API keys are stripped from every entry before it is written. Nothing recorded here can be used to authenticate anywhere.')
</div>

<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label">@lang('Action')</label>
                <select name="action" class="form-control form-control-sm">
                    <option value="">@lang('All')</option>
                    @foreach($actions as $key => $label)
                        <option value="{{ $key }}" @selected(request('action') === $key)>@lang($label)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">@lang('Platform')</label>
                <select name="platform" class="form-control form-control-sm">
                    <option value="">@lang('All')</option>
                    @foreach($platforms as $key => $label)
                        <option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">@lang('Admin')</label>
                <select name="admin_id" class="form-control form-control-sm">
                    <option value="">@lang('All')</option>
                    @foreach($admins as $admin)
                        <option value="{{ $admin->id }}" @selected(request('admin_id') == $admin->id)>{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">@lang('Result')</label>
                <select name="result" class="form-control form-control-sm">
                    <option value="">@lang('All')</option>
                    @foreach(['success' => 'Success', 'failed' => 'Failed', 'denied' => 'Denied'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('result') === $key)>@lang($label)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn--primary btn-sm flex-grow-1">@lang('Filter')</button>
                <a href="{{ route('admin.social.logs.index') }}" class="btn btn--dark btn-sm">@lang('Reset')</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('When')</th>
                        <th>@lang('Admin')</th>
                        <th>@lang('Action')</th>
                        <th>@lang('Detail')</th>
                        <th>@lang('Platform')</th>
                        <th>@lang('Result')</th>
                        <th>@lang('IP')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>
                                {{ showDateTime($log->created_at, 'd M Y') }}
                                <small class="d-block text-muted">{{ showDateTime($log->created_at, 'h:i:s A') }}</small>
                            </td>
                            <td>{{ $log->admin_name ?: __('System') }}</td>
                            <td>{{ $log->action_name }}</td>
                            <td style="max-width:380px">
                                {{ $log->description }}
                                @if($log->context)
                                    <button class="btn btn-link btn-sm p-0 d-block showContext"
                                            data-context="{{ json_encode($log->context) }}">
                                        @lang('Show details')
                                    </button>
                                @endif
                            </td>
                            <td>
                                @if($log->platform)
                                    <i class="{{ App\Constants\SocialStatus::platformIcon($log->platform) }}"
                                       style="color: {{ App\Constants\SocialStatus::platformColor($log->platform) }}"></i>
                                    {{ App\Constants\SocialStatus::platformName($log->platform) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="badge badge--{{ $log->result_class }}">{{ ucfirst($log->result) }}</span></td>
                            <td><small class="text-muted">{{ $log->ip }}</small></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('admin.social.partials.empty', [
                                    'icon' => 'las la-clipboard-list',
                                    'title' => 'No activity recorded yet',
                                    'message' => 'Connections, posts, approvals and publishes all appear here as they happen.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($logs->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($logs) }}</div>
    @endif
</div>

<div class="modal fade" id="contextModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Entry details')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <pre id="contextBody" class="social-note mb-0" style="white-space:pre-wrap"></pre>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
"use strict";
(function ($) {
    $(document).on('click', '.showContext', function () {
        $('#contextBody').text(JSON.stringify($(this).data('context'), null, 2));
        new bootstrap.Modal(document.getElementById('contextModal')).show();
    });
})(jQuery);
</script>
@endpush
