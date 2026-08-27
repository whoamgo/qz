@extends('website.layouts.app')

@section('breadcrumb')
    <a href="{{ route('home') }}">Home</a> <span>/</span>
    <span aria-current="page">Exam Prep</span>
@endsection

@section('content')
    <section class="w-section">
        <div class="container">
            <div class="w-section-head">
                <div>
                    <h1>Exam Prep Hubs</h1>
                    <p>Free, curated quiz practice for India's top competitive exams — each hub pulls together the real quizzes that match what the exam actually tests.</p>
                </div>
            </div>
            <div class="row g-3">
                @foreach ($hubs as $h)
                    <div class="col-sm-6 col-lg-4">
                        <a href="{{ route('website.exam.hub.show', $h->slug) }}" class="w-card h-100 d-block text-decoration-none">
                            <div class="w-card-body">
                                <span class="w-badge w-badge-primary mb-2">{{ $h->name }}</span>
                                <h2 class="w-card-title mb-1" style="font-size: var(--w-fs-lg, 1.15rem);">{{ $h->full_name }}</h2>
                                <p class="w-text-sm w-muted mb-0">{{ $h->description }}</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
