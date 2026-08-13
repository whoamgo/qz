@php
    $counterContent = getContent('counter.content', true);
    $counterElement = getContent('counter.element', false, 4, true);
@endphp




<section class="statistics-section my-120">
    <div class="statistics-section-shape">
        <img src="{{ asset(activeTemplate(true) . 'images/thumbs/globe.svg') }}" alt="img" />
    </div>
    <div class="statistics-section-shape left-side">
        <img src="{{ asset(activeTemplate(true) . 'images/thumbs/globe.svg') }}" alt="img" />
    </div>
    <div class="container">
        <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
            <h2 class="section-heading__title text-white" data-highlight-start="{{ $counterContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $counterContent?->data_values?->highlight_end ?? 3 }}">
                {{ __($counterContent?->data_values?->heading ?? '') }}
            </h2>
            <p class="section-heading__desc text-white">
                {{ __($counterContent?->data_values?->short_description ?? '') }}
            </p>
        </div>

        <div class="row gy-4 wow fadeInDown" data-wow-delay="0.2s">
            @foreach ($counterElement as $counter)
                <div class="col-lg-3 col-sm-6">
                    <div class="statistics-item">
                        <p class="statistics-item-title">{{ __($counter?->data_values?->title ?? '') }}</p>
                        <h3 class="statistics-item-count">
                            <span class="odometer" data-odometer-final="{{ __($counter?->data_values?->count_value ?? '') }}"></span>
                            <span>{{ __($counter?->data_values?->count_symbol ?? '') }}</span>
                        </h3>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
