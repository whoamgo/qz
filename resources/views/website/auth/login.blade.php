@extends('website.layouts.app')

@push('body-class') w-auth-page @endpush

@section('content')
<section class="w-section">
    <div class="container">
        <div class="row justify-content-center align-items-center g-5">

            {{-- Value proposition, hidden on small screens --}}
            <div class="col-lg-5 d-none d-lg-block">
                <h2 class="mb-4">Keep your streak going.</h2>
                <ul class="list-unstyled d-flex flex-column gap-3">
                    @foreach ([
                        ['bi-lightning-charge-fill', 'Earn XP on every quiz', 'Your level and rank build automatically as you practise.'],
                        ['bi-graph-up-arrow', 'See your weak topics', 'Accuracy is tracked per category so you know what to revise.'],
                        ['bi-award-fill', 'Unlock badges', 'Achievements for streaks, accuracy and milestones.'],
                        ['bi-bookmark-fill', 'Save questions', 'Bookmark tricky questions and revisit them any time.'],
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

            <div class="col-md-8 col-lg-5">
                <div class="w-card">
                    <div class="w-card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h1 class="h3 mb-1">Welcome back</h1>
                            <p class="w-muted w-text-sm mb-0">Sign in to continue practising.</p>
                        </div>

                        {{-- Posts to the existing LoginController. The `verify-gcaptcha`
                             class is what the theme's captcha script hooks onto. --}}
                        <form action="{{ route('user.login') }}" method="POST" class="verify-gcaptcha">
                            @csrf

                            <div class="mb-3">
                                <label class="form-label" for="lUser">Username or email</label>
                                <input type="text" id="lUser" name="username"
                                       class="form-control @error('username') is-invalid @enderror"
                                       value="{{ old('username') }}" required autofocus autocomplete="username">
                                @error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="lPass">Password</label>
                                <div class="position-relative">
                                    <input type="password" id="lPass" name="password"
                                           class="form-control @error('password') is-invalid @enderror"
                                           required autocomplete="current-password">
                                    <button type="button" class="btn border-0 position-absolute end-0 top-0 h-100 px-3 wTogglePassword"
                                            data-target="lPass" aria-label="Show password" tabindex="-1">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            {{-- Rendered only when a captcha extension is active. --}}
                            <x-captcha label="form-label" class="true" />

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="lRemember"
                                           @checked(old('remember'))>
                                    <label class="form-check-label w-text-sm" for="lRemember">Remember me</label>
                                </div>
                                <a href="{{ route('user.password.request') }}" class="w-text-sm">Forgot password?</a>
                            </div>

                            <button type="submit" class="btn w-btn-primary w-100 btn-lg">Sign in</button>
                        </form>

                        @if (gs('socialite_credentials') && count(array_filter((array) json_decode(json_encode(gs('socialite_credentials')), true), fn($v) => !empty($v['status']))))
                            <div class="text-center w-muted w-text-sm my-3">or continue with</div>
                            <div class="d-flex gap-2">
                                @foreach (['google' => 'bi-google', 'facebook' => 'bi-facebook', 'linkedin' => 'bi-linkedin'] as $provider => $icon)
                                    @if (@gs('socialite_credentials')->$provider->status ?? false)
                                        <a href="{{ route('user.social.login', $provider) }}" class="btn w-btn-outline flex-grow-1">
                                            <i class="bi {{ $icon }}" aria-hidden="true"></i>
                                        </a>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <p class="text-center w-text-sm w-muted mt-3 mb-0">
                    New here? <a href="{{ route('user.register') }}" class="fw-semibold">Create a free account</a>
                </p>
            </div>
        </div>
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
        });
    </script>
@endpush
