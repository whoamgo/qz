@php
    $breadcrumbContent = getContent('breadcrumb.content', true);
@endphp


<section class="page-banner">
    <img class="page-banner-shape" src="{{ frontendImage('breadcrumb', $breadcrumbContent?->data_values?->image ?? '', '1920x310') }}" alt="img">
    <div class="container">
        <h3 class="page-banner__title">{{ isset($customPageTitle) ? __($customPageTitle) : __($pageTitle) }}</h3>
        <ul class="breadcrumb custom--breadcrumb">
            <li class="breadcrumb-item">
                <a href="{{ route('home') }}"><i class="fas fa-home"></i>@lang('Home')</a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                {{ isset($customPageTitle) ? __($customPageTitle) : __($pageTitle) }}
            </li>
        </ul>
    </div>
</section>
