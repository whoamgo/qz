@extends('website.layouts.app')
@push('styles')<link href="{{ wAsset('assets/web/css/blog.css') }}" rel="stylesheet">@endpush
@section('breadcrumb')
    <x-website::breadcrumbs :trail="[
        'Home' => route('home'), 'Blog' => route('blog'),
        ($values->title ?? 'Article') => route('blog.details', $blog->slug)]" />
@endsection
<style>
    .w-article-body a { 
        color: #0066da !important;
    }
</style>
@section('content')
<section class="w-section">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <article class="w-article">
                    <h1 class="mb-3">{{ $values->title ?? 'Untitled' }}</h1>

                    <div class="w-blog-meta mb-4">
                        <span><i class="bi bi-person"></i> {{ gs('site_name') }}</span>
                        <span><i class="bi bi-calendar3"></i> {{ showDateTime($blog->created_at, 'd M Y') }}</span>
                        <span><i class="bi bi-clock"></i> {{ $readingTime }} min read</span>
                    </div>

                    @if (!empty($values->image))
                        <img src="{{ frontendThumb('blog', $values->image, '960x640') }}" alt="{{ $values->title ?? '' }}"
                             class="w-100 rounded mb-4" loading="eager">
                    @endif

                    <div class="w-article-body">
                        @php echo $values->description ?? ''; @endphp
                    </div>

                    <hr class="my-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <strong>Share this article</strong>
                        <div class="w-share">
                            @foreach ([['facebook','bi-facebook'],['twitter','bi-twitter-x'],['whatsapp','bi-whatsapp'],['linkedin','bi-linkedin'],['copy','bi-link-45deg']] as [$net,$icon])
                                <a href="#" class="wShareBtn" data-network="{{ $net }}" aria-label="Share on {{ ucfirst($net) }}">
                                    <i class="bi {{ $icon }}"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </article>

                {{-- Prev / next --}}
                <div class="w-prev-next mt-4">
                    @if ($prev)
                        <a href="{{ route('blog.details', $prev->slug) }}">
                            <small><i class="bi bi-arrow-left"></i> Previous</small>
                            <strong>{{ \Illuminate\Support\Str::limit($prev->data_values->title ?? '', 60) }}</strong>
                        </a>
                    @else <span></span> @endif
                    @if ($next)
                        <a href="{{ route('blog.details', $next->slug) }}" class="text-end">
                            <small>Next <i class="bi bi-arrow-right"></i></small>
                            <strong>{{ \Illuminate\Support\Str::limit($next->data_values->title ?? '', 60) }}</strong>
                        </a>
                    @endif
                </div>
            </div>

            <div class="col-lg-4">
                @if ($related->count())
                    <h2 class="h5 mb-3">Related articles</h2>
                    <div class="d-flex flex-column gap-3">
                        @foreach ($related as $r)
                            <x-website::blog-card :blog="$r" />
                        @endforeach
                    </div>
                @endif

                <div class="w-card mt-4"><div class="w-card-body text-center">
                    <h3 class="w-card-title">Ready to practise?</h3>
                    <p class="w-text-sm w-muted">Put what you have read into practice with a quiz.</p>
                    <a href="{{ route('website.quizzes') }}" class="btn w-btn-primary w-100">Browse quizzes</a>
                </div></div>
            </div>
        </div>
    </div>
</section>
@endsection
