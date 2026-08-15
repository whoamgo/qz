@extends('website.layouts.app')

@section('content')
<section class="w-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">

                {{-- Progress: registration is step 1, this is step 2. --}}
                <div class="d-flex align-items-center justify-content-center gap-2 gap-md-3 mb-4 flex-wrap">
                    <span class="d-inline-flex align-items-center gap-2 w-text-sm text-success">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Account created
                    </span>
                    <span class="w-muted d-none d-sm-inline" aria-hidden="true">&mdash;&mdash;</span>
                    <span class="d-inline-flex align-items-center gap-2 w-text-sm fw-semibold" style="color: var(--w-primary);">
                        <i class="bi bi-2-circle-fill" aria-hidden="true"></i> Complete your profile
                    </span>
                    <span class="w-muted d-none d-sm-inline" aria-hidden="true">&mdash;&mdash;</span>
                    <span class="d-inline-flex align-items-center gap-2 w-text-sm w-muted">
                        <i class="bi bi-3-circle" aria-hidden="true"></i> Start practising
                    </span>
                </div>

                <div class="w-card">
                    <div class="w-card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h1 class="h3 mb-1">Almost there, {{ $user->firstname }}</h1>
                            <p class="w-muted w-text-sm mb-0">
                                Pick a username and confirm your contact details to finish setting up your account.
                            </p>
                        </div>

                        {{-- Field names match UserController::userDataSubmit() exactly:
                             username, country, country_code, mobile_code, mobile,
                             address, city, state, zip. --}}
                        <form method="POST" action="{{ route('user.data.submit') }}" id="wUserDataForm">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="uUsername">
                                    Username <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">&#64;</span>
                                    <input type="text" id="uUsername" name="username"
                                           class="form-control wCheckUser @error('username') is-invalid @enderror"
                                           value="{{ old('username') }}" required autofocus
                                           minlength="3" autocomplete="username"
                                           aria-describedby="uUsernameHelp">
                                </div>
                                <div id="uUsernameHelp" class="w-text-xs w-muted mt-1">
                                    At least 3 characters. Lowercase letters, numbers and underscores only.
                                </div>
                                <small class="text--danger d-block usernameExist" role="alert"></small>
                                @error('username')<div class="text--danger w-text-sm">{{ $message }}</div>@enderror
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="uCountry">
                                        Country <span class="text-danger">*</span>
                                    </label>
                                    <select name="country" id="uCountry" class="form-select @error('country') is-invalid @enderror" required>
                                        @foreach ($countries as $key => $country)
                                            <option value="{{ $country->country }}"
                                                    data-code="{{ $key }}"
                                                    data-mobile_code="{{ $country->dial_code }}"
                                                    @selected(old('country') === $country->country)>{{ __($country->country) }}</option>
                                        @endforeach
                                    </select>
                                    @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="uMobile">
                                        Mobile number <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text mobile-code" id="uMobileCode">+</span>
                                        <input type="hidden" name="mobile_code">
                                        <input type="hidden" name="country_code">
                                        <input type="text" id="uMobile" name="mobile" inputmode="numeric"
                                               class="form-control wCheckUser @error('mobile') is-invalid @enderror"
                                               value="{{ old('mobile') }}" required autocomplete="tel-national">
                                    </div>
                                    <small class="text--danger d-block mobileExist" role="alert"></small>
                                    @error('mobile')<div class="text--danger w-text-sm">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <hr class="my-4">
                            <p class="w-text-sm w-muted mb-3">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                                Address details are optional &mdash; you can add them later from your profile settings.
                            </p>

                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label" for="uAddress">Address</label>
                                    <input type="text" id="uAddress" name="address" class="form-control"
                                           value="{{ old('address') }}" autocomplete="street-address">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="uCity">City</label>
                                    <input type="text" id="uCity" name="city" class="form-control"
                                           value="{{ old('city') }}" autocomplete="address-level2">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="uState">State</label>
                                    <input type="text" id="uState" name="state" class="form-control"
                                           value="{{ old('state') }}" autocomplete="address-level1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="uZip">ZIP / Postcode</label>
                                    <input type="text" id="uZip" name="zip" class="form-control"
                                           value="{{ old('zip') }}" autocomplete="postal-code">
                                </div>
                            </div>

                            <button type="submit" class="btn w-btn-primary w-100 btn-lg mt-4" id="wUserDataSubmit">
                                Finish setup <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <p class="text-center w-text-sm w-muted mt-3 mb-0">
                    Signed in as {{ $user->email }} &middot;
                    <a href="{{ route('user.logout') }}">Sign out</a>
                </p>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    <script src="{{ wAsset('assets/web/js/user-data.js') }}"></script>
    <script>
        jQuery(function () {
            window.WUserData.init({
                checkUrl: "{{ route('user.checkUser') }}",
                detectedCode: @json($mobileCode ?: null)
            });
        });
    </script>
@endpush
