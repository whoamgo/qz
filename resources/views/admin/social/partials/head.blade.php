{{--
    Shared head for every Social Media Center screen: the stylesheet plus the
    number formatter the KPI partials use.
--}}
@push('style')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/social.css') }}?v={{ @filemtime(base_path('assets/admin/css/social.css')) ?: 1 }}">
@endpush
