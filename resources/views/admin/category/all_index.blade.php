@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('ID')</th>
                                    <th>@lang('Image')</th>
                                    <th>@lang('Icon')</th>
                                    <th>@lang('Name')</th>
                                    <th>@lang('Type')</th>
                                    <th>@lang('Parent') / @lang('Sub-Cat ID')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                    <tr>
                                        <td>{{ __($category->id) }}</td>
                                        <td>
                                            <div class="user justify-content-center">
                                                <div class="thumb">
                                                    <img class="plugin_bg" src="{{ getImage(getFilePath('category') . '/' . $category?->image, getFileSize('category')) }}" alt="@lang('image')">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            @if($category->icon)
                                                <i class="{{ __($category->icon) }} fa-2x"></i>
                                            @else
                                                <span class="text-muted">@lang('N/A')</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="name">{{ __($category->name) }}</span>
                                        </td>
                                        <td>
                                            @if($category->parent_id)
                                                <span class="badge badge--warning">@lang('Sub-Category')</span>
                                            @else
                                                <span class="badge badge--primary">@lang('Category')</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($category->parent_id)
                                                <span class="fw-bold">{{ __($category->parent_id) }}</span>
                                                <br>
                                                <small class="text-muted">
                                                    @if($category->parent)
                                                        <a href="{{ route('admin.category.sub', $category->parent_id) }}" class="text--info">{{ __($category->parentName) }}</a>
                                                    @else
                                                        {{ __($category->parentName) }}
                                                    @endif
                                                </small>
                                            @else
                                                <a href="{{ route('admin.category.sub', $category->id) }}" class="btn btn-sm btn-outline--info">
                                                    <i class="las la-list-ul"></i> @lang('View Subs')
                                                </a>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                echo $category->statusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                @if(!$category->parent_id)
                                                    <a href="{{ route('admin.category.sub', $category->id) }}" class="btn btn-sm btn-outline--info" title="@lang('View Sub-Categories')">
                                                        <i class="las la-sitemap"></i>
                                                    </a>
                                                @endif
                                                <button class="btn btn-sm btn-outline--primary editButton" data-category="{{ $category }}" data-image="{{ getImage(getFilepath('category') . '/' . $category->image) }}">
                                                    <i class="la la-pencil"></i> @lang('Edit')
                                                </button>
                                                @if ($category->status == Status::ENABLE)
                                                    <button class="btn btn-sm btn-outline--danger confirmationBtn" data-action="{{ route('admin.category.status', $category->id) }}" data-question="@lang('Are you sure to disable this category')?">
                                                        <i class="la la-eye-slash"></i> @lang('Disable')
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success confirmationBtn" data-action="{{ route('admin.category.status', $category->id) }}" data-question="@lang('Are you sure to enable this category')?">
                                                        <i class="la la-eye"></i> @lang('Enable')
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center" colspan="100%">{{ __($emptyMessage) }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($categories->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($categories) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="modal fade" id="categoryModal" role="dialog" tabindex="-1">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"></h4>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form method="post" action="" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="form-group">
                            <label>@lang('Name')</label>
                            <input class="form-control" name="name" type="text" value="{{ old('name') }}" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('Parent Category') <span class="text--info">(@lang('Leave empty for main category'))</span></label>
                            <select class="form-control select2-basic" name="parent_id">
                                <option value="">@lang('No Parent (Main Category)')</option>
                                @foreach($parentCategories as $pcat)
                                    <option value="{{ $pcat->id }}">{{ __($pcat->name) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label>@lang('Icon') <span class="text--info">(@lang('FontAwesome class e.g. fas fa-book'))</span></label>
                            <input class="form-control" name="icon" type="text" value="{{ old('icon') }}" placeholder="fas fa-book">
                            <small class="text--primary">@lang('Find icons at fontawesome.com/icons')</small>
                        </div>
                        <div class="form-group">
                            <label>@lang('Image')</label>
                            <x-image-uploader class="w-100" type="category" image="" :required=false />
                        </div>
                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a href="{{ route('admin.category.index') }}" class="btn btn--lg btn-outline--dark">
        <i class="las la-folder"></i>@lang('Only Parents')
    </a>
    <x-search-form />
    <button class="btn btn--lg btn-outline--primary createButton" type="button">
        <i class="las la-plus"></i>@lang('Add New')
    </button>
@endpush

@push('style')
    <style>
        table .user {
            justify-content: center;
        }
        .badge--primary {
            background: #2b66f1;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        .badge--warning {
            background: #ff9b1a;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict"

            let modal = $('#categoryModal');

            $('.createButton').on('click', function() {
                modal.find('.modal-title').text(`@lang('Add New Category')`);
                modal.find('form').attr('action', `{{ route('admin.category.store', '') }}`);
                modal.find('[name=name]').val('');
                modal.find('[name=parent_id]').val('').trigger('change');
                modal.find('[name=icon]').val('');
                modal.modal('show');
            });

            $('.editButton').on('click', function() {
                var category = $(this).data('category');
                modal.find('.modal-title').text(`@lang('Update Category')`);
                modal.find('form').attr('action', `{{ route('admin.category.store', '') }}/${category.id}`);
                modal.find('[name=name]').val(category.name);
                modal.find('[name=parent_id]').val(category.parent_id || '').trigger('change');
                modal.find('[name=icon]').val(category.icon || '');
                modal.find('.image-upload-preview').attr('style', `background-image: url(${$(this).data('image')})`);
                modal.modal('show')
            });

            var defautlImage = `{{ getImage(getFilePath('category'), getFileSize('category')) }}`;
            modal.on('hidden.bs.modal', function() {
                modal.find('.image-upload-preview').attr('style', `background-image: url(${defautlImage})`);
                $('#categoryModal form')[0].reset();
                modal.find('[name=parent_id]').val('').trigger('change');
            });

        })(jQuery);
    </script>
@endpush
