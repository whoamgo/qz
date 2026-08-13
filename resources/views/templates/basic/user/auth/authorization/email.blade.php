@extends('Template::layouts.frontend')
@section('content')
    <div class="my-120">
        <div class="container">
            <div class="d-flex justify-content-center">
                <div class="verification-code-wrapper">
                    <div class="verification-area">
                        <form action="{{ route('user.verify.email') }}" method="POST" class="submit-form">
                            @csrf
                            <p class="mb-4">@lang('A 6 digit verification code sent to your email address'): {{ showEmailAddress(auth()->user()->email) }}</p>

                            @include('Template::partials.verification_code')

                            <button type="submit" class="w-100 btn btn--base">@lang('Submit')</button>

                            <div class="mt-4">
                                <p>
                                    @lang('If you don\'t get any code'), <span class="countdown-wrapper">@lang('try again after') <span id="countdown" class="fw-bold">--</span> @lang('seconds')</span> <a href="{{ route('user.send.verify.code', 'email') }}" class="try-again-link d-none text--base"> @lang('Try again')</a>
                                </p>
                                <a href="{{ route('user.logout') }}" class="text--base">@lang('Logout')</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script')
    <script>
        var distance = Number("{{ isset($user->ver_code_send_at) ? $user->ver_code_send_at->addMinutes(2)->timestamp - time() : '' }}");
        var x = setInterval(function() {
            distance--;
            document.getElementById("countdown").innerHTML = distance;
            if (distance <= 0) {
                clearInterval(x);
                document.querySelector('.countdown-wrapper').classList.add('d-none');
                document.querySelector('.try-again-link').classList.remove('d-none');
            }
        }, 1000);

        $('#verification-code').on('input', function() {
            var inputLength = $(this).val().length;
            if (inputLength == 6) {
                $(this).addClass('cursor-color');
            } else {
                $(this).removeClass('cursor-color');
            }
        });
    </script>
@endpush


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
