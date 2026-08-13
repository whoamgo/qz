@extends('Template::layouts.frontend')
@section('content')
    <section class="blog-details-main my-120">
        <div class="container">
            <div class="row gy-5 justify-content-center">
                <div class="col-xl-9 col-lg-8">
                    <div class="blog-details wow fadeInLeft" data-wow-delay="0.2s">
                        <div class="blog-details-top">
                            <h3 class="blog-details__title">
                                {{ __($blog?->data_values?->title ?? '') }}
                            </h3>

                            <h6 class="blog-details__date">@lang('Posted On') {{ showDateTime($blog->created_at, 'd F Y') }}
                            </h6>
                            <div class="blog-share">
                                <ul class="social-list">
                                    <li class="social-list__item">
                                        <a class="social-list__link flex-center" target="_blank" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}">
                                            <i class="fab fa-facebook-f"></i>
                                            @lang('Facebook')
                                        </a>
                                    </li>
                                    <li class="social-list__item">
                                        <a class="social-list__link flex-center" target="_blank" href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($blog->title) }}">
                                            <i class="fa-brands fa-x-twitter"></i>
                                            @lang('Twitter')
                                        </a>
                                    </li>
                                    <li class="social-list__item">
                                        <a class="social-list__link flex-center" target="_blank" href="https://www.linkedin.com/shareArticle?mini=true&url={{ urlencode(url()->current()) }}&title={{ urlencode($blog->title) }}">
                                            <i class="fab fa-linkedin-in"></i>
                                            @lang('Linkedin')
                                        </a>
                                    </li>
                                    <li class="social-list__item">
                                        <a class="social-list__link flex-center" target="_blank" href="https://pinterest.com/pin/create/button/?url={{ urlencode(url()->current()) }}&media={{ urlencode(frontendImage('blog', 'thumb_' . $blog?->data_values?->image, '470x265')) }}&description={{ urlencode($blog?->data_values?->title ?? '') }}">
                                            <i class="fab fa-pinterest"></i>
                                            @lang('Pinterest')
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="blog-details__thumb">
                            <img src="{{ frontendImage('blog', $blog?->data_values?->image, '960x640') }}" class="fit-image" alt="img">
                        </div>
                        <div class="blog-details__content">
                            <div class="content-item">
                                @php echo $blog->data_values->description @endphp
                            </div>
                        </div>
                    </div>
                    <div class="fb-comments" data-href="{{ url()->current() }}" data-numposts="5"></div>
                </div>
                <div class="col-xl-3 col-lg-4">
                    <div class="blog-sidebar-wrapper wow fadeInRight" data-wow-delay="0.2s">
                        <div class="blog-sidebar">
                            <h4 class="blog-sidebar__title"> @lang('Related Blogs') </h4>
                            <div class="latest-blog-wrapper">
                                @foreach ($recentBlogs as $recentBlog)
                                    <div class="latest-blog">
                                        <div class="latest-blog__thumb">
                                            <a href="{{ route('blog.details', $recentBlog->slug) }}"> <img src="{{ frontendImage('blog', 'thumb_' . $recentBlog?->data_values?->image ?? '', '420x280') }}" class="fit-image" alt="image"></a>
                                        </div>
                                        <div class="latest-blog__content">
                                            <span class="latest-blog__date">{{ showDateTime($recentBlog->created_at, 'd M Y') }}</span>
                                            <p class="latest-blog__title">
                                                <a href="{{ route('blog.details', $recentBlog->slug) }}">{{ strLimit(__($recentBlog?->data_values?->title ?? ''), 50) }}</a>
                                            </p>
                                            <a href="{{ route('blog.details', $recentBlog->slug) }}" class="btn btn-outline--base w-100 btn--sm mt-2">@lang('Learn More')</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@push('fbComment')
    @php echo loadExtension('fb-comment') @endphp
@endpush
