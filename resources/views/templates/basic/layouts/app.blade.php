<!doctype html>
<html lang="{{ config('app.locale') }}" itemscope itemtype="http://schema.org/WebPage">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title> {{ gs()->siteName(__($pageTitle)) }}</title>
    @include('partials.seo')

    <link href="{{ asset('assets/global/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/global/css/all.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/global/css/line-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/custom.css') }}">

    @stack('style-lib')

    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/fontasosome.min.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/slick.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/odometer.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/animate.css') }}">
    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/main.css') }}">

    @stack('style')

    <link rel="stylesheet" href="{{ asset(activeTemplate(true) . 'css/color.php') }}?color={{ gs('base_color') }}&secondColor={{ gs('secondary_color') }}">
</head>

@php echo loadExtension('google-analytics') @endphp

<body>

    @stack('fbComment')


    @include('Template::partials.preloader')

    <div class="body-overlay"></div>

    <div class="sidebar-overlay"></div>

    <a class="scroll-top"><i class="fas fa-angle-up"></i></a>

    @if (!in_array(request()->route()->getName(), ['user.login', 'user.register', 'maintenance']))
        @include('Template::partials.header')
        @if (!request()->routeIs('home'))
            @include('Template::partials.breadcrumb')
        @endif
    @endif

    <main>
        @yield('app')
    </main>

    @if (!in_array(request()->route()->getName(), ['user.login', 'user.register', 'maintenance']))
        @include('Template::partials.footer')
    @endif


    @php
        $cookie = App\Models\Frontend::where('data_keys', 'cookie.data')->first();
    @endphp

    @if ($cookie->data_values->status == Status::ENABLE && !\Cookie::get('gdpr_cookie'))
        <div class="cookies-card text-center hide">
            <div class="cookies-card__icon bg--base">
                <i class="las la-cookie-bite"></i>
            </div>
            <p class="mt-4 cookies-card__content">{{ $cookie->data_values->short_desc }} <a href="{{ route('cookie.policy') }}" class="text--base" target="_blank">@lang('learn more')</a></p>
            <div class="cookies-card__btn mt-4">
                <a href="javascript:void(0)" class="btn btn--base w-100 policy">@lang('Allow')</a>
            </div>
        </div>
    @endif

    <script src="{{ asset('assets/global/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/global/js/bootstrap.bundle.min.js') }}"></script>

    @stack('script-lib')

    <script src="{{ asset(activeTemplate(true) . 'js/slick.min.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/odometer.min.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/viewport.jquery.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/grid-animation.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/wow.min.js') }}"></script>
    <script src="{{ asset(activeTemplate(true) . 'js/main.js') }}"></script>

    @php echo loadExtension('tawk-chat') @endphp

    @include('partials.notify')

    @if (gs('pn'))
        @include('partials.push_script')
    @endif

    @stack('script')

    <script>
        (function($) {
            "use strict";
            $(".langSel").on("change", function() {
                window.location.href = "{{ route('home') }}/change/" + $(this).val();
            });

            $('.policy').on('click', function() {
                $.get('{{ route('cookie.accept') }}', function(response) {
                    $('.cookies-card').addClass('d-none');
                });
            });

            setTimeout(function() {
                $('.cookies-card').removeClass('hide')
            }, 2000);

            var inputElements = $('[type=text],select,textarea');
            $.each(inputElements, function(index, element) {
                element = $(element);
                element.closest('.form-group').find('label').attr('for', element.attr('name'));
                element.attr('id', element.attr('name'))
            });

            $.each($('input, select, textarea'), function(i, element) {
                var elementType = $(element);
                if (elementType.attr('type') != 'checkbox') {
                    if (element.hasAttribute('required')) {
                        $(element).closest('.form-group').find('label').addClass('required');
                    }
                }

            });

            let disableSubmission = false;
            $('.disableSubmission').on('submit', function(e) {
                if (disableSubmission) {
                    e.preventDefault()
                } else {
                    disableSubmission = true;
                }
            });

        })(jQuery);
    </script>

</body>

</html>
