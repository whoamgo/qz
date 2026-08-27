@extends('website.layouts.app')

@section('breadcrumb')
    <a href="{{ route('home') }}">Home</a> <span>/</span>
    <a href="{{ route('website.exam.hub.index') }}">Exam Prep</a> <span>/</span>
    <span aria-current="page">{{ $exam['name'] }}</span>
@endsection

@section('content')

    {{-- Hero / intro (unique per exam) --}}
    <section class="w-section">
        <div class="container">
            <div class="w-card" style="background: linear-gradient(135deg,#eef2ff,#fff);">
                <div class="w-card-body">
                    <span class="w-badge w-badge-primary mb-2"><i class="bi bi-mortarboard-fill"></i> Exam Prep</span>
                    <h1 class="mb-2">{{ $exam['title'] }}</h1>
                    <p class="w-muted mb-3" style="max-width: 70ch;">{{ $exam['intro'] }}</p>
                    <div class="w-meta">
                        <span><i class="bi bi-collection"></i> {{ $quizTotal }} quizzes</span>
                        <span><i class="bi bi-question-circle"></i> {{ number_format($questionTotal) }} practice questions</span>
                        <span><i class="bi bi-lightning-charge"></i> Instant scoring &amp; explanations</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Curated real quizzes --}}
    <section class="w-section">
        <div class="container">
            <div class="w-section-head">
                <div>
                    <h2>{{ $exam['name'] }} practice quizzes</h2>
                    <p>Curated from QuizMitra's published quizzes across the subjects {{ $exam['name'] }} tests.</p>
                </div>
                <a href="{{ route('website.quizzes') }}" class="btn w-btn-outline btn-sm">All quizzes</a>
            </div>
            @include('website.partials.quiz-grid', [
                'quizzes'      => $quizzes,
                'emptyTitle'   => 'Quizzes coming soon',
                'emptyMessage' => 'We are adding ' . $exam['name'] . ' quizzes. Browse all quizzes meanwhile.',
            ])
        </div>
    </section>

    {{-- Cross-links to the other hubs (internal linking) --}}
    <section class="w-section w-section-alt">
        <div class="container">
            <div class="w-section-head"><div><h2>Prepare for other exams</h2></div></div>
            <div class="row g-3">
                @foreach (config('exam_hubs.hubs') as $hslug => $h)
                    @continue($hslug === $slug)
                    <div class="col-sm-6 col-lg-4">
                        <a href="{{ route('website.exam.hub.show', $hslug) }}" class="w-card h-100 d-block text-decoration-none">
                            <div class="w-card-body">
                                <h3 class="w-card-title mb-1">{{ $h['name'] }}</h3>
                                <p class="w-text-sm w-muted mb-0">{{ \Illuminate\Support\Str::limit($h['description'], 96) }}</p>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- FAQ (matches the FAQPage schema emitted in <head>) --}}
    <section class="w-section">
        <div class="container">
            <div class="w-section-head"><div><h2>{{ $exam['name'] }} quiz — FAQs</h2></div></div>
            <div class="w-card"><div class="w-card-body">
                @foreach ($faqs as $faq)
                    <div class="{{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                        <strong>{{ $faq['question'] }}</strong>
                        <p class="w-muted mb-0">{{ $faq['answer'] }}</p>
                    </div>
                @endforeach
            </div></div>
        </div>
    </section>

@endsection
