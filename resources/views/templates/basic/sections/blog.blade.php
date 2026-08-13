@php
    $blogContent = getContent('blog.content', true);
    $blogElement = getContent('blog.element', null, 3, false);
@endphp


<section class="blog my-120">
    <div class="container">
        <div class="section-heading-wrapper">
            <div class="section-heading flex-1 wow fadeInUp" data-wow-delay="0.2s">
                <h2 class="section-heading__title" data-highlight-start="{{ $blogContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $blogContent?->data_values?->highlight_end ?? 3 }}">
                    {{ __($blogContent?->data_values?->heading ?? '') }}
                </h2>
                <p class="section-heading__desc">
                    {{ __($blogContent?->data_values?->short_description ?? '') }}
                </p>
            </div>
        </div>
        <div class="row gy-4 justify-content-center">
            @include('Template::partials.blogs', ['blogs' => $blogElement])
        </div>
    </div>
</section>
