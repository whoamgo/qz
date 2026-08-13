{{-- Shared quiz grid + pagination + empty state. --}}
@php $cols = $cols ?? 'col-sm-6 col-lg-4'; @endphp
@if ($quizzes->count())
    <div class="row g-3">
        @foreach ($quizzes as $quiz)
            <div class="{{ $cols }}"><x-website::quiz-card :quiz="$quiz" /></div>
        @endforeach
    </div>
    @if ($quizzes instanceof \Illuminate\Contracts\Pagination\Paginator || $quizzes instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <x-website::pagination :paginator="$quizzes" />
    @endif
@else
    <div class="w-card">
        <x-website::empty-state icon="{{ $emptyIcon ?? 'bi-patch-question' }}"
            title="{{ $emptyTitle ?? 'No quizzes here yet' }}"
            message="{{ $emptyMessage ?? 'Quizzes appear here once they are published from the admin panel.' }}"
            :actionUrl="route('website.quizzes')" actionLabel="Browse all quizzes" />
    </div>
@endif
