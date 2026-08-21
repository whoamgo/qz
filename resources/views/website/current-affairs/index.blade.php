@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'Current Affairs' => route('website.current.affairs.index')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>{{ $seoContent['h1'] ?? 'Current Affairs' }}</h1>
                <p>{{ $seoContent['intro'] ?? 'Daily, weekly and monthly news quizzes for competitive exams.' }}</p>
            </div>
        </div>

        <div class="row g-3 mb-5">
            @foreach ([
                ['today', 'Today', 'bi-calendar-day', 'The latest national and international news questions.'],
                ['weekly', 'This Week', 'bi-calendar-week', 'A consolidated revision of the week in news.'],
                ['monthly', 'This Month', 'bi-calendar-month', 'Full-month revision for exam preparation.'],
            ] as [$key, $title, $icon, $desc])
                <div class="col-md-4">
                    <a href="{{ route('website.current.affairs.' . $key) }}" class="w-card text-decoration-none">
                        <div class="w-card-body">
                            <span class="w-cat-icon m-0 mb-3" style="width:48px;height:48px;font-size:1.25rem;">
                                <i class="bi {{ $icon }}"></i>
                            </span>
                            <h2 class="w-card-title">{{ $title }}</h2>
                            <p class="w-text-sm w-muted mb-0">{{ $desc }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="w-section-head"><div><h2>Latest Current Affairs Quizzes</h2></div></div>
        @include('website.partials.quiz-grid', ['quizzes' => $latest, 'emptyIcon' => 'bi-newspaper'])

        <div class="mt-5"><x-website::faq-accordion :faqs="$faqs" id="wCaFaq" /></div>

        {{-- Admin SEO content (sanitised server-side). --}}
        @if (!empty($seoContent['content']))
            <div class="w-card mt-4"><div class="w-card-body">
                <div class="w-article-body w-seo-content">{!! $seoContent['content'] !!}</div>
            </div></div>
        @endif
        @if (!empty($seoContent['bottom']))
            <div class="w-card mt-4"><div class="w-card-body">
                <div class="w-article-body w-seo-content">{!! $seoContent['bottom'] !!}</div>
            </div></div>
        @endif
    </div>
</section>
@endsection
