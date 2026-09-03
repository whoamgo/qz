@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php use App\Services\Social\SocialPermission; $can = SocialPermission::current(); @endphp

@section('panel')

<div class="social-kpi-grid mb-4">
    @include('admin.social.partials.kpi', ['label' => 'Files',  'value' => $totals['count'],  'icon' => 'las la-photo-video', 'colour' => '#5b6ef5'])
    @include('admin.social.partials.kpi', ['label' => 'Images', 'value' => $totals['images'], 'icon' => 'las la-image',       'colour' => '#20c997'])
    @include('admin.social.partials.kpi', ['label' => 'Videos', 'value' => $totals['videos'], 'icon' => 'las la-video',       'colour' => '#e1306c'])
    @include('admin.social.partials.kpi', ['label' => 'Storage used', 'value' => convertToReadableSize($totals['bytes']), 'icon' => 'las la-hdd', 'colour' => '#6c757d'])
</div>

@unless($canProbe)
    <div class="alert alert-info small">
        <i class="las la-info-circle"></i>
        @lang('ffprobe is not installed on this server, so video duration and dimensions cannot be read. Uploads still work, but the duration and aspect-ratio checks for Reels and Shorts will be reported as unverified rather than passing silently.')
    </div>
@endunless

@if($can[SocialPermission::MANAGE_MEDIA] ?? false)
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('admin.social.media.upload') }}" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">@lang('Upload files')</label>
                    <input type="file" name="files[]" class="form-control" multiple required
                           accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.mov,.webm,.3gp">
                    <small class="social-counter">
                        @lang('Images up to') {{ App\Models\Social\SocialSetting::config()->max_image_mb }} MB,
                        @lang('videos up to') {{ App\Models\Social\SocialSetting::config()->max_video_mb }} MB.
                        @lang('Every file is checked by its real content, not its name.')
                    </small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">@lang('Folder')</label>
                    <input type="text" name="folder" class="form-control" placeholder="@lang('e.g. daily-quiz')">
                </div>
                <div class="col-md-3">
                    <button class="btn btn--primary w-100"><i class="las la-upload"></i> @lang('Upload')</button>
                </div>
            </form>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label">@lang('Search')</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">@lang('Type')</label>
                <select name="type" class="form-control form-control-sm">
                    <option value="">@lang('All')</option>
                    <option value="image" @selected(request('type') === 'image')>@lang('Images')</option>
                    <option value="video" @selected(request('type') === 'video')>@lang('Videos')</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">@lang('Folder')</label>
                <select name="folder" class="form-control form-control-sm">
                    <option value="">@lang('All')</option>
                    @foreach($folders as $folder)
                        <option value="{{ $folder }}" @selected(request('folder') === $folder)>{{ $folder }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn--primary btn-sm w-100">@lang('Filter')</button>
            </div>
        </form>
    </div>

    <div class="card-body">
        @if($items->count())
            <div class="social-media-grid">
                @foreach($items as $media)
                    <div class="social-media-item" data-bs-toggle="modal" data-bs-target="#mediaModal{{ $media->id }}">
                        @if($media->thumbnail_url)
                            <img src="{{ $media->thumbnail_url }}" alt="{{ e($media->original_name) }}" loading="lazy">
                        @else
                            <div class="social-media-placeholder"><i class="las la-file-video"></i></div>
                        @endif
                        <span class="social-media-item__type">{{ $media->type === 'video' ? 'VIDEO' : strtoupper($media->extension) }}</span>
                        <span class="social-media-item__meta">{{ $media->original_name }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Detail modals live outside the grid so the tiles stay light. --}}
            @foreach($items as $media)
                <div class="modal fade" id="mediaModal{{ $media->id }}" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{ strLimit($media->original_name, 50) }}</h5>
                                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        @if($media->type === 'video')
                                            <video src="{{ $media->url }}" controls class="w-100" style="border-radius:8px"></video>
                                        @else
                                            <img src="{{ $media->url }}" class="w-100" style="border-radius:8px" alt="">
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm">
                                            <tr><td>@lang('Type')</td><td class="text-end">{{ strtoupper($media->extension) }} · {{ $media->mime }}</td></tr>
                                            <tr><td>@lang('Size')</td><td class="text-end">{{ $media->size_for_humans }}</td></tr>
                                            <tr><td>@lang('Dimensions')</td><td class="text-end">{{ $media->dimensions ?: __('unknown') }}</td></tr>
                                            <tr><td>@lang('Aspect ratio')</td><td class="text-end">{{ $media->aspect_ratio ?: '—' }}</td></tr>
                                            @if($media->type === 'video')
                                                <tr><td>@lang('Duration')</td><td class="text-end">{{ $media->duration ? $media->duration . 's' : __('unknown') }}</td></tr>
                                            @endif
                                            <tr><td>@lang('Used in')</td><td class="text-end">{{ $media->posts_count }} @lang('post(s)')</td></tr>
                                            <tr><td>@lang('Uploaded')</td><td class="text-end">{{ showDateTime($media->created_at, 'd M Y') }}</td></tr>
                                        </table>

                                        @if($can[SocialPermission::MANAGE_MEDIA] ?? false)
                                            <form action="{{ route('admin.social.media.update', $media->id) }}" method="POST">
                                                @csrf
                                                <div class="form-group mb-2">
                                                    <label class="form-label">@lang('Title')</label>
                                                    <input type="text" name="title" class="form-control form-control-sm" value="{{ $media->title }}">
                                                </div>
                                                <div class="form-group mb-2">
                                                    <label class="form-label">@lang('Alt text')</label>
                                                    <input type="text" name="alt_text" class="form-control form-control-sm" value="{{ $media->alt_text }}">
                                                </div>
                                                <div class="form-group mb-2">
                                                    <label class="form-label">@lang('Tags')</label>
                                                    <input type="text" name="tags" class="form-control form-control-sm" value="{{ $media->tags }}">
                                                </div>
                                                <div class="form-group mb-3">
                                                    <label class="form-label">@lang('Folder')</label>
                                                    <input type="text" name="folder" class="form-control form-control-sm" value="{{ $media->folder }}">
                                                </div>
                                                <button class="btn btn--primary btn-sm">@lang('Save details')</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @if($can[SocialPermission::DELETE_MEDIA] ?? false)
                                <div class="modal-footer">
                                    @if($media->posts_count)
                                        <span class="social-counter">
                                            @lang('Used by :n post(s) - remove it from those posts before deleting.', ['n' => $media->posts_count])
                                        </span>
                                    @else
                                        <button type="button" class="btn btn-sm btn-outline--danger confirmationBtn"
                                                data-action="{{ route('admin.social.media.delete', $media->id) }}"
                                                data-question="@lang('Delete this file permanently?')">
                                            <i class="las la-trash"></i> @lang('Delete')
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            @include('admin.social.partials.empty', [
                'icon' => 'las la-photo-video',
                'title' => 'The library is empty',
                'message' => 'Upload an image or video to use it across your posts.',
            ])
        @endif
    </div>

    @if($items->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($items) }}</div>
    @endif
</div>

<x-confirmation-modal />

@endsection
