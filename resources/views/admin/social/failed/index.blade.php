@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Constants\SocialStatus as S;
    use App\Services\Social\SocialPermission;
    $can = SocialPermission::current();

    // Failures a retry cannot fix on its own - the account or the file has to
    // be dealt with first.
    $needsHuman = ['token_expired', 'permission_denied', 'not_connected', 'not_configured', 'invalid_media', 'unsupported'];
@endphp

@section('panel')

@if($byReason->count())
    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">@lang('Why things failed')</h6></div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                @foreach($byReason as $reason)
                    <span class="social-chip {{ in_array($reason->error_code, $needsHuman, true) ? 'social-chip--danger' : 'social-chip--warn' }}">
                        <i class="{{ S::platformIcon($reason->platform) }}"></i>
                        {{ str_replace('_', ' ', $reason->error_code ?: 'unknown') }}
                        <strong>{{ $reason->total }}</strong>
                    </span>
                @endforeach
            </div>
            <p class="social-counter mt-3 mb-0">
                @lang('Red causes need a person first - usually an account to reconnect or a file to replace. Amber causes are transient and are retried automatically.')
            </p>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0">@lang('Failed posts')</h6>

        <div class="d-flex gap-2">
            <form class="d-flex gap-2" method="GET">
                <select name="platform" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">@lang('All platforms')</option>
                    @foreach(S::PLATFORMS as $key => $label)
                        <option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="error_code" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">@lang('All causes')</option>
                    @foreach($errorCodes as $code)
                        <option value="{{ $code }}" @selected(request('error_code') === $code)>{{ str_replace('_', ' ', $code) }}</option>
                    @endforeach
                </select>
            </form>

            @if($can[SocialPermission::RETRY] ?? false)
                <form action="{{ route('admin.social.failed.retry.all') }}" method="POST">
                    @csrf
                    <input type="hidden" name="platform" value="{{ request('platform') }}">
                    <button class="btn btn-sm btn--warning"><i class="las la-redo"></i> @lang('Retry everything retryable')</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Post')</th>
                        <th>@lang('Platform')</th>
                        <th>@lang('What went wrong')</th>
                        <th>@lang('Attempts')</th>
                        <th>@lang('Last try')</th>
                        <th>@lang('Next try')</th>
                        <th>@lang('Action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($failures as $failure)
                        <tr>
                            <td>
                                @if($failure->post)
                                    <a href="{{ route('admin.social.posts.show', $failure->social_post_id) }}">
                                        {{ strLimit($failure->post->title ?: strip_tags($failure->post->caption), 45) }}
                                    </a>
                                @else
                                    <span class="text-muted">@lang('Deleted post')</span>
                                @endif
                            </td>
                            <td>
                                <i class="{{ S::platformIcon($failure->platform) }}" style="color: {{ S::platformColor($failure->platform) }}"></i>
                                {{ $failure->platform_name }}
                                @if($failure->account)
                                    <small class="d-block text-muted">{{ $failure->account->display_name }}</small>
                                @endif
                            </td>
                            <td style="max-width:340px">
                                <div class="small">{{ $failure->error_message ?: __('The platform rejected the post.') }}</div>
                                @if($failure->error_code)
                                    <span class="social-chip social-chip--muted mt-1">{{ str_replace('_', ' ', $failure->error_code) }}</span>
                                @endif
                            </td>
                            <td>{{ $failure->attempts }}</td>
                            <td>{{ $failure->last_attempt_at ? diffForHumans($failure->last_attempt_at) : '—' }}</td>
                            <td>
                                @if($failure->next_attempt_at)
                                    {{ showDateTime($failure->next_attempt_at, 'd M, h:i A') }}
                                @else
                                    <span class="text-muted">@lang('No automatic retry left')</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1 flex-wrap">
                                    @if(in_array($failure->error_code, ['token_expired', 'permission_denied', 'not_connected'], true))
                                        <a href="{{ route('admin.social.accounts.index') }}#platform-{{ $failure->platform }}"
                                           class="btn btn-sm btn--primary">
                                            <i class="las la-plug"></i> @lang('Reconnect')
                                        </a>
                                    @endif
                                    @if($can[SocialPermission::RETRY] ?? false)
                                        <form action="{{ route('admin.social.posts.target.retry', $failure->id) }}" method="POST">
                                            @csrf
                                            <button class="btn btn-sm btn-outline--warning"><i class="las la-redo"></i> @lang('Retry')</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('admin.social.partials.empty', [
                                    'icon' => 'las la-check-circle',
                                    'title' => 'No failures',
                                    'message' => 'Every publish attempt has succeeded, or been retried successfully.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($failures->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($failures) }}</div>
    @endif
</div>

@endsection
