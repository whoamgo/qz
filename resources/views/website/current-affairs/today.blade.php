@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="[
        'Home' => locale_route('home'),
        'Current Affairs' => locale_route('website.current.affairs.index'),
        $label => locale_route('website.current.affairs.today')]" />
@endsection
@section('content')
@include('website.partials.current-affairs-period')
@endsection
