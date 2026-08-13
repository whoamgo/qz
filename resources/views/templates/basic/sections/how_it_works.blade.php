@php
    $howItWorksContent = getContent('how_it_works.content', true);
    $howItWorksElement = getContent('how_it_works.element', false, 4, true);
@endphp
<section class="how-work-section my-120">
    <div class="container">
        <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
            <h2 class="section-heading__title" data-highlight-start="{{ $howItWorksContent?->data_values?->highlight_start ?? 4 }}" data-highlight-word="{{ $howItWorksContent?->data_values?->highlight_end ?? 5 }}">
                {{ __($howItWorksContent?->data_values?->heading ?? '') }}
            </h2>
            <p class="section-heading__desc">
                {{ __($howItWorksContent?->data_values?->short_description ?? '') }}
            </p>
        </div>
        <div class="how-work-wrapper">
            @foreach ($howItWorksElement as $key => $howItWork)
                <div class="how-work-item wow fadeInDown" data-wow-delay="0.2s">
                    <div class="how-work-item-icon">
                        <img src="{{ frontendImage('how_it_works', $howItWork?->data_values?->image ?? '', '60x60') }}" alt="img">
                    </div>
                    <div class="how-work-item-content">
                        <h5 class="how-work-item-title">{{ __($howItWork?->data_values?->title ?? '') }}</h5>
                        <p class="how-work-item-desc">
                            {{ __($howItWork?->data_values?->description ?? '') }}
                        </p>
                        <a href="{{ url($howItWork?->data_values?->button_link ?? '') }}" class="how-work-item-link">
                            {{ __($howItWork?->data_values?->button_name ?? '') }}
                            <span class="icon">
                                <i class="las la-arrow-right"></i>
                            </span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
