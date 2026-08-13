@extends('website.layouts.app')
@section('content')
<section class="w-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="w-card"><div class="w-card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="w-cat-icon m-0 mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;">
                            <i class="bi bi-key" aria-hidden="true"></i>
                        </span>
                        <h1 class="h3 mb-1">Reset your password</h1>
                        <p class="w-muted w-text-sm mb-0">
                            Enter your username or email and we'll send you a verification code.
                        </p>
                    </div>

                    {{-- Field name `value` matches ForgotPasswordController::sendResetCodeEmail. --}}
                    <form action="{{ route('user.password.email') }}" method="POST" class="verify-gcaptcha">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="fValue">Username or email</label>
                            <input type="text" id="fValue" name="value"
                                   class="form-control @error('value') is-invalid @enderror"
                                   value="{{ old('value') }}" required autofocus>
                            @error('value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <x-captcha label="form-label" class="true" />

                        <button type="submit" class="btn w-btn-primary w-100 btn-lg mt-2">Send reset code</button>
                    </form>
                </div></div>
                <p class="text-center w-text-sm w-muted mt-3 mb-0">
                    Remembered it? <a href="{{ route('user.login') }}" class="fw-semibold">Back to sign in</a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
