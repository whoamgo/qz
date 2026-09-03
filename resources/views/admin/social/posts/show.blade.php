@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Constants\SocialStatus as S;
    use App\Services\Social\SocialPermission;
    $can = SocialPermission::current();

    $published = $post->targets->where('status', S::PUBLISHED)->count();
    $failed    = $post->targets->where('status', S::FAILED)->count();
    $pending   = $post->targets->whereIn('status', [S::QUEUED, S::SCHEDULED, S::PUBLISHING, S::RETRYING])->count();
@endphp

@section('panel')

{{-- ============================================= Publishing result banner --}}
@if(in_array($post->status, [S::PUBLISHED, S::PARTIALLY_PUBLISHED, S::FAILED, S::PUBLISHING, S::QUEUED, S::RETRYING], true))
    @php
        $banner = match ($post->status) {
            S::PUBLISHED           => ['success', 'las la-check-circle',        __('Publishing complete'),  __('Every selected platform published successfully.')],
            S::PARTIALLY_PUBLISHED => ['warning', 'las la-exclamation-circle',  __('Partially published'),  __('Some platforms published and some did not. Nothing here is marked successful unless it actually succeeded.')],
            S::FAILED              => ['danger',  'las la-times-circle',        __('Publishing failed'),    __('No platform accepted this post. The reason for each is below.')],
            default                => ['info',    'las la-sync',                __('Publishing in progress'), __('Jobs are running in the background. You can close this page - it will carry on.')],
        };
    @endphp

    <div class="alert alert-{{ $banner[0] }}">
        <div class="d-flex align-items-start gap-3">
            <i class="{{ $banner[1] }}" style="font-size:26px"></i>
            <div class="flex-grow-1">
                <h6 class="mb-1">{{ $banner[2] }}</h6>
                <p class="mb-0 small">{{ $banner[3] }}</p>
                <p class="mb-0 small mt-1">
                    <strong>{{ $published }}</strong> @lang('published') ·
                    <strong>{{ $failed }}</strong> @lang('failed') ·
                    <strong>{{ $pending }}</strong> @lang('in progress')
                </p>
            </div>
            @if(in_array($post->status, [S::PUBLISHING, S::QUEUED, S::RETRYING], true))
                <button class="btn btn-sm btn-outline--dark" onclick="location.reload()">
                    <i class="las la-redo"></i> @lang('Refresh')
                </button>
            @endif
        </div>
    </div>
@endif

