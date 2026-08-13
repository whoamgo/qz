@extends('website.layouts.app')

@push('body-class') w-auth-page @endpush

@section('content')
<section class="w-section">
    <div class="container">
        @if (!gs('registration'))
            <div class="row justify-content-center">
                <div class="col-md-7 col-lg-5">
                    <div class="w-card">
                        <x-website::empty-state icon="bi-lock"
                            title="Registration is currently closed"
                            message="New sign-ups are disabled at the moment. Please check back later."
                            :actionUrl="route('user.login')" actionLabel="Sign in instead" />
                    </div>
                </div>
            </div>
        @else
            @php
                // Same source the legacy register form uses for the policy links.
                $policyPages = getContent('policy_pages.element', false, orderById: true);
            @endphp

            <div class="row justify-content-center align-items-center g-5">
                <div class="col-lg-5 d-none d-lg-block">
                    <h2 class="mb-4">Start practising in under a minute.</h2>
                    <ul class="list-unstyled d-flex flex-column gap-3">
                        @foreach ([
                            ['bi-infinity', 'Free to use', 'Every quiz marked Free can be attempted at no cost.'],
                            ['bi-lightning-charge-fill', 'Earn XP and levels', 'Progress is calculated on the server after each quiz.'],
                            ['bi-fire', 'Build a daily streak', 'Complete one quiz a day to keep your streak alive.'],
                            ['bi-clipboard-data', 'Detailed answer review', 'See the correct answer and a written explanation.'],
                        ] as [$icon, $title, $text])
                            <li class="d-flex gap-3">
                                <span class="w-cat-icon m-0 flex-shrink-0" style="width:44px;height:44px;font-size:1.05rem;">
                                    <i class="bi {{ $icon }}" aria-hidden="true"></i>
                                </span>
                                <span>
                                    <strong class="d-block">{{ $title }}</strong>
                                    <span class="w-text-sm w-muted">{{ $text }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="col-md-9 col-lg-6">
                    <div class="w-card">
                        <div class="w-card-body p-4 p-md-5">
                            <div class="text-center mb-4">
                                <h1 class="h3 mb-1">Create your free account</h1>
                                <p class="w-muted w-text-sm mb-0">Track progress, earn XP and unlock badges.</p>
                            </div>

                            {{-- Fields match RegisterController::validator() exactly:
                                 firstname, lastname, email, password (+confirmation),
                                 agree and captcha. Username is not collected — the
                                 controller does not accept or generate one. --}}
                            <form action="{{ route('user.register') }}" method="POST" class="verify-gcaptcha">
                                @csrf

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="rFirst">First name <span class="text-danger">*</span></label>
                                        <input type="text" id="rFirst" name="firstname"
                                               class="form-control @error('firstname') is-invalid @enderror"
                                               value="{{ old('firstname') }}" required autofocus autocomplete="given-name">
                                        @error('firstname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="rLast">Last name <span class="text-danger">*</span></label>
                                        <input type="text" id="rLast" name="lastname"
                                               class="form-control @error('lastname') is-invalid @enderror"
                                               value="{{ old('lastname') }}" required autocomplete="family-name">
                                        @error('lastname')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label" for="rEmail">Email address <span class="text-danger">*</span></label>
                                        <input type="email" id="rEmail" name="email"
                                               class="form-control @error('email') is-invalid @enderror"
                                               value="{{ old('email') }}" required autocomplete="email">
                                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="rPass">Password <span class="text-danger">*</span></label>
                                        <div class="position-relative">
                                            <input type="password" id="rPass" name="password"
                                                   class="form-control @error('password') is-invalid @enderror"
                                                   required autocomplete="new-password">
                                            <button type="button" class="btn border-0 position-absolute end-0 top-0 h-100 px-3 wTogglePassword"
                                                    data-target="rPass" aria-label="Show password" tabindex="-1">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="rPass2">Confirm password <span class="text-danger">*</span></label>
                                        <input type="password" id="rPass2" name="password_confirmation"
                                               class="form-control" required autocomplete="new-password">
                                    </div>
                                </div>

                                <p class="w-text-xs w-muted mt-2 mb-3">
                                    @if (gs('secure_password'))
                                        Use at least 6 characters with upper and lower case, a number and a symbol.
                                    @else
                                        Use at least 6 characters.
                                    @endif
                                </p>

                                <x-captcha label="form-label" class="true" />

                                @if (gs('agree'))
                                    <div class="form-check mb-4">
                                        <input class="form-check-input @error('agree') is-invalid @enderror"
                                               name="agree" type="checkbox" id="rAgree" @checked(old('agree')) required>
                                        <label class="form-check-label w-text-sm" for="rAgree">
                                            I agree with
                                            @forelse ($policyPages as $policy)
                                                <a href="{{ route('policy.pages', $policy->slug) }}" target="_blank">{{ __($policy->data_values->title) }}</a>@if(!$loop->last), @endif
                                            @empty
                                                the terms and conditions
                                            @endforelse
                                        </label>
                                        @error('agree')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                @endif

                                <button type="submit" class="btn w-btn-primary w-100 btn-lg">Create account</button>
                            </form>
                        </div>
                    </div>

                    <p class="text-center w-text-sm w-muted mt-3 mb-0">
                        Already registered? <a href="{{ route('user.login') }}" class="fw-semibold">Sign in</a>
                    </p>
                </div>
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
    <script>
        jQuery(function ($) {
            $('.wTogglePassword').on('click', function () {
                var $field = $('#' + $(this).data('target'));
                var isPw = $field.attr('type') === 'password';
                $field.attr('type', isPw ? 'text' : 'password');
                $(this).find('i').toggleClass('bi-eye bi-eye-slash');
                $(this).attr('aria-label', isPw ? 'Hide password' : 'Show password');
            });

            // Confirmation must match before the form is allowed to submit.
            $('form.verify-gcaptcha').on('submit', function (e) {
                var pw = $('#rPass').val(), pw2 = $('#rPass2').val();
                if (pw && pw2 && pw !== pw2) {
                    e.preventDefault();
                    $('#rPass2').addClass('is-invalid');
                    window.WSite.toast('The two passwords do not match.', 'error');
                }
            });
        });
    </script>
@endpush
