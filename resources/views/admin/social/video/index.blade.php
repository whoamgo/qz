@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php use App\Constants\SocialStatus as S; @endphp

@section('panel')

<div class="social-note mb-4">
    <strong>@lang('Vertical video workflow.')</strong>
    @lang('Pick a video, choose whether it is short-form, and check it against every platform before uploading anything. The checks run locally, so a file that would be rejected is caught in a second rather than after a long upload.')
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">@lang('1. Choose a video')</h6>
                <a href="{{ route('admin.social.media.index') }}" class="btn btn-sm btn-outline--primary">
                    <i class="las la-upload"></i> @lang('Upload')
                </a>
            </div>
            <div class="card-body">
                @if($videos->count())
                    <div class="social-media-grid" id="videoGrid">
                        @foreach($videos as $video)
                            <div class="social-media-item" data-media-id="{{ $video->id }}">
                                @if($video->thumbnail_url)
                                    <img src="{{ $video->thumbnail_url }}" alt="" loading="lazy">
                                @else
                                    <div class="social-media-placeholder"><i class="las la-file-video"></i></div>
                                @endif
                                <span class="social-media-item__type">
                                    {{ $video->duration ? round($video->duration) . 's' : 'VIDEO' }}
                                </span>
                                <span class="social-media-item__meta">{{ $video->original_name }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    @include('admin.social.partials.empty', [
                        'icon' => 'las la-video',
                        'title' => 'No videos in the library',
                        'message' => 'Upload a video to the media library first.',
                        'actionUrl' => route('admin.social.media.index'),
                        'actionLabel' => 'Go to Media Library',
                    ])
                @endif

                @unless($canProbe)
                    <div class="alert alert-warning small mt-3 mb-0">
                        @lang('ffprobe is not installed, so duration and dimensions cannot be read from your videos. The duration and aspect-ratio checks below will report "could not be verified" rather than passing.')
                    </div>
                @endunless
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">@lang('2. Format')</h6></div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="asShort" checked>
                    <label class="form-check-label" for="asShort">
                        <strong>@lang('Short-form (vertical)')</strong>
                        <small class="d-block text-muted">
                            @lang('YouTube Shorts, Instagram Reels, Facebook Reels. Tighter duration limits and a 9:16 frame.')
                        </small>
                    </label>
                </div>

                <label class="form-label">@lang('Publish to')</label>
                <div id="videoPlatforms">
                    @foreach($platforms as $key => $platform)
                        @php
                            $supportsShort = in_array($key, $shortPlatforms, true);
                            $supportsVideo = in_array($key, $videoPlatforms, true);
                        @endphp
                        @if($supportsVideo)
                            <div class="form-check mb-2 platform-option"
                                 data-platform="{{ $key }}"
                                 data-short="{{ $supportsShort ? 1 : 0 }}">
                                <input class="form-check-input" type="checkbox" value="{{ $key }}"
                                       id="vp-{{ $key }}" {{ $platform['available'] ? '' : 'disabled' }}>
                                <label class="form-check-label" for="vp-{{ $key }}">
                                    <i class="{{ $platform['icon'] }}" style="color: {{ $platform['color'] }}"></i>
                                    {{ $platform['name'] }}
                                    @unless($platform['available'])
                                        <small class="d-block text-muted">{{ $platform['reason'] }}</small>
                                    @endunless
                                </label>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">@lang('3. Pre-flight check')</h6></div>
            <div class="card-body">
                <div id="videoDetails" class="social-note mb-3 d-none"></div>
                <div id="checkResults">
                    @include('admin.social.partials.empty', [
                        'icon' => 'las la-clipboard-check',
                        'title' => 'Nothing checked yet',
                        'message' => 'Pick a video and at least one platform to run the checks.',
                    ])
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h6 class="mb-0">@lang('4. Continue')</h6></div>
            <div class="card-body">
                <p class="social-counter">
                    @lang('Everything checks out? Continue into the publishing wizard, where the video is attached and you can write the copy for each platform.')
                </p>
                <a href="{{ route('admin.social.posts.create') }}" class="btn btn--primary" id="continueBtn">
                    <i class="las la-arrow-right"></i> @lang('Open the publishing wizard')
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

@push('script')
<script>
"use strict";
(function ($) {

    var selected = null;
    var checkUrl = '{{ route('admin.social.video.check') }}';
    var csrf     = '{{ csrf_token() }}';

    $(document).on('click', '#videoGrid .social-media-item', function () {
        $('#videoGrid .social-media-item').removeClass('is-selected');
        $(this).addClass('is-selected');
        selected = $(this).data('media-id');
        runCheck();
    });

    $('#asShort').on('change', function () {
        // Short-form only makes sense on platforms that have it; the rest are
        // dimmed rather than silently included.
        $('.platform-option').each(function () {
            var supportsShort = $(this).data('short') === 1;
            var isShortMode   = $('#asShort').is(':checked');
            var $input        = $(this).find('input');

            if (isShortMode && !supportsShort) {
                $input.prop('checked', false).prop('disabled', true);
                $(this).css('opacity', .5);
            } else if (!$input.data('unavailable')) {
                $input.prop('disabled', false);
                $(this).css('opacity', 1);
            }
        });

        runCheck();
    });

    $(document).on('change', '#videoPlatforms input', runCheck);

    // Remember which inputs were disabled server-side, so re-enabling on mode
    // change never turns on a platform that has no connected account.
    $('#videoPlatforms input:disabled').data('unavailable', true);

    function chosenPlatforms() {
        return $('#videoPlatforms input:checked').map(function () { return this.value; }).get();
    }

    function runCheck() {
        var platforms = chosenPlatforms();

        if (!selected || !platforms.length) {
            return;
        }

        $('#checkResults').html('<div class="social-skeleton mb-2"></div><div class="social-skeleton" style="width:70%"></div>');

        $.post(checkUrl, {
            _token: csrf,
            media_id: selected,
            platforms: platforms,
            as_short: $('#asShort').is(':checked') ? 1 : 0
        }).done(render).fail(function (xhr) {
            var res = xhr.responseJSON || {};
            render(res.issues ? res : { issues: [{ severity: 'error', platform: '', message: '{{ __('The check could not be completed.') }}' }] });
        });
    }

    function render(res) {
        if (res.video) {
            $('#videoDetails').removeClass('d-none').html(
                '<strong>' + escapeHtml(res.video.name) + '</strong> · ' + res.video.size
                + (res.video.duration ? ' · ' + res.video.duration + 's' : ' · {{ __('duration unknown') }}')
                + (res.video.ratio ? ' · ' + res.video.ratio : '')
                + (res.video.width ? ' · ' + res.video.width + '×' + res.video.height : '')
            );
        }

        var issues = res.issues || [];

        if (!issues.length) {
            $('#checkResults').html(
                '<div class="alert alert-success mb-0"><i class="las la-check-circle"></i> '
                + '{{ __('This video meets the requirements of every selected platform.') }}</div>'
            );
            return;
        }

        var html = '';

        if (res.blocking) {
            html += '<div class="alert alert-danger"><strong>' + res.blocking + ' '
                  + '{{ __('problem(s) would stop this from publishing.') }}</strong></div>';
        }

        issues.forEach(function (issue) {
            var cls = issue.severity === 'error' ? 'danger' : 'warning';
            var ico = issue.severity === 'error' ? 'la-times-circle' : 'la-exclamation-triangle';

            html += '<div class="alert alert-' + cls + ' py-2 px-3 small mb-2"><i class="las ' + ico + '"></i> '
                  + escapeHtml(issue.message) + '</div>';
        });

        $('#checkResults').html(html);
    }

    function escapeHtml(s) { return $('<div>').text(s == null ? '' : s).html(); }

    $('#asShort').trigger('change');

})(jQuery);
</script>
@endpush
