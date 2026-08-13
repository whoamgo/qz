@foreach ($blogs as $blog)
    <div class="col-lg-4 col-md-6">
        <div class="blog-item wow fadeInUp" data-wow-delay="0.2s">
            <div class="blog-item__thumb">
                <a href="{{ route('blog.details', $blog->slug) }}" class="link">
                    <img src="{{ frontendImage('blog', 'thumb_' . $blog?->data_values?->image ?? '', '420x280') }}" class="fit-image" alt="" />
                </a>
            </div>
            <div class="blog-item__content">
                <p class="blog-item__date">{{ showDateTime($blog->created_at, 'd M Y') }}</p>
                <h5 class="blog-item__title">
                    <a href="{{ route('blog.details', $blog->slug) }}" class="link">
                        {{ strLimit(__($blog?->data_values?->title ?? ''), 60) }}
                    </a>
                </h5>
                <a href="{{ route('blog.details', $blog->slug) }}" class="btn btn-outline--base w-100 btn--md mt-3">@lang('Learn More')</a>
            </div>
        </div>
    </div>
@endforeach
