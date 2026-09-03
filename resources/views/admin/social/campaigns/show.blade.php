@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php use App\Models\Social\SocialCampaign; @endphp

@section('panel')

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h5 class="mb-1">{{ $campaign->name }}</h5>
                <p class="text-muted mb-2">{{ $campaign->description }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge badge--{{ $campaign->status_class }}">
                        {{ SocialCampaign::STATUSES[$campaign->status] ?? $campaign->status }}
                    </span>
                    <span class="social-chip">
                        <i class="las la-calendar"></i>
                        {{ $campaign->start_date?->format('d M Y') ?: '—' }} &rarr; {{ $campaign->end_date?->format('d M Y') ?: '—' }}
                    </span>
                    <span class="social-chip"><i class="las la-tag"></i> {{ $campaign->utm_campaign }}</span>
                </div>
            </div>
            <a href="{{ route('admin.social.campaigns.index') }}" class="btn btn-outline--dark btn-sm">
                <i class="las la-arrow-left"></i> @lang('All campaigns')
            </a>
        </div>
    </div>
</div>

<div class="social-kpi-grid mb-4">
    @include('admin.social.partials.kpi', ['label' => 'Posts',     'value' => $stats['posts'],     'icon' => 'las la-stream',   'colour' => '#6c757d'])
    @include('admin.social.partials.kpi', ['label' => 'Published', 'value' => $stats['published'], 'icon' => 'las la-check',    'colour' => '#28a745'])
    @include('admin.social.partials.kpi', ['label' => 'Reach',     'value' => $stats['reach'],     'icon' => 'las la-bullseye', 'colour' => '#0d6efd'])
    @include('admin.social.partials.kpi', ['label' => 'Views',     'value' => $stats['views'],     'icon' => 'las la-eye',      'colour' => '#20c997'])
    @include('admin.social.partials.kpi', ['label' => 'Likes',     'value' => $stats['likes'],     'icon' => 'las la-heart',    'colour' => '#e1306c'])
    @include('admin.social.partials.kpi', ['label' => 'Clicks',    'value' => $stats['clicks'],    'icon' => 'las la-mouse-pointer', 'colour' => '#198754'])
    @include('admin.social.partials.kpi', [
        'label'  => 'Engagement rate',
        'value'  => $stats['engagement_rate'] !== null ? $stats['engagement_rate'] . '%' : '—',
        'icon'   => 'las la-percentage',
        'colour' => '#dc3545',
    ])
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">@lang('Posts in this campaign')</h6></div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Post')</th>
                        <th>@lang('Platforms')</th>
                        <th>@lang('Status')</th>
                        <th>@lang('Published')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($posts as $post)
                        <tr>
                            <td>
                                <a href="{{ route('admin.social.posts.show', $post->id) }}">
                                    {{ $post->title ?: strLimit(strip_tags($post->caption), 50) }}
                                </a>
                            </td>
                            <td>@include('admin.social.partials.platform-chips', ['targets' => $post->targets])</td>
                            <td><span class="badge badge--{{ $post->status_class }}">{{ $post->status_name }}</span></td>
                            <td>{{ $post->published_at ? showDateTime($post->published_at, 'd M Y, h:i A') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                @include('admin.social.partials.empty', [
                                    'icon' => 'las la-folder-open',
                                    'title' => 'No posts in this campaign',
                                    'message' => 'Assign a campaign when creating a post to group it here.',
                                    'actionUrl' => route('admin.social.posts.create'),
                                    'actionLabel' => 'Create a post',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($posts->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($posts) }}</div>
    @endif
</div>

@endsection
