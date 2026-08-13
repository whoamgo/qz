@props(['blog'])
@php
    $v = $blog->data_values;
    $body = strip_tags($v->description ?? '');
    $read = max(1, (int) ceil(str_word_count($body) / 200));
@endphp
<article class="w-card">
    <a href="{{ route('blog.details', $blog->slug) }}" aria-label="{{ $v->title ?? 'Article' }}">
        <img src="{{ frontendThumb('blog', $v->image ?? '', '420x280') }}"
             alt="{{ $v->title ?? 'Article' }}" class="w-100" loading="lazy" width="420" height="280">
    </a>
    <div class="w-card-body">
        <div class="w-blog-meta">
            <span><i class="bi bi-calendar3" aria-hidden="true"></i> {{ showDateTime($blog->created_at, 'd M Y') }}</span>
            <span><i class="bi bi-clock" aria-hidden="true"></i> {{ $read }} min read</span>
        </div>
        <h3 class="w-card-title">
            <a href="{{ route('blog.details', $blog->slug) }}">{{ $v->title ?? 'Untitled' }}</a>
        </h3>
        <p class="w-text-sm w-muted mb-0">{{ \Illuminate\Support\Str::limit($body, 120) }}</p>
    </div>
    <div class="w-card-footer">
        <a href="{{ route('blog.details', $blog->slug) }}" class="w-text-sm fw-semibold">
            Read article <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </div>
</article>
