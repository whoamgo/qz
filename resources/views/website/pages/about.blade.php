@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'About' => route('website.about')]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="row g-5 align-items-start">
            <div class="col-lg-8">
                <h1 class="mb-4">{{ $content->data_values->heading ?? 'About Us' }}</h1>
                <div class="w-article-body w-muted">
                    @if (!empty($content->data_values->description))
                        @php echo $content->data_values->description; @endphp
                    @else
                        <p>We build free, high-quality practice material for learners preparing for competitive
                           examinations in India — SSC, Railway, Banking, UPSC, Defence, State PSC and Teaching.</p>
                        <p>Every question carries a written explanation, so you understand the reasoning rather than
                           memorising an answer key. Your progress is tracked automatically: accuracy per topic, XP,
                           levels, daily streaks and badges.</p>
                    @endif
                </div>
            </div>
            <div class="col-lg-4">
                @if ($counters->count())
                    <div class="row g-3">
                        @foreach ($counters as $c)
                            <div class="col-6">
                                <div class="w-stat-tile">
                                    <strong>{{ $c->data_values->number ?? '—' }}</strong>
                                    <span>{{ $c->data_values->title ?? '' }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                <div class="w-card mt-4"><div class="w-card-body text-center">
                    <h2 class="w-card-title">Start practising</h2>
                    <a href="{{ route('website.quizzes') }}" class="btn w-btn-primary w-100 mt-2">Browse quizzes</a>
                </div></div>
            </div>
        </div>
    </div>
</section>
@endsection
