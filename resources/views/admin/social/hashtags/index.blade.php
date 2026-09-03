@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Services\Social\SocialPermission;
    $canManage = SocialPermission::allows(SocialPermission::MANAGE_LIBRARY);
@endphp

@section('panel')

<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label">@lang('Search')</label>
                <input type="text" name="search" class="form-control form-control-sm" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <button class="btn btn--primary btn-sm w-100">@lang('Search')</button>
            </div>
        </form>
    </div>

    <div class="card-body">
        <div class="row g-3">
            @forelse($groups as $group)
                <div class="col-lg-6">
                    <div class="social-platform h-100">
                        <div class="social-platform__head">
                            <div class="flex-grow-1">
                                <div class="social-platform__name">{{ $group->name }}</div>
                                <div class="social-platform__meta">
                                    {{ $group->hashtag_count }} @lang('tags')
                                    @if($group->platform) · {{ App\Constants\SocialStatus::platformName($group->platform) }} @endif
                                    · @lang('used') {{ $group->usage_count }}x
                                </div>
                            </div>
                            <span class="badge badge--{{ $group->is_active ? 'success' : 'secondary' }}">
                                {{ $group->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>

                        <div class="social-platform__body">
                            @if($group->description)
                                <p class="social-platform__meta">{{ $group->description }}</p>
                            @endif
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($group->hashtags as $tag)
                                    <span class="social-chip">{{ $tag }}</span>
                                @endforeach
                            </div>
                        </div>

                        <div class="social-platform__foot">
                            <button class="btn btn-sm btn-outline--primary copyTags" data-tags="{{ $group->asString() }}">
                                <i class="las la-copy"></i> @lang('Copy')
                            </button>
                            @if($canManage)
                                <button class="btn btn-sm btn-outline--dark editGroup"
                                        data-group="{{ json_encode([
                                            'id'          => $group->id,
                                            'name'        => $group->name,
                                            'description' => $group->description,
                                            'hashtags'    => $group->asString(),
                                            'platform'    => $group->platform,
                                            'is_active'   => $group->is_active,
                                        ]) }}">
                                    <i class="las la-edit"></i> @lang('Edit')
                                </button>
                                <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                        data-action="{{ route('admin.social.hashtags.delete', $group->id) }}"
                                        data-question="@lang('Delete this hashtag group?')">
                                    <i class="las la-trash"></i>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    @include('admin.social.partials.empty', [
                        'icon' => 'las la-hashtag',
                        'title' => 'No hashtag groups yet',
                        'message' => 'Save the sets you reuse - Daily Quiz, SSC, Banking - and drop them into any post with one click.',
                    ])
                </div>
            @endforelse
        </div>
    </div>

    @if($groups->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($groups) }}</div>
    @endif
</div>

@if($canManage)
<div class="modal fade" id="groupModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" id="groupForm" action="{{ route('admin.social.hashtags.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="groupTitle">@lang('New hashtag group')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="form-group mb-3">
                    <label class="form-label">@lang('Name') <span class="text--danger">*</span></label>
                    <input type="text" name="name" class="form-control" required maxlength="150" placeholder="@lang('Daily Quiz')">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">@lang('Description')</label>
                    <input type="text" name="description" class="form-control" maxlength="1000">
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">@lang('Hashtags') <span class="text--danger">*</span></label>
                    <textarea name="hashtags" class="form-control" rows="4" required
                              placeholder="#dailyquiz #gk #currentaffairs #ssc"></textarea>
                    <small class="social-counter">
                        @lang('Paste them however you like - spaces, commas or new lines. They are cleaned, lowercased and de-duplicated on save.')
                    </small>
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">@lang('Platform (optional)')</label>
                    <select name="platform" class="form-control">
                        <option value="">@lang('All platforms')</option>
                        @foreach($platforms as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="grpActive" checked>
                    <label class="form-check-label" for="grpActive">@lang('Active')</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                <button class="btn btn--primary">@lang('Save group')</button>
            </div>
        </form>
    </div>
</div>
@endif

<x-confirmation-modal />

@endsection

@push('breadcrumb-plugins')
    @if($canManage)
        <button class="btn btn-sm btn-outline--primary" id="newGroup"><i class="las la-plus"></i> @lang('New Group')</button>
    @endif
@endpush

@push('script')
<script>
"use strict";
(function ($) {
    var $form = $('#groupForm');

    $('#newGroup').on('click', function () {
        $form[0].reset();
        $form.attr('action', '{{ route('admin.social.hashtags.store') }}');
        $('#groupTitle').text('{{ __('New hashtag group') }}');
        new bootstrap.Modal(document.getElementById('groupModal')).show();
    });

    $(document).on('click', '.editGroup', function () {
        var data = $(this).data('group');

        $form[0].reset();
        $form.attr('action', '{{ url('admin/social/hashtags') }}/' + data.id + '/update');
        $('#groupTitle').text('{{ __('Edit hashtag group') }}');

        ['name', 'description', 'hashtags', 'platform'].forEach(function (f) {
            $form.find('[name="' + f + '"]').val(data[f] || '');
        });
        $form.find('[name="is_active"]').prop('checked', !!data.is_active);

        new bootstrap.Modal(document.getElementById('groupModal')).show();
    });

    $(document).on('click', '.copyTags', function () {
        var tags = String($(this).data('tags'));

        // navigator.clipboard needs a secure context; fall back to a hidden
        // textarea so this still works on plain HTTP during setup.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(tags).then(done);
        } else {
            var $temp = $('<textarea>').val(tags).css({ position: 'fixed', opacity: 0 }).appendTo('body');
            $temp[0].select();
            try { document.execCommand('copy'); done(); } catch (e) { /* nothing to do */ }
            $temp.remove();
        }

        function done() {
            iziToast.success({ message: '{{ __('Hashtags copied.') }}', position: 'topRight' });
        }
    });
})(jQuery);
</script>
@endpush
