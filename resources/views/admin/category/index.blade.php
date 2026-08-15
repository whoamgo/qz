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
                                    <th>@lang('Status')</th>
                                    <th>@lang('Action')</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($categories as $key=>$category)
                                    <tr>
                                        <td>{{ ++$key }} ==</td>
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
                                            <span class="name fw-bold">{{ __($category->name) }} ({{$category->id}})</span>
                                        </td>
                                        
                                        <td>
                                            @php
                                                echo $category->statusBadge;
                                            @endphp
                                        </td>
                                        <td>
                                            <div class="button--group">
                                                 <a href="{{ route('admin.category.sub', $category->id) }}" class="btn btn-sm btn-outline--info" title="@lang('View Sub-Categories')">
                                                    <i class="las la-sitemap"></i>
                                                </a> 
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

                        <hr>
                        <h6 class="mb-3 text--primary"><i class="las la-search"></i> @lang('SEO Settings') <span class="text--info">(@lang('optional'))</span></h6>
                        <div class="form-group">
                            <label>@lang('Meta Title')</label>
                            <input class="form-control" name="meta_title" type="text" maxlength="255" value="{{ old('meta_title') }}" placeholder="@lang('e.g. General Knowledge Quiz - Online MCQs | Quiz Mitra')">
                            <small class="text--muted">@lang('Leave empty to auto-generate from the category name.')</small>
                        </div>
                        <div class="form-group">
                            <label>@lang('Meta Description')</label>
                            <textarea class="form-control" name="meta_description" rows="2" maxlength="320" placeholder="@lang('A short, unique description shown in Google search results.')">{{ old('meta_description') }}</textarea>
                            <small class="text--muted">@lang('Recommended 150–160 characters.')</small>
                        </div>
                        <div class="form-group">
                            <label>@lang('Meta Keywords')</label>
                            <input class="form-control" name="meta_keywords" type="text" maxlength="255" value="{{ old('meta_keywords') }}" placeholder="@lang('comma, separated, keywords')">
                        </div>

                        <button class="btn btn--primary w-100 h-45" type="submit">@lang('Submit')</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="importModal" role="dialog" tabindex="-1">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">@lang('Import Categories')</h4>
                    <button class="close" data-bs-dismiss="modal" type="button" aria-label="Close">
                        <i class="las la-times"></i>
                    </button>
                </div>
                <form id="importForm" method="post" action="{{ route('admin.category.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="alert alert--info p-3 mb-3">
                            <h6 class="mb-2"><i class="las la-info-circle"></i> @lang('Instructions')</h6>
                            <ul class="mb-0 pl-3">
                                <li>@lang('Use columns:') <b>category</b> @lang('(parent name) +') <b>sub_category</b> @lang('(child name)')</li>
                                <li>@lang('OR use single:') <b>name</b> @lang('column')</li>
                                <li>@lang('Use') <b>slug</b> @lang('for custom URL, leave empty for auto')</li>
                                <li>@lang('Use') <b>parent_id</b> @lang('OR') <b>category_id</b> @lang('OR') <b>parent_category</b> @lang('for sub-categories')</li>
                                <li>@lang('status: 1 = Enable, 0 = Disable')</li>
                                <li>@lang('icon: FontAwesome class e.g. fas fa-book')</li>
                                <li><b>@lang('Auto-creates parent categories if they do not exist!')</b></li>
                            </ul>
                        </div>

                        <div class="card border--primary mb-3">
                            <div class="card-header bg--primary text-white py-2">
                                <h6 class="mb-0"><i class="las la-file-csv"></i> @lang('Your CSV Format (Recommended)')</h6>
                            </div>
                            <div class="card-body p-2">
                                <code class="text--primary">id, category_id, parent_id, category, sub_category, slug, status, icon</code>
                            </div>
                        </div>

                        <div class="card border mb-3">
                            <div class="card-header bg-light py-2">
                                <h6 class="mb-0"><i class="las la-table"></i> @lang('Example Rows')</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th><small>category</small></th>
                                                <th><small>sub_category</small></th>
                                                <th><small>slug</small></th>
                                                <th><small>status</small></th>
                                                <th><small>icon</small></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><small>Current Affairs</small></td>
                                                <td><small>Daily Current Affairs</small></td>
                                                <td><small>daily-current-affairs</small></td>
                                                <td><small>1</small></td>
                                                <td><small>fas fa-calendar-day</small></td>
                                            </tr>
                                            <tr>
                                                <td><small>Current Affairs</small></td>
                                                <td><small>National Affairs</small></td>
                                                <td><small>national-affairs</small></td>
                                                <td><small>1</small></td>
                                                <td><small>fas fa-flag</small></td>
                                            </tr>
                                            <tr>
                                                <td><small>Science</small></td>
                                                <td><small></small></td>
                                                <td><small>science</small></td>
                                                <td><small>1</small></td>
                                                <td><small>fas fa-flask</small></td>
                                            </tr>
                                            <tr>
                                                <td><small>Science</small></td>
                                                <td><small>Physics</small></td>
                                                <td><small>physics</small></td>
                                                <td><small>1</small></td>
                                                <td><small>fas fa-atom</small></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <a class="btn btn-sm btn-outline--success" href="{{ route('admin.category.example.csv') }}">
                                <i class="las la-download"></i> @lang('Download Example CSV')
                            </a>
                            <small class="text-muted">@lang('Max 2MB, CSV or TXT format')</small>
                        </div>

                        <div class="form-group">
                            <label>@lang('Select CSV File') <span class="text--danger">*</span></label>
                            <input class="form-control" id="csvFile" name="file" type="file" accept=".csv,.txt" required>
                        </div>

                        <div id="importLoader" style="display:none;" class="mb-3 p-4 border rounded bg-light">
                            <div class="d-flex align-items-center mb-3">
                                <div class="spinner-border text--primary mr-3" role="status">
                                    <span class="sr-only">Loading...</span>
                                </div>
                                <h6 id="importStatusText" class="mb-0 text--primary font-weight-bold">@lang('Initializing import...')</h6>
                            </div>

                            <div class="mb-2 d-flex justify-content-between">
                                <span id="importProgressText" class="text-muted">0 / 0 (0%)</span>
                                <span id="importPercentText" class="font-weight-bold text--primary">0%</span>
                            </div>

                            <div class="progress" style="height: 25px; border-radius: 12px; overflow: hidden;">
                                <div id="importProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg--primary" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>

                            <div class="row mt-3 text-center">
                                <div class="col-4">
                                    <div class="p-2 bg-white border rounded">
                                        <small class="text-muted">@lang('Total')</small>
                                        <h6 id="statTotal" class="mb-0 font-weight-bold">0</h6>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-white border rounded">
                                        <small class="text-success">@lang('Imported')</small>
                                        <h6 id="statImported" class="mb-0 font-weight-bold text-success">0</h6>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-2 bg-white border rounded">
                                        <small class="text-warning">@lang('Skipped')</small>
                                        <h6 id="statSkipped" class="mb-0 font-weight-bold text-warning">0</h6>
                                    </div>
                                </div>
                            </div>

                            <div id="importErrorsBox" style="display:none; max-height: 150px; overflow-y: auto;" class="mt-3 p-2 border bg-white rounded">
                                <small class="text-danger font-weight-bold">@lang('Issues / Warnings:')</small>
                                <ul id="importErrorsList" class="mb-0 pl-3 text-xs"></ul>
                            </div>
                        </div>

                        <button id="importSubmitBtn" class="btn btn--primary w-100 h-45" type="submit">
                            <i class="las la-file-import"></i> @lang('Import CSV')
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <x-search-form />
    <a class="btn btn--lg btn-outline--dark" href="{{ route('admin.category.all') }}">
        <i class="las la-layer-group"></i>@lang('View All')
    </a>
    <button class="btn btn--lg btn-outline--info importButton" type="button">
        <i class="las la-file-import"></i>@lang('Import CSV')
    </button>
    <a class="btn btn--lg btn-outline--success" href="{{ route('admin.category.example.csv') }}">
        <i class="las la-download"></i>@lang('Example CSV')
    </a>
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
            let importModal = $('#importModal');
            let importRunning = false;

            $('.createButton').on('click', function() {
                modal.find('.modal-title').text(`@lang('Add New Category')`);
                modal.find('form').attr('action', `{{ route('admin.category.store', '') }}`);
                modal.find('[name=name]').val('');
                modal.find('[name=parent_id]').val('').trigger('change');
                modal.find('[name=icon]').val('');
                modal.find('[name=meta_title]').val('');
                modal.find('[name=meta_description]').val('');
                modal.find('[name=meta_keywords]').val('');
                modal.modal('show');
            });

            $('.editButton').on('click', function() {
                var category = $(this).data('category');
                modal.find('.modal-title').text(`@lang('Update Category')`);
                modal.find('form').attr('action', `{{ route('admin.category.store', '') }}/${category.id}`);
                modal.find('[name=name]').val(category.name);
                modal.find('[name=parent_id]').val(category.parent_id || '').trigger('change');
                modal.find('[name=icon]').val(category.icon || '');
                modal.find('[name=meta_title]').val(category.meta_title || '');
                modal.find('[name=meta_description]').val(category.meta_description || '');
                modal.find('[name=meta_keywords]').val(category.meta_keywords || '');
                modal.find('.image-upload-preview').attr('style', `background-image: url(${$(this).data('image')})`);
                modal.modal('show')
            });

            $('.importButton').on('click', function() {
                if (!importRunning) {
                    resetImportUI();
                }
                importModal.modal('show');
            });

            function resetImportUI() {
                $('#importLoader').hide();
                $('#importSubmitBtn').prop('disabled', false);
                $('#importStatusText').text('@lang('Initializing import...')');
                $('#importProgressText').text('0 / 0 (0%)');
                $('#importPercentText').text('0%');
                $('#importProgressBar').css('width', '0%').attr('aria-valuenow', 0);
                $('#statTotal').text('0');
                $('#statImported').text('0');
                $('#statSkipped').text('0');
                $('#importErrorsBox').hide();
                $('#importErrorsList').html('');
                $('#importForm')[0].reset();
                $.post(`{{ route('admin.category.import.reset') }}`, {
                    _token: `{{ csrf_token() }}`
                });
            }

            function updateProgressUI(data) {
                const pct = data.percent || 0;
                $('#importProgressBar').css('width', pct + '%').attr('aria-valuenow', pct);
                $('#importPercentText').text(pct + '%');
                $('#importProgressText').text((data.current || 0) + ' / ' + (data.total || 0) + ' (' + pct + '%)');
                $('#statTotal').text(data.total || 0);
                $('#statImported').text(data.imported || 0);
                $('#statSkipped').text(data.skipped || 0);

                if (data.errors && data.errors.length > 0) {
                    $('#importErrorsBox').show();
                    const list = $('#importErrorsList');
                    list.html('');
                    const showErrors = data.errors.slice(0, 20);
                    showErrors.forEach(function(err) {
                        list.append('<li class="text-danger small">' + err + '</li>');
                    });
                    if (data.errors.length > 20) {
                        list.append('<li class="text-muted small">...and ' + (data.errors.length - 20) + ' more issues</li>');
                    }
                }
            }

            function pollProgress() {
                $.get(`{{ route('admin.category.import.progress') }}`, function(data) {
                    updateProgressUI(data);

                    if (data.status === 'running') {
                        setTimeout(pollProgress, 600);
                    } else if (data.status === 'completed') {
                        importComplete(data);
                    }
                }).fail(function() {
                    setTimeout(pollProgress, 1000);
                });
            }

            function processBatches(file) {
                function runBatch() {
                    const fd = new FormData();
                    fd.append('_token', `{{ csrf_token() }}`);

                    $.ajax({
                        url: `{{ route('admin.category.import.process') }}`,
                        method: 'POST',
                        data: fd,
                        processData: false,
                        contentType: false,
                        cache: false,
                        success: function(data) {
                            updateProgressUI(data);

                            if (data.status === 'running') {
                                setTimeout(runBatch, 250);
                            } else if (data.status === 'completed') {
                                importComplete(data);
                            } else if (data.fatal) {
                                importRunning = false;
                                $('#importSubmitBtn').prop('disabled', false);
                                iziToast.error({ title: '@lang('Error')', message: (data.errors && data.errors[0]) || '@lang('Import failed')', position: 'topRight' });
                            }
                        },
                        error: function() {
                            setTimeout(runBatch, 800);
                        }
                    });
                }
                runBatch();
            }

            function importComplete(data) {
                importRunning = false;
                $('#importSubmitBtn').prop('disabled', false);
                const pct = 100;
                $('#importProgressBar').css('width', pct + '%').attr('aria-valuenow', pct).removeClass('progress-bar-animated');
                $('#importPercentText').text('100%');

                if (data.status === 'completed' || !data.total) {
                    $('#importStatusText').html('<span class="text-success"><i class="las la-check-circle"></i> @lang('Import Completed Successfully!')</span>');
                }

                setTimeout(function() {
                    iziToast.success({
                        title: '@lang('Import Done')',
                        message: (data.imported || 0) + ' @lang('imported'), ' + (data.skipped || 0) + ' @lang('skipped')',
                        position: 'topRight'
                    });
                    location.reload();
                }, 2000);
            }

            $('#importForm').on('submit', function(e) {
                e.preventDefault();
                if (importRunning) return;

                const fileInput = $('#csvFile')[0];
                if (!fileInput.files || !fileInput.files[0]) {
                    iziToast.error({ title: '@lang('Error')', message: '@lang('Please select a CSV file')', position: 'topRight' });
                    return;
                }

                const file = fileInput.files[0];
                importRunning = true;

                $('#importLoader').show();
                $('#importSubmitBtn').prop('disabled', true);
                $('#importProgressBar').addClass('progress-bar-animated');
                $('#importStatusText').text('@lang('Uploading & scanning CSV file...')');

                const fd = new FormData(this);
                $.ajax({
                    url: `{{ route('admin.category.import.ajax') }}`,
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    cache: false,
                    success: function(res) {
                        if (!res.success) {
                            importRunning = false;
                            $('#importSubmitBtn').prop('disabled', false);
                            iziToast.error({ title: '@lang('Error')', message: res.message || '@lang('Import failed')', position: 'topRight' });
                            $('#importLoader').hide();
                            return;
                        }

                        if (res.total === 0) {
                            importComplete({ total: 0, imported: 0, skipped: 0, errors: [] });
                            return;
                        }

                        updateProgressUI({
                            total: res.total,
                            current: 0,
                            imported: 0,
                            skipped: 0,
                            errors: [],
                            percent: 0
                        });

                        $('#importStatusText').text('@lang('Processing rows... Please wait')');
                        processBatches(file);
                        setTimeout(pollProgress, 1000);
                    },
                    error: function(xhr) {
                        importRunning = false;
                        $('#importSubmitBtn').prop('disabled', false);
                        $('#importLoader').hide();
                        let msg = '@lang('An error occurred during upload')';
                        try {
                            const resp = JSON.parse(xhr.responseText);
                            if (resp.message) msg = resp.message;
                            if (resp.errors) {
                                const keys = Object.keys(resp.errors);
                                if (keys.length) msg = resp.errors[keys[0]][0];
                            }
                        } catch(e) {}
                        iziToast.error({ title: '@lang('Error')', message: msg, position: 'topRight' });
                    }
                });
            });

            var defautlImage = `{{ getImage(getFilePath('category'), getFileSize('category')) }}`;
            modal.on('hidden.bs.modal', function() {
                modal.find('.image-upload-preview').attr('style', `background-image: url(${defautlImage})`);
                $('#categoryModal form')[0].reset();
                modal.find('[name=parent_id]').val('').trigger('change');
            });

            importModal.on('hidden.bs.modal', function() {
                if (!importRunning) {
                    resetImportUI();
                }
            });

        })(jQuery);
    </script>
@endpush
