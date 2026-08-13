@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="[
        'Home' => route('home'),
        'Current Affairs' => route('website.current.affairs.index'),
        $label => route('website.current.affairs.weekly')]" />
@endsection
@section('content')
@include('website.partials.current-affairs-period')
@endsection
