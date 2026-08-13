@extends('website.layouts.app')
@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), $title => url()->current()]" />
@endsection
@section('content')
<section class="w-section">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <article class="w-article">
                    <h1 class="mb-4">{{ $page->data_values->title ?? $title }}</h1>
                    <div class="w-article-body">
                        @if (!empty($page->data_values->details))
                            @php echo $page->data_values->details; @endphp
                        @elseif (!empty($page->data_values->description))
                            @php echo $page->data_values->description; @endphp
                        @else
                            <p class="w-muted">
                                This page has not been configured yet. Content can be added from
                                the admin panel under Frontend Manager &rsaquo; Policy Pages.
                            </p>
                        @endif
                    </div>
                </article>
            </div>
            <div class="col-lg-4">
                @if ($allPages->count())
                    <div class="w-card"><div class="w-card-body">
                        <h2 class="w-card-title">Policies</h2>
                        <ul class="list-unstyled mb-0">
                            @foreach ([['website.privacy','Privacy Policy'],['website.terms','Terms & Conditions'],['website.disclaimer','Disclaimer']] as [$r,$l])
                                <li class="mb-2"><a href="{{ route($r) }}">{{ $l }}</a></li>
                            @endforeach
                        </ul>
                    </div></div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
