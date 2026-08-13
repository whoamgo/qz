@extends('admin.layouts.app')
@section('panel')
    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card bg--primary text-white">
                <div class="card-body py-3 px-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div class="d-flex align-items-center">
                            <a href="{{ route('admin.category.index') }}" class="btn btn-sm btn--light mr-3" title="@lang('Back to Categories')">
                                <i class="las la-arrow-left"></i> @lang('Back')
                            </a>
                            <div class="d-flex align-items-center">
                                <div class="thumb mr-3" style="width: 55px; height: 55px;">
                                    <img class="plugin_bg rounded" src="{{ getImage(getFilePath('category') . '/' . $parent?->image, getFileSize('category')) }}" alt="@lang('image')">
                                </div>
                                <div>
                                    <h5 class="mb-0 text-white">
                                        @if($parent->icon)<i class="{{ __($parent->icon) }} mr-1"></i>@endif
                                        {{ __($parent->name) }}
                                    </h5>
                                    <small class="text-white-50">@lang('Parent Category ID:') {{ $parent->id }} | @lang('Slug:') {{ $parent->slug }}</small>
                                </div>
                            </div>
                        </div>
                        <div>
                            @php echo $parent->statusBadge; @endphp
                            <span class="badge badge--warning ml-2" style="background: rgba(255,255,255,0.2); color: white;">
                                @lang('Total Sub-Categories:') <b>{{ $categories->total() }}</b>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive--md table-responsive">
                        <table class="table--light style--two table">
                            <thead>
                                <tr>
                                    <th>@lang('Sub-Cat ID')</th>
                                    <th>@lang('Image')</th>
                                    <th>@lang('Icon')</th>
                                    <th>@lang('Sub-Category Name')</th>
                                    <th>@lang('Parent') / @lang('Parent ID')</th>
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $category)
                                    <tr>
                                        <td><span class="fw-bold text--primary">{{ __($category->id) }}</span></td>
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
                                            <span class="name fw-bold">{{ __($category->name) }}</span>
                                            <br>
                                            <small class="text-muted">@lang('Slug:') {{ $category->slug }}</small>
                                        </td>
                                        <td>
                                            <span class="fw-bold">{{ __($parent->name) }}</span>
                                            <br>
                                            <small class="text-muted">@lang('ID:') {{ $category->parent_id }}</small>
                                        </td>
                                        <td>
                                            @php
                                                echo $category->statusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                <button class="btn btn-sm btn-outline--primary editButton" data-category="{{ $category }}" data-image="{{ getImage(getFilepath('category') . '/' . $category->image) }}">
                                                    <i class="la la-pencil"></i> @lang('Edit')
                                                </button>
                                                @if ($category->status == Status::ENABLE)
                                                    <button class="btn btn-sm btn-outline--danger confirmationBtn" data-action="{{ route('admin.category.status', $category->id) }}" data-question="@lang('Are you sure to disable this sub-category')?">
                                                        <i class="la la-eye-slash"></i> @lang('Disable')
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-outline--success confirmationBtn" data-action="{{ route('admin.category.status', $category->id) }}" data-question="@lang('Are you sure to enable this sub-category')?">
                                                        <i class="la la-eye"></i> @lang('Enable')
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-muted text-center py-5" colspan="100%">
                                            <i class="las la-folder-open fa-3x text--info"></i><br>
                                            <h6 class="mt-2">@lang('No sub-categories found')</h6>
                                            <small>@lang('Click "Add New Sub-Category" to create one under') <b>{{ __($parent->name) }}</b></small>
                                        </td>
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
                        <div class="alert alert--dark py-2 px-3">
                            <small><i class="las la-sitemap"></i> @lang('Adding under Parent Category:') <b>{{ __($parent->name) }}</b> (@lang('ID:') {{ $parent->id }})</small>
                        </div>
                        <div class="form-group">
                            <label>@lang('Name')</label>
                            <input class="form-control" name="name" type="text" value="{{ old('name') }}" required>
                        </div>
                        <div class="form-group">
                            <label>@lang('Parent Category')</label>
                            <select class="form-control select2-basic" name="parent_id">
                                <option value="">@lang('No Parent (Main Category)')</option>
                                @foreach($parentCategories as $pcat)
                                    <option value="{{ $pcat->id }}" {{ $pcat->id == $parent->id ? 'selected' : '' }}>
                                        {{ __($pcat->name) }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text--warning">@lang('Selected:') <b>{{ __($parent->name) }}</b> @lang('- change if needed')</small>
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
        <i class="las la-arrow-left"></i>@lang('All Categories')
    </a>
    <x-search-form />
    <button class="btn btn--lg btn-outline--primary createButton" type="button">
        <i class="las la-plus"></i>@lang('Add New Sub-Category')
    </button>
@endpush

@push('style')
    <style>
        table .user { justify-content: center; }
        .card.bg--primary { background: linear-gradient(90deg, #2b66f1 0%, #5f4dee 100%); }
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict"

            let modal = $('#categoryModal');
            let defaultParentId = {{ $parent->id }};

            $('.createButton').on('click', function() {
                modal.find('.modal-title').text(`@lang('Add New Sub-Category under') {{ addslashes(__($parent->name)) }}`);
                modal.find('form').attr('action', `{{ route('admin.category.store', '') }}`);
                modal.find('[name=name]').val('');
                modal.find('[name=parent_id]').val(defaultParentId).trigger('change');
                modal.find('[name=icon]').val('');
                modal.modal('show');
            });

            $('.editButton').on('click', function() {
                var category = $(this).data('category');
                modal.find('.modal-title').text(`@lang('Update Sub-Category')`);
                modal.find('form').attr('action', `{{ route('admin.category.store', '') }}/${category.id}`);
                modal.find('[name=name]').val(category.name);
                modal.find('[name=parent_id]').val(category.parent_id || defaultParentId).trigger('change');
                modal.find('[name=icon]').val(category.icon || '');
                modal.find('.image-upload-preview').attr('style', `background-image: url(${$(this).data('image')})`);
                modal.modal('show')
            });

            var defautlImage = `{{ getImage(getFilePath('category'), getFileSize('category')) }}`;
            modal.on('hidden.bs.modal', function() {
                modal.find('.image-upload-preview').attr('style', `background-image: url(${defautlImage})`);
                $('#categoryModal form')[0].reset();
                modal.find('[name=parent_id]').val(defaultParentId).trigger('change');
            });

        })(jQuery);
    </script>
@endpush
