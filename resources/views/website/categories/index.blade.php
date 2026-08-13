@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'Categories' => route('website.categories')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>All Categories</h1>
                <p>{{ $categories->count() }} subject areas covering every exam and topic.</p>
            </div>
        </div>
        <div class="row g-3">
            @foreach ($categories as $cat)
                <div class="col-6 col-md-4 col-lg-3">
                    <x-website::category-card :category="$cat"
                        :quizCount="$quizCounts[$cat->id] ?? 0"
                        :questionCount="$questionCounts[$cat->id] ?? 0" />
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
