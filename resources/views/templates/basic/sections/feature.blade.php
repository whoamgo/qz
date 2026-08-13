@php
    $featureContent = getContent('feature.content', true);
    $featureElement = getContent('feature.element', false, 3, true);
@endphp


<section class="feature-section my-120">
    <div class="container">
        <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
            <h2 class="section-heading__title" data-highlight-start="{{ $featureContent?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $featureContent?->data_values?->highlight_end ?? 3 }}">
                {{ __($featureContent?->data_values?->subheading ?? '') }}
            </h2>
            <p class="section-heading__desc">
                {{ __($featureContent?->data_values?->short_description ?? '') }}
            </p>
        </div>

        <div class="feature-wrapper wow fadeInDown" data-wow-delay="0.2s">
            @foreach ($featureElement as $key => $feature)
                <div class="feature-item">
                    <div class="row gy-4 gy-md-5 align-items-center ">
                        <div class="col-md-5">
                            <div class="feature-item-thumb">
                                <img src="{{ frontendImage('feature', $feature?->data_values?->image ?? '', '575x585') }}" alt="AI Ad Copy Generator">
                            </div>
                        </div>
                        <div class="col-md-7">
                            <div class="ms-lg-5">
                                <h2 class="feature-item-title" data-highlight-start="{{ $feature?->data_values?->highlight_start ?? 2 }}" data-highlight-word="{{ $feature?->data_values?->highlight_end ?? 3 }}">
                                    {{ __($feature?->data_values?->heading ?? '') }}
                                </h2>
                                <p class="feature-item-desc">
                                    {{ __($feature?->data_values?->description ?? '') }}
                                </p>

                                @php
                                    echo $feature?->data_values?->feature_list ?? '';
                                @endphp

                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
