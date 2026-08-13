@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <form action="" method="GET">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>@lang('Search')</label>
                                    <input type="text" name="search" class="form-control" value="{{ request()->search }}" placeholder="@lang('File name...')">
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="form-group">
                                    <label>@lang('Status')</label>
                                    <select name="status" class="form-control select2">
                                        <option value="">@lang('All')</option>
                                        @foreach ([
                                            'uploaded' => 'Uploaded',
                                            'processing' => 'Processing',
                                            'validation_failed' => 'Validation Failed',
                                            'ready_for_review' => 'Ready for Review',
                                            'approved' => 'Approved',
                                            'completed' => 'Completed',
                                            'failed' => 'Failed',
                                            'cancelled' => 'Cancelled',
                                        ] as $value => $label)
                                            <option value="{{ $value }}" @selected(request('status') == $value)>@lang($label)</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end gap-2">
                                <button type="submit" class="btn btn--primary h-45 flex-grow-1">
                                    <i class="las la-search"></i>
                                </button>
                                <a href="{{ route('admin.question-import.history') }}" class="btn btn--dark h-45 flex-grow-1">
                                    <i class="las la-undo"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-body p-0">
                    @include('admin.question_import.partials.table', ['imports' => $imports])
                </div>
                @if ($imports->hasPages())
                    <div class="card-footer py-4">
                        {{ paginateLinks($imports) }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn--lg btn-outline--primary" href="{{ route('admin.question-import.index') }}">
        <i class="las la-upload"></i>@lang('New Import')
    </a>
@endpush
