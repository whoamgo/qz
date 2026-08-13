@php
    $categoryContent = getContent('categories.content', true);
    $categories = App\Models\Category::active()
        ->withCount([
            'exams' => function ($q) {
                $q->active()->running();
            },
        ])
        ->get();
@endphp
<section class="category-section my-120">
    <div class="container">
        <div class="section-heading wow fadeInDown" data-wow-delay="0.2s">
            <h2 class="section-heading__title" data-highlight-start="{{ $categoryContent?->data_values?->highlight_start ?? 4 }}" data-highlight-word="{{ $categoryContent?->data_values?->highlight_end ?? 4 }}">{{ __($categoryContent?->data_values?->heading ?? '') }}</h2>
            <p class="section-heading__desc">{{ __($categoryContent?->data_values?->short_description ?? '') }}</p>
        </div>
        <div class="row gy-4">
            @foreach ($categories as $category)
                <div class="col-lg-3 col-sm-6 col-xsm-6">
                    <a href="{{ route('exams') }}?search={{ $category->slug }}" class="category-card wow fadeInDown" data-wow-delay="0.2s">
                        <div class="category-card__img">
                            <img class="rounded-circle" src="{{ getImage(getFilePath('category') . '/' . $category->image, getFileSize('category')) }}" alt="">
                        </div>
                        <h5 class="category-card__title">{{ __($category->name) }}</h5>
                        <p class="category-card__count">{{ getAmount($category->exams_count) }} @lang('Exam Running')</p>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