<div class="row g-4">
    {{-- ============================================== Per-platform results --}}
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">@lang('Platform results')</h6></div>
            <div class="card-body p-0">
                <div class="social-result">
                    @forelse($post->targets as $target)
                        <div class="social-result__row">
                            <div class="social-platform__icon" style="background: {{ S::platformColor($target->platform) }}; width:36px;height:36px;font-size:18px;">
                                <i class="{{ S::platformIcon($target->platform) }}"></i>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="social-platform__name">{{ $target->platform_name }}</div>
                                <div class="social-platform__meta">
                                    {{ $target->account?->display_name ?: __('No account') }}
                                    @if($target->published_at) · {{ showDateTime($target->published_at, 'd M Y, h:i A') }} @endif
                                </div>

                                @if($target->status === S::FAILED || $target->status === S::RETRYING)
                                    <div class="alert alert-danger py-2 px-3 small mt-2 mb-0">
                                        {{ $target->error_message ?: __('The platform rejected this post.') }}
                                        @if($target->next_attempt_at)
                                            <div class="mt-1">
                                                <i class="las la-clock"></i>
                                                @lang('Next automatic attempt'): {{ showDateTime($target->next_attempt_at, 'd M, h:i A') }}
                                                (@lang('attempt') {{ $target->attempts + 1 }})
                                            </div>
                                        @elseif($target->attempts)
                                            <div class="mt-1 social-counter">
                                                @lang('Gave up after') {{ $target->attempts }} @lang('attempt(s).')
                                            </div>
                                        @endif
                                    </div>
                                @endif

                                @if($target->platform_url)
                                    <a href="{{ $target->platform_url }}" target="_blank" rel="noopener noreferrer"
                                       class="small d-inline-block mt-1">
                                        <i class="las la-external-link-alt"></i> @lang('View on') {{ $target->platform_name }}
                                    </a>
                                @endif

                                @if($target->status === S::PUBLISHED && $target->metrics)
                                    <div class="small text-muted mt-1">
                                        <i class="las la-eye"></i> {{ number_format($target->metric('views')) }}
                                        <i class="las la-heart ms-2"></i> {{ number_format($target->metric('likes')) }}
                                        <i class="las la-comment ms-2"></i> {{ number_format($target->metric('comments')) }}
                                        <i class="las la-share ms-2"></i> {{ number_format($target->metric('shares')) }}
                                    </div>
                                @endif
                            </div>

                            <div class="social-result__status">
                                <span class="badge badge--{{ $target->status_class }}">{{ $target->status_name }}</span>

                                @if($target->attemptLogs->count())
                                    <button type="button" class="btn btn-sm btn-outline--dark view-attempts"
                                            data-target="{{ $target->id }}"
                                            data-url="{{ route('admin.social.posts.target.attempts', $target->id) }}">
                                        @lang('Details')
                                    </button>
                                @endif

                                @if(($can[SocialPermission::RETRY] ?? false) && in_array($target->status, [S::FAILED, S::CANCELLED], true))
                                    <form action="{{ route('admin.social.posts.target.retry', $target->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn--warning">
                                            <i class="las la-redo"></i> @lang('Retry')
                                        </button>
                                    </form>
                                @endif

                                @if($target->error_code === 'token_expired' || $target->error_code === 'permission_denied')
                                    <a href="{{ route('admin.social.accounts.index') }}" class="btn btn-sm btn-outline--primary">
                                        @lang('Reconnect')
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        @include('admin.social.partials.empty', [
                            'icon' => 'las la-hand-pointer',
                            'title' => 'No platforms selected',
                            'message' => 'Edit this post to choose where it should be published.',
                        ])
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ------------------------------------------------------- Content --}}
        <div class="card">
            <div class="card-header"><h6 class="mb-0">@lang('Content')</h6></div>
            <div class="card-body">
                @if($post->title)
                    <h6>{{ $post->title }}</h6>
                @endif
                <p style="white-space: pre-wrap">{{ $post->caption }}</p>

                @if($post->description)
                    <hr>
                    <p class="small text-muted" style="white-space: pre-wrap">{{ $post->description }}</p>
                @endif

                @if($post->hashtagList())
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($post->hashtagList() as $tag)
                            <span class="social-chip">{{ $tag }}</span>
                        @endforeach
                    </div>
                @endif

                @if($post->media->count())
                    <div class="social-media-grid mt-3">
                        @foreach($post->media as $media)
                            <div class="social-media-item">
                                @if($media->thumbnail_url)
                                    <img src="{{ $media->thumbnail_url }}" alt="">
                                @else
                                    <div class="social-media-placeholder"><i class="las la-file-video"></i></div>
                                @endif
                                <span class="social-media-item__type">{{ strtoupper($media->pivot->role) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($post->quiz_url || $post->website_url)
                    <div class="mt-3 small">
                        @if($post->quiz_url)
                            <div><i class="las la-link"></i> <a href="{{ $post->quiz_url }}" target="_blank" rel="noopener">{{ $post->quiz_url }}</a></div>
                        @endif
                        @if($post->website_url)
                            <div><i class="las la-link"></i> <a href="{{ $post->website_url }}" target="_blank" rel="noopener">{{ $post->website_url }}</a></div>
                        @endif
                        @if($post->utm_enabled)
                            <div class="social-counter mt-1">@lang('UTM parameters are added per platform when publishing.')</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ================================================== Sidebar/actions --}}
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0">@lang('Status')</h6></div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><td>@lang('Status')</td><td class="text-end"><span class="badge badge--{{ $post->status_class }}">{{ $post->status_name }}</span></td></tr>
                    <tr><td>@lang('Approval')</td><td class="text-end">{{ ucfirst(str_replace('_', ' ', $post->approval_status)) }}</td></tr>
                    <tr><td>@lang('Type')</td><td class="text-end">{{ __(S::CONTENT_TYPES[$post->content_type] ?? $post->content_type) }}</td></tr>
                    <tr><td>@lang('Scheduled')</td><td class="text-end">{{ $post->scheduled_at ? showDateTime($post->scheduled_at, 'd M Y, h:i A') : '—' }}</td></tr>
                    <tr><td>@lang('Published')</td><td class="text-end">{{ $post->published_at ? showDateTime($post->published_at, 'd M Y, h:i A') : '—' }}</td></tr>
                    <tr><td>@lang('Campaign')</td><td class="text-end">{{ $post->campaign?->name ?: '—' }}</td></tr>
                    <tr><td>@lang('Created by')</td><td class="text-end">{{ $post->creator?->name ?: '—' }}</td></tr>
                    @if($post->approver)
                        <tr><td>@lang('Approved by')</td><td class="text-end">{{ $post->approver->name }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- Pre-flight issues stay visible after publishing: they explain a
             failure as often as they predict one. --}}
        @if($issues)
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">@lang('Platform checks')</h6></div>
                <div class="card-body">
                    @foreach($issues as $issue)
                        <div class="alert alert-{{ $issue['severity'] === 'error' ? 'danger' : 'warning' }} py-2 px-3 small mb-2">
                            <strong>{{ S::platformName($issue['platform']) }}:</strong> {{ $issue['message'] }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h6 class="mb-0">@lang('Actions')</h6></div>
            <div class="card-body d-grid gap-2">
                @if(($can[SocialPermission::EDIT] ?? false) && $post->isEditable())
                    <a href="{{ route('admin.social.posts.edit', $post->id) }}" class="btn btn--primary">
                        <i class="las la-edit"></i> @lang('Edit post')
                    </a>
                @endif

                @if(($can[SocialPermission::APPROVE] ?? false) && $post->approval_status === S::APPROVAL_PENDING)
                    <form action="{{ route('admin.social.posts.approve', $post->id) }}" method="POST">
                        @csrf
                        <button class="btn btn--success w-100"><i class="las la-check"></i> @lang('Approve')</button>
                    </form>
                    <button type="button" class="btn btn-outline--danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="las la-times"></i> @lang('Reject')
                    </button>
                @endif

                @if(($can[SocialPermission::PUBLISH] ?? false) && !in_array($post->status, [S::PUBLISHED, S::PUBLISHING, S::QUEUED], true))
                    <form action="{{ route('admin.social.posts.publish', $post->id) }}" method="POST" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf
                        <button class="btn btn--success w-100"><i class="las la-paper-plane"></i> @lang('Publish now')</button>
                    </form>
                @endif

                @if(($can[SocialPermission::SCHEDULE] ?? false) && !in_array($post->status, [S::PUBLISHED, S::PUBLISHING], true))
                    <button type="button" class="btn btn-outline--primary" data-bs-toggle="modal" data-bs-target="#rescheduleModal">
                        <i class="las la-clock"></i> {{ $post->scheduled_at ? __('Reschedule') : __('Schedule') }}
                    </button>
                @endif

                @if(($can[SocialPermission::SCHEDULE] ?? false) && in_array($post->status, [S::SCHEDULED, S::QUEUED, S::APPROVED], true))
                    <button type="button" class="btn btn-outline--dark confirmationBtn"
                            data-action="{{ route('admin.social.posts.cancel', $post->id) }}"
                            data-question="@lang('Cancel this scheduled post?')">
                        <i class="las la-ban"></i> @lang('Cancel schedule')
                    </button>
                @endif

                @if($can[SocialPermission::CREATE] ?? false)
                    <button type="button" class="btn btn-outline--dark confirmationBtn"
                            data-action="{{ route('admin.social.posts.duplicate', $post->id) }}"
                            data-question="@lang('Duplicate this post as a new draft?')">
                        <i class="las la-copy"></i> @lang('Duplicate')
                    </button>
                @endif

                @if($can[SocialPermission::DELETE] ?? false)
                    <button type="button" class="btn btn-outline--danger confirmationBtn"
                            data-action="{{ route('admin.social.posts.delete', $post->id) }}"
                            data-question="@lang('Delete this post? Content already live on a platform is not removed.')">
                        <i class="las la-trash"></i> @lang('Delete')
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ------------------------------------------------------------- Modals --}}
<div class="modal fade" id="rescheduleModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST"
              action="{{ $post->scheduled_at ? route('admin.social.posts.reschedule', $post->id) : route('admin.social.posts.schedule', $post->id) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">@lang('Schedule post')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <label class="form-label">@lang('Publish at')</label>
                <input type="datetime-local" name="scheduled_at" class="form-control" required
                       value="{{ $post->scheduled_at?->format('Y-m-d\TH:i') }}">
                <small class="social-counter">
                    @lang('Times are in'): <strong>{{ App\Models\Social\SocialSetting::config()->timezone() }}</strong>
                </small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                <button class="btn btn--primary">@lang('Save')</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" action="{{ route('admin.social.posts.reject', $post->id) }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">@lang('Reject post')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <label class="form-label">@lang('Reason (shown to the author)')</label>
                <textarea name="reason" class="form-control" rows="3" maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                <button class="btn btn--danger">@lang('Reject')</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="attemptsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Publish attempts')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body" id="attemptsBody"></div>
        </div>
    </div>
</div>

<x-confirmation-modal />

@endsection

@push('script')
<script>
"use strict";
(function ($) {
    $(document).on('click', '.view-attempts', function () {
        var $body = $('#attemptsBody').html('<div class="social-skeleton mb-2"></div><div class="social-skeleton" style="width:60%"></div>');
        new bootstrap.Modal(document.getElementById('attemptsModal')).show();

        $.get($(this).data('url'), function (res) {
            var html = '<p class="mb-3"><strong>' + res.platform + '</strong> — ' + res.status + '</p>';

            if (!res.attempts.length) {
                html += '<p class="text-muted">{{ __('No attempts recorded yet.') }}</p>';
            } else {
                html += '<div class="table-responsive"><table class="table table--light style--two mb-0"><thead><tr>'
                      + '<th>#</th><th>{{ __('Result') }}</th><th>{{ __('Detail') }}</th><th>{{ __('HTTP') }}</th>'
                      + '<th>{{ __('Took') }}</th><th>{{ __('When') }}</th></tr></thead><tbody>';

                res.attempts.forEach(function (a) {
                    var cls = a.status === 'success' ? 'success' : 'danger';
                    html += '<tr><td>' + a.attempt + '</td>'
                          + '<td><span class="badge badge--' + cls + '">' + a.status + '</span></td>'
                          + '<td>' + $('<div>').text(a.message || a.code || '—').html() + '</td>'
                          + '<td>' + (a.http || '—') + '</td>'
                          + '<td>' + (a.duration ? (a.duration + ' ms') : '—') + '</td>'
                          + '<td>' + (a.at || '—') + '</td></tr>';
                });

                html += '</tbody></table></div>';
            }

            $body.html(html);
        }).fail(function () {
            $body.html('<div class="alert alert-danger mb-0">{{ __('Could not load the attempt history.') }}</div>');
        });
    });

    // A post that is mid-flight settles within seconds; refresh once so the
    // admin sees the outcome without having to think about it.
    @if(in_array($post->status, [App\Constants\SocialStatus::PUBLISHING, App\Constants\SocialStatus::QUEUED], true))
        setTimeout(function () { location.reload(); }, 20000);
    @endif
})(jQuery);
</script>
@endpush
