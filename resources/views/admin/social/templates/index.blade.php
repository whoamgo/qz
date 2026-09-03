@extends('admin.layouts.app')
@include('admin.social.partials.head')

@php
    use App\Services\Social\SocialPermission;
    $canManage = SocialPermission::allows(SocialPermission::MANAGE_LIBRARY);
@endphp

@section('panel')

<div class="social-note mb-4">
    <strong>@lang('Template variables.')</strong>
    @lang('Write') <code>{{ '{{ variable }}' }}</code> @lang('anywhere in a template and it is replaced when the template is used. Available:')
    <div class="d-flex flex-wrap gap-1 mt-2">
        @foreach($variables as $variable)
            <code class="social-chip">{{ '{{ ' . $variable . ' }}' }}</code>
        @endforeach
    </div>
    <small class="d-block mt-2 social-counter">
        @lang('Substitution is plain text replacement, not code - a template can never execute anything on the server.')
    </small>
</div>

<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label">@lang('Category')</label>
                <select name="category" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">@lang('All')</option>
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" @selected(request('category') === $key)>@lang($label)</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">@lang('Platform')</label>
                <select name="platform" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="">@lang('All')</option>
                    @foreach($platforms as $key => $label)
                        <option value="{{ $key }}" @selected(request('platform') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table--light style--two mb-0">
                <thead>
                    <tr>
                        <th>@lang('Template')</th>
                        <th>@lang('Category')</th>
                        <th>@lang('Platform')</th>
                        <th>@lang('Variables')</th>
                        <th>@lang('Used')</th>
                        <th>@lang('Status')</th>
                        <th>@lang('Action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($templates as $template)
                        <tr>
                            <td>
                                <strong>{{ $template->name }}</strong>
                                <small class="d-block text-muted">{{ strLimit($template->caption_template, 70) }}</small>
                            </td>
                            <td>{{ $template->category_name }}</td>
                            <td>{{ $template->platform ? App\Constants\SocialStatus::platformName($template->platform) : __('All') }}</td>
                            <td>
                                @forelse((array) $template->variables as $variable)
                                    <span class="social-chip">{{ $variable }}</span>
                                @empty
                                    <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>{{ $template->usage_count }}</td>
                            <td>
                                <span class="badge badge--{{ $template->is_active ? 'success' : 'secondary' }}">
                                    {{ $template->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-outline--info previewTemplate"
                                            data-url="{{ route('admin.social.templates.preview', $template->id) }}">
                                        <i class="las la-eye"></i>
                                    </button>
                                    @if($canManage)
                                        <button class="btn btn-sm btn-outline--dark editTemplate"
                                                data-template="{{ json_encode($template->only([
                                                    'id','name','category','platform','title_template','caption_template',
                                                    'description_template','hashtag_template','cta_template','is_active',
                                                ])) }}">
                                            <i class="las la-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline--danger confirmationBtn"
                                                data-action="{{ route('admin.social.templates.delete', $template->id) }}"
                                                data-question="@lang('Delete this template?')">
                                            <i class="las la-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                @include('admin.social.partials.empty', [
                                    'icon' => 'las la-file-alt',
                                    'title' => 'No templates yet',
                                    'message' => 'Save the copy you write repeatedly - daily quiz, current affairs, new blog - and reuse it with variables.',
                                ])
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($templates->hasPages())
        <div class="card-footer py-4">{{ paginateLinks($templates) }}</div>
    @endif
</div>

@if($canManage)
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" id="templateForm" action="{{ route('admin.social.templates.store') }}">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="templateTitle">@lang('New template')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">@lang('Name') <span class="text--danger">*</span></label>
                        <input type="text" name="name" class="form-control" required maxlength="150">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">@lang('Category')</label>
                        <select name="category" class="form-control">
                            @foreach($categories as $key => $label)
                                <option value="{{ $key }}">@lang($label)</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">@lang('Platform')</label>
                        <select name="platform" class="form-control">
                            <option value="">@lang('All platforms')</option>
                            @foreach($platforms as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">@lang('Title template')</label>
                        <input type="text" name="title_template" class="form-control" maxlength="500"
                               placeholder="{{ '{{ title }} | Quiz Mitra' }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">@lang('Caption template')</label>
                        <textarea name="caption_template" class="form-control" rows="5"
                                  placeholder="{{ '{{ title }}\n\n{{ caption }}\n\n{{ cta }} {{ quiz_url }}\n\n{{ hashtags }}' }}"></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">@lang('Description template')</label>
                        <textarea name="description_template" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">@lang('Hashtag template')</label>
                        <input type="text" name="hashtag_template" class="form-control" maxlength="2000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">@lang('CTA template')</label>
                        <input type="text" name="cta_template" class="form-control" maxlength="255">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="tplActive" checked>
                            <label class="form-check-label" for="tplActive">@lang('Active')</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn--dark" data-bs-dismiss="modal">@lang('Cancel')</button>
                <button class="btn btn--primary">@lang('Save template')</button>
            </div>
        </form>
    </div>
</div>
@endif

<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">@lang('Rendered with sample data')</h5>
                <button type="button" class="close" data-bs-dismiss="modal"><i class="las la-times"></i></button>
            </div>
            <div class="modal-body" id="previewBody"></div>
        </div>
    </div>
</div>

<x-confirmation-modal />

@endsection

@push('breadcrumb-plugins')
    @if($canManage)
        <button class="btn btn-sm btn-outline--primary" id="newTemplate"><i class="las la-plus"></i> @lang('New Template')</button>
    @endif
@endpush

@push('script')
<script>
"use strict";
(function ($) {
    var $form = $('#templateForm');

    $('#newTemplate').on('click', function () {
        $form[0].reset();
        $form.attr('action', '{{ route('admin.social.templates.store') }}');
        $('#templateTitle').text('{{ __('New template') }}');
        new bootstrap.Modal(document.getElementById('templateModal')).show();
    });

    $(document).on('click', '.editTemplate', function () {
        var data = $(this).data('template');

        $form[0].reset();
        $form.attr('action', '{{ url('admin/social/templates') }}/' + data.id + '/update');
        $('#templateTitle').text('{{ __('Edit template') }}');

        Object.keys(data).forEach(function (key) {
            var $field = $form.find('[name="' + key + '"]');
            if (!$field.length) return;
            if ($field.attr('type') === 'checkbox') $field.prop('checked', !!data[key]);
            else $field.val(data[key] || '');
        });

        new bootstrap.Modal(document.getElementById('templateModal')).show();
    });

    $(document).on('click', '.previewTemplate', function () {
        var $body = $('#previewBody').html('<div class="social-skeleton mb-2"></div><div class="social-skeleton" style="width:70%"></div>');
        new bootstrap.Modal(document.getElementById('previewModal')).show();

        $.post($(this).data('url'), { _token: '{{ csrf_token() }}' }, function (res) {
            var html = '';
            Object.keys(res.rendered).forEach(function (key) {
                if (!res.rendered[key]) return;
                html += '<div class="mb-3"><label class="form-label text-uppercase small text-muted">' + key + '</label>'
                      + '<div class="social-note" style="white-space:pre-wrap">' + $('<div>').text(res.rendered[key]).html() + '</div></div>';
            });
            $body.html(html || '<p class="text-muted mb-0">{{ __('This template is empty.') }}</p>');
        });
    });
})(jQuery);
</script>
@endpush
