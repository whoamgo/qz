{{-- Shared body for the today / weekly / monthly Current Affairs pages. --}}
<section class="w-section">
    <div class="container">
        <div class="w-section-head">
            <div>
                <h1>{{ $label }}</h1>
                <p>{{ $quizzes->total() }} {{ \Illuminate\Support\Str::plural('quiz', $quizzes->total()) }} available in this section.</p>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-4">
            @foreach (['index' => 'All', 'today' => 'Today', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $k => $l)
                <a href="{{ route('website.current.affairs.' . $k) }}"
                   class="btn btn-sm {{ request()->routeIs('website.current.affairs.' . $k) ? 'w-btn-primary' : 'w-btn-outline' }}">{{ $l }}</a>
            @endforeach
        </div>

        @include('website.partials.quiz-grid', [
            'quizzes' => $quizzes, 'emptyIcon' => 'bi-newspaper',
            'emptyTitle' => 'Nothing published in this section yet',
            'emptyMessage' => 'Current affairs quizzes appear here as soon as they are published.',
        ])
    </div>
</section>
