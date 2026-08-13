@php
    $bannerContent = getContent('banner.content', true);
    $bannerElement = getContent('banner.element', orderById: true);
@endphp
<section class="banner-section">
    <div class="container">
        <div class="row g-4 g-sm-5">
            <div class="d-none d-xsm-block d-sm-block col-lg-3 col-sm-6 col-xsm-6">
                <div class="banner-shape mb-4 wow fadeInUp" data-wow-delay="0.2s">
                    <img src="{{ frontendImage('banner', $bannerContent?->data_values?->icon_one ?? '', '110x100') }}" alt="img">
                </div>
                <div class="banner-thumb wow fadeInLeft" data-wow-delay="0.2s">
                    <img src="{{ frontendImage('banner', $bannerContent?->data_values?->image_one ?? '', '290x390') }}" alt="img">
                </div>
            </div>
            <div class="col-lg-6 order-3 order-lg-0">
                <div class="banner-content wow fadeInUp" data-wow-delay="0.2s">

                    <h1 class="banner-content__title" data-highlight-start="{{ $bannerContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $bannerContent?->data_values?->highlight_end ?? 3 }}">
                        {{ __($bannerContent?->data_values?->heading ?? '') }}
                    </h1>
                    <p class="banner-content__desc">
                        {{ __($bannerContent?->data_values?->short_description ?? '') }}
                    </p>


                    <div class="banner-content-rating">
                        <div class="banner-content-rating-thumb">
                            @foreach ($bannerElement as $banner)
                                <img src="{{ frontendImage('banner', $banner?->data_values?->image ?? '', '60x60') }}" alt="img" />
                            @endforeach
                        </div>
                        <div class="banner-content-rating-content">
                            <div class="banner-content-rating-star">
                                <ul class="star-list">
                                    @php
                                        echo showRatings($bannerContent?->data_values?->review_rating ?? 5);
                                    @endphp
                                </ul>
                                <span class="count">{{ __($bannerContent?->data_values?->review_rating ?? '') }}/@lang('5')</span>
                            </div>
                            <p class="banner-content-rating-text">
                                {{ __($bannerContent?->data_values?->rating_text ?? '') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-none d-xsm-block d-sm-block col-lg-3 col-sm-6 col-xsm-6">
                <div class="banner-thumb wow fadeInRight" data-wow-delay="0.2s">
                    <img src="{{ frontendImage('banner', $bannerContent?->data_values?->image_two ?? '', '290x405') }}" alt="img">
                </div>
                <div class="banner-shape mt-4 wow fadeInDown" data-wow-delay="0.2s">
                    <img src="{{ frontendImage('banner', $bannerContent?->data_values?->icon_two ?? '', '140x105') }}" alt="img">
                </div>
            </div>
        </div>
    </div>
</section>

@push('script')
    <script>
        (function($) {
            "use strict";
            let text = $(".banner-content__desc").text().trim();
            let words = text.split(" ");
            let lastThree = words.splice(-3).join(" ");
            let updatedText = words.join(" ") + ' <span class="center-text">' + lastThree + '</span>';
            $(".banner-content__desc").html(updatedText);
        })(jQuery)
    </script>
@endpush
