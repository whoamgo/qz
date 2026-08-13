{{-- Nested layout: dashboard chrome shared by every profile tab.
     Profile pages extend THIS and define only @section('profile-content').
     A @yield inside an @include does not resolve, so the shell is a layout. --}}
@extends('website.layouts.app')

@push('styles')
    <link href="{{ wAsset('assets/web/css/profile.css') }}" rel="stylesheet">
@endpush

@section('content')
<section class="w-section">
    <div class="container">
        @include('website.partials.profile-shell')
    </div>
</section>
@endsection
