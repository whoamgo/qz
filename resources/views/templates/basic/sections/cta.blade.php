@php
    $ctaContent = getContent('cta.content', true);
@endphp

<section class="my-120 cta-section">
    <div class="container">
        <div class="cta-wrapper wow fadeInDown" data-wow-delay="0.2s">
            <div class="cta-content">
                <h2 class="cta-content__title">
                    {{ __($ctaContent?->data_values?->heading ?? '') }}
                </h2>
                <p class="cta-content__desc">
                    {{ __($ctaContent?->data_values?->short_details ?? '') }}
                </p>

                <div class="cta-content__button">
                    <a href="{{ url($ctaContent?->data_values?->button_url ?? '#') }}" class="btn btn--dark pill">{{ __($ctaContent?->data_values?->button_name ?? '') }}</a>
                </div>
            </div>
        </div>
    </div>
</section>
