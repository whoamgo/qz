@php
    $faqContent = getContent('faq.content', true);
    $faqElement = getContent('faq.element', orderById: true);
@endphp

<section class="faq-section my-120">
    <div class="container">
        <div class="row g-4 g-xl-5 justify-content-between">
            <div class="col-lg-4">
                <div class="faq-wrapper wow fadeInLeft" data-wow-delay="0.2s">
                    <div class="section-heading style-left mb-lg-5">
                        <p class="section-heading__subtitle">{{ __($faqContent?->data_values?->heading ?? '') }}</p>
                        <h3 class="section-heading__title">
                            {{ __($faqContent?->data_values?->subheading ?? '') }}
                        </h3>
                        <p class="section-heading__desc">
                            {{ __($faqContent?->data_values?->short_description ?? '') }}
                        </p>
                    </div>
                    <a href="{{ url($faqContent?->data_values?->button_url ?? '#') }}" class="faq-contact flex-between">
                        <p class="faq-contact-title">{{ __($faqContent?->data_values?->button_text ?? '') }}</p>
                        <p class="faq-contact-link">
                            <i class="las la-arrow-right"></i>
                        </p>
                    </a>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="accordion custom--accordion wow fadeInRight" data-wow-delay="0.2s" id="faq-accordion">
                    @foreach ($faqElement as $key => $faq)
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq-accordion-{{ $key }}" @if ($loop->first) aria-expanded="true" @else aria-expanded="false" @endif aria-controls="faq-accordion-{{ $key }}">
                                    {{ __($faq?->data_values?->question ?? '') }}
                                </button>
                            </h2>
                            <div id="faq-accordion-{{ $key }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#faq-accordion">
                                <div class="accordion-body">
                                    {{ __($faq?->data_values?->answer ?? '') }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
