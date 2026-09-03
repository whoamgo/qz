@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php use App\Constants\SocialStatus as S; @endphp

@section('panel')

<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-3">
                <label class="form-label">@lang('Sort by')</label>
                <select name="sort" class="form-control" onchange="this.form.submit()">
                    @foreach([
                        'views' => 'Views', 'likes' => 'Likes', 'comments' => 'Comments',
                        'shares' => 'Shares', 'clicks' => 'Clicks',
                        'engagement' => 'Total engagement', 'engagement_rate' => 'Engagement rate',
                    ] as $key => $label)
                        <option value="{{ $key }}" @selected($sort === $key)>@lang($label)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">@lang('Platform')</label>
                <select name="platform" class="form-control" onchange="this.form.submit()">
                    <option value="">@lang('All')</option>
                    @foreach(S::PLATFORMS as $key => $label)
                        <option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">@lang('Range')</label>
                <select name="range" class="form-control" onchange="this.form.submit()">
                    @foreach(['7' => 'Last 7 days', '30' => 'Last 30 days', '90' => 'Last 90 days'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('range', '30') === $key)>@lang($label)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <a href="{{ route('admin.social.analytics.index') }}" class="btn btn-outline--primary w-100">@lang('Back to analytics')</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>@lang('Content')</th>
                        <th>@lang('Platform')</th>
                        <th>@lang('Published')</th>
                        <th>@lang('Views')</th>
                        <th>@lang('Likes')</th>
                        <th>@lang('Comments')</th>
                        <th>@lang('Shares')</th>
                        <th>@lang('Clicks')</th>
                        <th>@lang('Engagement')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $index => $item)
                        @php $target = $item['target']; @endphp
                        <tr>
                            <td><strong>{{ $index + 1 }}</strong></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if($target->post?->previewImage())
                                        <img src="{{ $target->post->previewImage() }}" alt=""
                                             style="width:40px;height:40px;object-fit:cover;border-radius:6px">
                                    @endif
                                    <div class="min-w-0">
                                        <a href="{{ $target->post ? route('admin.social.posts.show', $target->social_post_id) : '#' }}"
                                           class="d-block text-truncate" style="max-width:260px">
                                            {{ $target->post?->title ?: strLimit(strip_tags($target->post?->caption), 45) }}
                                        </a>
                                        @if($target->platform_url)
                                            <a href="{{ $target->platform_url }}" target="_blank" rel="noopener" class="small text-muted">
                                                <i class="las la-external-link-alt"></i> @lang('Open live post')
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <i class="{{ S::platformIcon($target->platform) }}" style="color: {{ S::platformColor($target->platform) }}"></i>
                                {{ $target->platform_name }}
                            </td>
                            <td>{{ $target->published_at ? showDateTime($target->published_at, 'd M Y') : '—' }}</td>
                            <td>{{ number_format($item['views']) }}</td>
                            <td>{{ number_format($item['likes']) }}</td>
                            <td>{{ number_format($item['comments']) }}</td>
                            <td>{{ number_format($item['shares']) }}</td>
                            <td>{{ number_format($item['clicks']) }}</td>
                            <td>
                                {{ number_format($item['engagement']) }}
                                @if($item['engagement_rate'])
                                    <small class="d-block text-muted">{{ $item['engagement_rate'] }}%</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">
                                @include('admin.social.partials.empty', [
                                    'icon' => 'las la-trophy',
                                    'title' => 'No performance data yet',
                                    'message' => 'Metrics appear after posts have been published and synced from their platform.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection
