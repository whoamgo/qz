@extends('Template::layouts.' . $layout)
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-12">
                    <div class="card custom--card">
                        <div class="card-header card-header-bg d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                @php echo $myTicket->statusBadge; @endphp
                                <h5 class="card-title mt-0">
                                    [@lang('Ticket')#{{ $myTicket->ticket }}] {{ $myTicket->subject }}
                                </h5>
                            </div>
                            @if ($myTicket->status != Status::TICKET_CLOSE && $myTicket->user)
                                <button class="btn btn--danger close-button btn--sm confirmationBtn" type="button" data-question="@lang('Are you sure to close this ticket?')" data-action="{{ route('ticket.close', $myTicket->id) }}"><i class="fas fa-times-circle"></i>
                                </button>
                            @endif
                        </div>
                        <div class="card-body">
                            <form method="post" class="disableSubmission" action="{{ route('ticket.reply', $myTicket->id) }}" enctype="multipart/form-data">
                                @csrf
                                <div class="row justify-content-between">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <textarea name="message" class="form--control md-style" rows="4" required>{{ old('message') }}</textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-9">
                                        <button type="button" class="btn btn--secondary btn--sm addAttachment mt-3"> <i class="fas fa-plus"></i> @lang('Add Attachment') </button>
                                        <p class="mt-2"><span class="text--info">@lang('Max 5 files can be uploaded | Maximum upload size is ' . convertToReadableSize(ini_get('upload_max_filesize')) . ' | Allowed File Extensions: .jpg, .jpeg, .png, .pdf, .doc, .docx')</span></p>
                                        <div class="row fileUploadsContainer gy-4 mt-2">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="w-100 btn btn--base mt-3" type="submit"><i class="la la-fw la-lg la-reply"></i> @lang('Reply')
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h6 class="mb-2 mt-4">@lang('Previous Replies')</h6>
                    <div class="list support-list">
                        @foreach ($messages as $message)
                            @if ($message->admin_id == 0)
                                <div class="support-card">
                                    <div class="support-card__head">
                                        <h6 class="support-card__title">
                                            {{ $message->ticket->name }}
                                        </h6>
                                        <span class="support-card__date">
                                            <code class="xsm-text text-muted"><i class="far fa-clock"></i>
                                                {{ $message->created_at->format('dS F Y @ H:i') }}</code>
                                        </span>
                                    </div>
                                    <div class="support-card__body">
                                        <p class="support-card__body-text">
                                            {{ $message->message }}
                                        </p>

                                        @if ($message->attachments->count() > 0)
                                            <ul class="list list--row support-card__list d-flex gap-2 flex-wrap mt-3">
                                                @foreach ($message->attachments as $k => $image)
                                                    <li>
                                                        <a class="support-card__file" href="{{ route('ticket.download', encrypt($image->id)) }}">
                                                            <span class="support-card__file-icon">
                                                                <i class="far fa-file-alt"></i>
                                                            </span>
                                                            <span class="support-card__file-text">
                                                                @lang('Attachment') {{ ++$k }}
                                                            </span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="support-card">
                                    <div class="support-card__head">
                                        <h6 class="support-card__title">
                                            {{ $message->admin->name }}
                                        </h6>
                                        <span class="support-card__date">
                                            <code class="xsm-text text-muted"><i class="far fa-clock"></i>
                                                {{ $message->created_at->format('dS F Y @ H:i') }}</code>
                                        </span>

                                    </div>
                                    <div class="support-card__body">
                                        <p class="support-card__body-text">
                                            {{ $message->message }}
                                        </p>

                                        @if ($message->attachments->count() > 0)
                                            <ul class="list list--row support-card__list d-flex gap-2 flex-wrap mt-3">
                                                @foreach ($message->attachments as $k => $image)
                                                    <li>
                                                        <a class="support-card__file" href="{{ route('ticket.download', encrypt($image->id)) }}">
                                                            <span class="support-card__file-icon">
                                                                <i class="far fa-file-alt"></i>
                                                            </span>
                                                            <span class="support-card__file-text">
                                                                @lang('Attachment') {{ ++$k }}
                                                            </span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                </div>
            </div>
        </div>
    </div>

    <x-confirmation-modal className="custom--modal" btnClose="btn-close" submitBtn="btn btn--base btn--sm" closeBtn="btn btn--danger btn--sm" />
@endsection

@push('style')
    <style>
        .input-group-text:focus {
            box-shadow: none !important;
        }

        .reply-bg {
            background-color: #ffd96729
        }

        .empty-message img {
            width: 120px;
            margin-bottom: 15px;
        }

        .support-card {
            border: 1px solid hsl(var(--border-color));
            border-radius: 12px;
            background: hsl(var(--gray-two));
            margin-bottom: 12px;
        }

        .support-card:last-child {
            margin-bottom: 0;
        }

        .support-card__title {
            margin-top: 0;
            margin-bottom: 0.5rem;
        }

        @media screen and (min-width: 768px) {
            .support-card__title {
                margin-bottom: 0;
            }
        }

        .support-card__head {
            padding: 12px 15px;
            border-bottom: 1px solid hsl(var(--border-color)/0.4);
        }

        @media screen and (min-width: 768px) {
            .support-card__head {
                display: flex;
                justify-content: space-between;
            }
        }

        .support-card__date {
            display: block;
            line-height: 1;
        }

        .support-card__body {
            padding: 12px 15px;
        }

        .support-card__body-text {
            font-size: 14px;
            margin-bottom: 0;
        }

        .support-card__list {
            --gap: 0.5rem;
        }

        @media screen and (min-width: 768px) {
            .support-card__list {
                margin-top: 1rem;
            }
        }

        .support-card__file {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 7px 10px;
            border: 1px solid hsl(var(--base) / 0.2);
            border-radius: 5px;
            font-size: 14px;
            line-height: 1;
            color: hsl(var(--base));
        }

        .support-card__file:hover {
            background: hsl(var(--base));
            color: hsl(var(--black));
        }
    </style>
@endpush

@push('script')
    <script>
        (function($) {
            "use strict";
            var fileAdded = 0;
            $('.addAttachment').on('click', function() {
                fileAdded++;
                if (fileAdded == 5) {
                    $(this).attr('disabled', true)
                }
                $(".fileUploadsContainer").append(`
                    <div class="col-lg-4 col-md-12 removeFileInput">
                        <div class="input-group ">
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
        })(jQuery);
    </script>
@endpush
