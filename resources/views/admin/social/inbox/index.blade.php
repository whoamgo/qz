@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Constants\SocialStatus as S;
    use App\Services\Social\SocialPermission;
    $canManage = SocialPermission::allows(SocialPermission::MANAGE_INBOX);
@endphp

@section('panel')

{{--
    The inbox only shows what the official APIs actually return. Saying which
    platforms cannot appear here - and why - is more useful than an empty tab
    that looks broken.
--}}
@if($unsupported->count())
    <div class="social-note mb-4">
        <strong>@lang('Coverage.')</strong>
        @lang('Comments and mentions are pulled from:')
        {{ collect($supported)->map(fn ($p) => S::platformName($p))->implode(', ') }}.
        @lang('These platforms expose no comment or mention API that this panel can use, so nothing from them appears here:')
        {{ $unsupported->map(fn ($p) => S::platformName($p))->implode(', ') }}.
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <form class="d-flex gap-2 align-items-end" method="GET">
            <div>
                <label class="form-label">@lang('Platform')</label>
                <select name="platform" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">@lang('All')</option>
                    @foreach($supported as $platform)
                        <option value="{{ $platform }}" @selected(request('platform') === $platform)>
                            {{ S::platformName($platform) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">@lang('Show')</label>
                <select name="filter" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">@lang('All')</option>
                    <option value="unread" @selected(request('filter') === 'unread')>@lang('Unread only')</option>
                </select>
            </div>
            <div>
                <label class="form-label">@lang('Search')</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}">
            </div>
            <button class="btn btn-sm btn--primary">@lang('Filter')</button>
        </form>

        <div class="d-flex gap-2 align-items-center">
            @if($unread)
                <span class="badge badge--warning">{{ $unread }} @lang('unread')</span>
            @endif
            @if($canManage)
                <form action="{{ route('admin.social.inbox.sync') }}" method="POST">
                    @csrf
                    <button class="btn btn-sm btn-outline--primary"><i class="las la-sync"></i> @lang('Fetch new')</button>
                </form>
                @if($unread)
                    <form action="{{ route('admin.social.inbox.read.all') }}" method="POST">
                        @csrf
                        <button class="btn btn-sm btn-outline--dark">@lang('Mark all read')</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <div class="card-body p-0">
        @forelse($comments as $comment)
            <div class="d-flex gap-3 p-3 border-bottom {{ $comment->is_read ? '' : 'bg-light' }}" data-comment="{{ $comment->id }}">
                @if($comment->author_avatar)
                    <img src="{{ $comment->author_avatar }}" class="social-avatar" alt="">
                @else
                    <div class="social-platform__icon" style="background: {{ S::platformColor($comment->platform) }}">
                        <i class="{{ S::platformIcon($comment->platform) }}"></i>
                    </div>
                @endif

                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <strong>{{ $comment->author_name ?: __('Unknown') }}</strong>
                        <span class="social-chip social-chip--muted">
                            <i class="{{ S::platformIcon($comment->platform) }}"></i>
                            {{ S::platformName($comment->platform) }}
                        </span>
                        <span class="social-chip">{{ ucfirst($comment->kind) }}</span>
                        @unless($comment->is_read)
                            <span class="badge badge--warning">@lang('Unread')</span>
                        @endunless
                        <small class="text-muted ms-auto">
                            {{ $comment->posted_at ? diffForHumans($comment->posted_at) : '' }}
                        </small>
                    </div>

                    <p class="mb-1 mt-1" style="white-space:pre-wrap">{{ $comment->message }}</p>

                    <div class="d-flex gap-3 small">
                        @if($comment->permalink)
                            <a href="{{ $comment->permalink }}" target="_blank" rel="noopener noreferrer">
                                <i class="las la-external-link-alt"></i> @lang('Open on') {{ S::platformName($comment->platform) }}
                            </a>
                        @endif
                        @if($comment->target?->post)
                            <a href="{{ route('admin.social.posts.show', $comment->target->social_post_id) }}">
                                <i class="las la-file-alt"></i> @lang('View our post')
                            </a>
                        @endif
                        @if($canManage && !$comment->is_read)
                            <button class="btn btn-link btn-sm p-0 markRead"
                                    data-url="{{ route('admin.social.inbox.read', $comment->id) }}">
                                @lang('Mark read')
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            @include('admin.social.partials.empty', [
                'icon' => 'las la-comments',
                'title' => 'Nothing in the inbox',
                'message' => count($supported)
                    ? 'Connect an account and click "Fetch new" to pull in comments and mentions.'
                    : 'None of the connected platforms expose comments through their API.',
            ])
        @endforelse
    </div>

    @if($comments->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($comments) }}</div>
    @endif
</div>

@endsection

@push('script')
<script>
"use strict";
(function ($) {
    $(document).on('click', '.markRead', function () {
        var $btn = $(this);

        $.post($btn.data('url'), { _token: '{{ csrf_token() }}' }, function () {
            $btn.closest('[data-comment]').removeClass('bg-light').find('.badge--warning').remove();
            $btn.remove();
        });
    });
})(jQuery);
</script>
@endpush
