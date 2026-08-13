@extends('website.layouts.app')
@section('content')
<section class="w-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="w-card"><div class="w-card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="w-cat-icon m-0 mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;">
                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        </span>
                        <h1 class="h3 mb-1">Choose a new password</h1>
                        <p class="w-muted w-text-sm mb-0">Make it different from your previous password.</p>
                    </div>

                    {{-- token and email are supplied by ResetPasswordController::showResetForm. --}}
                    <form action="{{ route('user.password.update') }}" method="POST">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="mb-3">
                            <label class="form-label" for="nPass">New password</label>
                            <div class="position-relative">
                                <input type="password" id="nPass" name="password"
                                       class="form-control @error('password') is-invalid @enderror"
                                       required autofocus autocomplete="new-password">
                                <button type="button" class="btn border-0 position-absolute end-0 top-0 h-100 px-3 wTogglePassword"
                                        data-target="nPass" aria-label="Show password" tabindex="-1">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="nPass2">Confirm new password</label>
                            <input type="password" id="nPass2" name="password_confirmation"
                                   class="form-control" required autocomplete="new-password">
                        </div>

                        <p class="w-text-xs w-muted mb-3">
                            @if (gs('secure_password'))
                                Use at least 6 characters with upper and lower case, a number and a symbol.
                            @else
                                Use at least 6 characters.
                            @endif
                        </p>

                        <button type="submit" class="btn w-btn-primary w-100 btn-lg">Update password</button>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    <script>
        jQuery(function ($) {
            $('.wTogglePassword').on('click', function () {
                var $f = $('#' + $(this).data('target'));
                var isPw = $f.attr('type') === 'password';
                $f.attr('type', isPw ? 'text' : 'password');
                $(this).find('i').toggleClass('bi-eye bi-eye-slash');
            });
        });
    </script>
@endpush
