@props(['topic', 'parentSlug', 'quizCount' => 0, 'questionCount' => 0])
<a href="{{ route('website.subcategory.show', [$parentSlug, $topic->slug]) }}" class="w-card text-decoration-none">
    <div class="w-card-body">
        <div class="d-flex align-items-start gap-3">
            <span class="w-cat-icon m-0 flex-shrink-0" style="width:44px;height:44px;font-size:1.1rem;" aria-hidden="true">
                @include('website.partials.category-icon', ['category' => $topic, 'fallback' => 'bi-newspaper'])
            </span>
            <div class="flex-grow-1">
                <h3 class="w-card-title mb-1" style="font-size: var(--w-fs-base);">{{ $topic->name }}</h3>
                <div class="w-meta">
                    <span><i class="bi bi-patch-question" aria-hidden="true"></i> {{ $quizCount }}</span>
                    <span><i class="bi bi-list-ol" aria-hidden="true"></i> {{ number_format($questionCount) }} Qs</span>
                </div>
            </div>
        </div>
    </div>
</a>
