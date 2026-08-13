@extends('admin.layouts.app')
@section('panel')
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body p-0">
                    @include('admin.quiz_import.partials.table', ['imports' => $imports])
                </div>
                @if ($imports->hasPages())
                    <div class="card-footer py-4">{{ paginateLinks($imports) }}</div>
                @endif
            </div>
        </div>
    </div>
    <x-confirmation-modal />
@endsection

@push('breadcrumb-plugins')
    <a class="btn btn--lg btn-outline--primary" href="{{ route('admin.quiz-import.index') }}">
        <i class="las la-upload"></i>@lang('New Quiz Import')
    </a>
@endpush
