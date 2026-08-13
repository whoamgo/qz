@extends('website.layouts.app')
@section('content')
<section class="w-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="w-card"><div class="w-card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <span class="w-cat-icon m-0 mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;">
                            <i class="bi bi-envelope-check" aria-hidden="true"></i>
                        </span>
                        <h1 class="h3 mb-1">Enter your code</h1>
                        <p class="w-muted w-text-sm mb-0">
                            We sent a verification code to <strong>{{ $email }}</strong>.
                        </p>
                    </div>

                    {{-- Fields match ForgotPasswordController::verifyCode: code + email. --}}
                    <form action="{{ route('user.password.verify.code') }}" method="POST">
                        @csrf
                        <input type="hidden" name="email" value="{{ $email }}">

                        <div class="mb-3">
                            <label class="form-label" for="vCode">Verification code</label>
                            <input type="text" id="vCode" name="code" inputmode="numeric" autocomplete="one-time-code"
                                   class="form-control form-control-lg text-center @error('code') is-invalid @enderror"
                                   style="letter-spacing: .4em; font-weight: 700;"
                                   value="{{ old('code') }}" required autofocus>
                            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <button type="submit" class="btn w-btn-primary w-100 btn-lg">Verify code</button>
                    </form>
                </div></div>
                <p class="text-center w-text-sm w-muted mt-3 mb-0">
                    Didn't get it? <a href="{{ route('user.password.request') }}" class="fw-semibold">Request a new code</a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection
