@extends('Template::layouts.frontend')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="d-flex justify-content-center">
                <div class="verification-code-wrapper">
                    <div class="verification-area">
                        <h5 class="mb-4 ">@lang('2FA Verification')</h5>
                        <form action="{{ route('user.2fa.verify') }}" method="POST" class="submit-form">
                            @csrf

                            @include('Template::partials.verification_code')

                            <button type="submit" class="w-100 btn btn--gradient">@lang('Submit')</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('style')
    <style>
        .verification-code-wrapper {
            border-radius: 12px;
            overflow: hidden;
            -webkit-transition: all 0.3s linear;
            transition: all 0.3s linear;
            background-color: hsl(var(--section-bg));
            border: 1px solid hsl(var(--border-color));
        }

        .verification-code span {
            background: transparent;
            border: solid 1px hsl(var(--base)/0.5);
            color: hsl(var(--base)/0.5);
        }

        .verification-code input {
            color: #fff !important;
        }


        .verification-code::after {
            background-color: transparent;
        }

        .verification-code::after {
            display: none;
        }

        .cursor-color {
            caret-color: transparent;
        }
    </style>
@endpush
