@props(['category', 'quizCount' => null, 'questionCount' => null, 'url' => null])
<a href="{{ $url ?? route('website.category.show', $category->slug) }}" class="w-card w-cat-card text-decoration-none">
    <span class="w-cat-icon" aria-hidden="true">
        @include('website.partials.category-icon', ['category' => $category])
    </span>
    <h3 class="w-card-title" style="font-size: var(--w-fs-base);">{{ $category->name }}</h3>
    <p class="w-text-sm w-muted mb-0">
        @if (!is_null($quizCount)) {{ $quizCount }} {{ \Illuminate\Support\Str::plural('quiz', $quizCount) }} @endif
        @if (!is_null($questionCount)) &middot; {{ number_format($questionCount) }} Qs @endif
        @if (is_null($quizCount) && is_null($questionCount) && isset($category->sub_count)) {{ $category->sub_count }} topics @endif
    </p>
</a>
