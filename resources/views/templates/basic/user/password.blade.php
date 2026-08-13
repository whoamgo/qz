@extends('Template::layouts.master')

@section('content')
    <div class="my-120">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card custom--card">
                        <div class="card-body">
                            <form method="post">
                                @csrf
                                <div class="form-group">
                                    <label class="form--label">@lang('Current Password')</label>
                                    <input type="password" class="form--control md-style" name="current_password" required autocomplete="current-password">
                                </div>
                                <div class="form-group">
                                    <label class="form--label">@lang('Password')</label>
                                    <input type="password" class="form--control md-style @if (gs('secure_password')) secure-password @endif" name="password" required autocomplete="current-password">
                                </div>
                                <div class="form-group">
                                    <label class="form--label">@lang('Confirm Password')</label>
                                    <input type="password" class="form--control md-style" name="password_confirmation" required autocomplete="current-password">
                                </div>
                                <button type="submit" class="w-100 btn btn--base">@lang('Submit')</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@if (gs('secure_password'))
    @push('script-lib')
        <script src="{{ asset('assets/global/js/secure_password.js') }}"></script>
    @endpush
@endif
@push('style')
    <style>
        .input-popup p.error {
            color: hsl(var(--white) / .75);
        }

        .input-popup p.success {
            color: hsl(var(--white));
        }

        .hover-input-popup .input-popup {
            bottom: 80%;
        }
    </style>
@endpush
