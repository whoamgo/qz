@extends('Template::layouts.master')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-12">
                    <div class="card custom--card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                                <h5 class="topbar__title">{{ __($pageTitle) }}</h5>
                                <a href="{{ route('ticket.index') }}" class="btn btn--sm btn--base"><i class="las la-lg la-ticket-alt"></i>
                                    <span class="ms-2">@lang('My Tickets')</span></a>
                            </div>
                        </div>

                        <div class="card-body">
                            <form action="{{ route('ticket.store') }}" class="disableSubmission" method="post" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label class="form--label">@lang('Subject')</label>
                                        <input type="text" name="subject" value="{{ old('subject') }}" class="form--control md-style" required>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label class="form--label">@lang('Priority')</label>
                                        <select name="priority" class="form--control select2" data-minimum-results-for-search="-1" required>
                                            <option value="3">@lang('High')</option>
                                            <option value="2">@lang('Medium')</option>
                                            <option value="1">@lang('Low')</option>
                                        </select>
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="form--label">@lang('Message')</label>
                                        <textarea name="message" id="inputMessage" rows="6" class="form--control md-style" required>{{ old('message') }}</textarea>
                                    </div>

                                    <div class="col-md-9">
                                        <button type="button" class="btn btn--secondary btn--sm addAttachment my-2"> <i class="fas fa-plus"></i> @lang('Add Attachment') </button>
                                        <p class="mt-2"><span class="text--info">@lang('Max 5 files can be uploaded | Maximum upload size is ' . convertToReadableSize(ini_get('upload_max_filesize')) . ' | Allowed File Extensions: .jpg, .jpeg, .png, .pdf, .doc, .docx')</span></p>
                                        <div class="row fileUploadsContainer gy-4 mt-2">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="w-100 btn btn--base my-2" type="submit"><i class="las la-paper-plane"></i> @lang('Submit')
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('style')
    <style>
        .input-group-text:focus {
            box-shadow: none !important;
        }
    </style>
@endpush

@push('style-lib')
    <link rel="stylesheet" href="{{ asset('assets/global/css/select2.min.css') }}">
@endpush

@push('script-lib')
    <script src="{{ asset('assets/global/js/select2.min.js') }}"></script>
@endpush


@push('script')
    <script>
        (function($) {
            "use strict";
            $('.select2').each(function(index, element) {
                if (!$(element).parent().hasClass('select2-wrapper')) {
                    $(element).wrap('<div class="select2-wrapper"></div>');
                }

                $(element).select2({
                    dropdownParent: $(element).closest('.select2-wrapper')
                });
            });
            var fileAdded = 0;
            $('.addAttachment').on('click', function() {
                fileAdded++;
                if (fileAdded == 5) {
                    $(this).attr('disabled', true)
                }
                $(".fileUploadsContainer").append(`
                    <div class="col-lg-4 col-md-12 removeFileInput">
                        <div class="input-group input--group">
                            <input type="file" name="attachments[]" class="form-control form--control md-style" accept=".jpeg,.jpg,.png,.pdf,.doc,.docx" required>
                            <button type="button" class="input-group-text removeFile border-transparent text-danger"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                `)
            });
            $(document).on('click', '.removeFile', function() {
                $('.addAttachment').removeAttr('disabled', true)
                fileAdded--;
                $(this).closest('.removeFileInput').remove();
            });

            $('.select2').each(function(index, element) {
                $(element).select2();
            });

        })(jQuery);
    </script>
@endpush
