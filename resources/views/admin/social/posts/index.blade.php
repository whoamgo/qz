@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Constants\SocialStatus as S;
    use App\Services\Social\SocialPermission;
    $can = SocialPermission::current();
@endphp

@section('panel')

<div class="card">
    <div class="card-body">
        {{-- ------------------------------------------------------ Filters --}}
        <form class="row g-2 align-items-end mb-4" method="GET">
            <div class="col-md-3">
                <label class="form-label">@lang('Search')</label>
                <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                       placeholder="@lang('Title or caption')">
            </div>
            <div class="col-md-2">
                <label class="form-label">@lang('Status')</label>
                <select name="status" class="form-control">
                    <option value="">@lang('All')</option>
                    @foreach(S::POST_STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>@lang($label)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">@lang('Platform')</label>
                <select name="platform" class="form-control">
                    <option value="">@lang('All')</option>
                    @foreach(S::PLATFORMS as $key => $label)
                        <option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">@lang('Campaign')</label>
                <select name="campaign" class="form-control">
                    <option value="">@lang('All')</option>
                    @foreach($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" @selected(request('campaign') == $campaign->id)>{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn--primary flex-grow-1"><i class="las la-filter"></i> @lang('Filter')</button>
                <a href="{{ route('admin.social.posts.index') }}" class="btn btn--dark">@lang('Reset')</a>
            </div>
        </form>

        {{-- -------------------------------------------------------- Table --}}
        <div class="table-responsive">
            <table class="table table--light style--two">
                <thead>
                    <tr>
                        <th>@lang('Post')</th>
                        <th>@lang('Platforms')</th>
                        <th>@lang('Status')</th>
                        <th>@lang('Scheduled')</th>
                        <th>@lang('Published')</th>
                        <th>@lang('Performance')</th>
                        <th>@lang('Action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($posts as $post)
                        @php
                            $metric = fn ($key) => $post->targets->sum(fn ($t) => (int) $t->metric($key, 0));
                        @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($post->previewImage())
                                        <img src="{{ $post->previewImage() }}" alt=""
                                             style="width:44px;height:44px;object-fit:cover;border-radius:6px;">
                                    @else
                                        <div class="social-platform__icon" style="background:#e9ecf3;color:#8a94ad;width:44px;height:44px;">
                                            <i class="las la-{{ $post->content_type === 'video' || $post->content_type === 'reel' ? 'video' : 'align-left' }}"></i>
                                        </div>
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.social.posts.show', $post->id) }}" class="d-block text-truncate" style="max-width:280px">
                                            {{ $post->title ?: strLimit(strip_tags($post->caption), 50) }}
                                        </a>
                                        <small class="text-muted">
                                            {{ __(S::CONTENT_TYPES[$post->content_type] ?? $post->content_type) }}
                                            @if($post->campaign) · {{ $post->campaign->name }} @endif
                                            @if($post->ai_generated) · <span class="badge badge--secondary">@lang('AI')</span> @endif
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>@include('admin.social.partials.platform-chips', ['targets' => $post->targets])</td>
                            <td>
                                <span class="badge badge--{{ $post->status_class }}">{{ $post->status_name }}</span>
                                @if($post->approval_status === S::APPROVAL_PENDING)
                                    <span class="badge badge--warning">@lang('Approval')</span>
                                @endif
                            </td>
                            <td>{{ $post->scheduled_at ? showDateTime($post->scheduled_at, 'd M Y, h:i A') : '—' }}</td>
                            <td>{{ $post->published_at ? showDateTime($post->published_at, 'd M Y, h:i A') : '—' }}</td>
                            <td>
                                @if($post->status === S::PUBLISHED || $post->status === S::PARTIALLY_PUBLISHED)
                                    <small class="d-block">
                                        <i class="las la-eye"></i> {{ number_format($metric('views')) }}
                                        <i class="las la-heart ms-2"></i> {{ number_format($metric('likes')) }}
                                        <i class="las la-comment ms-2"></i> {{ number_format($metric('comments')) }}
                                    </small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline--primary dropdown-toggle" data-bs-toggle="dropdown">
                                        @lang('Actions')
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('admin.social.posts.show', $post->id) }}">
                                                <i class="las la-eye"></i> @lang('View')
                                            </a>
                                        </li>
                                        @if(($can[SocialPermission::EDIT] ?? false) && $post->isEditable())
                                            <li>
                                                <a class="dropdown-item" href="{{ route('admin.social.posts.edit', $post->id) }}">
                                                    <i class="las la-edit"></i> @lang('Edit')
                                                </a>
                                            </li>
                                        @endif
                                        @if($can[SocialPermission::CREATE] ?? false)
                                            <li>
                                                <button type="button" class="dropdown-item confirmationBtn"
                                                        data-action="{{ route('admin.social.posts.duplicate', $post->id) }}"
                                                        data-question="@lang('Duplicate this post as a new draft?')">
                                                    <i class="las la-copy"></i> @lang('Duplicate')
                                                </button>
                                            </li>
                                        @endif
                                        @if(($can[SocialPermission::SCHEDULE] ?? false) && in_array($post->status, [S::SCHEDULED, S::QUEUED, S::APPROVED], true))
                                            <li>
                                                <button type="button" class="dropdown-item confirmationBtn"
                                                        data-action="{{ route('admin.social.posts.cancel', $post->id) }}"
                                                        data-question="@lang('Cancel this scheduled post? Anything already published stays published.')">
                                                    <i class="las la-ban"></i> @lang('Cancel schedule')
                                                </button>
                                            </li>
                                        @endif
                                        @if(($can[SocialPermission::RETRY] ?? false) && in_array($post->status, [S::FAILED, S::PARTIALLY_PUBLISHED], true))
                                            <li>
                                                <a class="dropdown-item" href="{{ route('admin.social.posts.show', $post->id) }}">
                                                    <i class="las la-redo"></i> @lang('Retry failures')
                                                </a>
                                            </li>
                                        @endif
                                        @if($can[SocialPermission::DELETE] ?? false)
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button" class="dropdown-item text--danger confirmationBtn"
                                                        data-action="{{ route('admin.social.posts.delete', $post->id) }}"
                                                        data-question="@lang('Delete this post? Anything already published on a platform is not removed.')">
                                                    <i class="las la-trash"></i> @lang('Delete')
                                                </button>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('admin.social.partials.empty', [
                                    'icon' => 'las la-share-alt',
                                    'title' => request()->hasAny(['search', 'status', 'platform', 'campaign']) ? 'No posts match those filters' : 'No posts yet',
                                    'message' => request()->hasAny(['search', 'status', 'platform', 'campaign'])
                                        ? 'Try widening the filters, or reset them.'
                                        : 'Create one piece of content and publish it everywhere from a single screen.',
                                    'actionUrl' => ($can[SocialPermission::CREATE] ?? false) ? route('admin.social.posts.create') : null,
                                    'actionLabel' => 'Create Social Post',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($posts->hasPages())
        <div class="card-footer py-4">
            {{ paginateLinks($posts) }}
        </div>
    @endif
</div>

<x-confirmation-modal />

@endsection

@push('breadcrumb-plugins')
    @if($can[App\Services\Social\SocialPermission::CREATE] ?? false)
        <a href="{{ route('admin.social.posts.create') }}" class="btn btn-sm btn-outline--primary">
            <i class="las la-plus"></i> @lang('Create Post')
        </a>
    @endif
@endpush
