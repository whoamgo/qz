@extends('Template::layouts.frontend')

@section('content')
    @php
        $blogContent = getContent('blog.content', true);
    @endphp
    <section class="blog my-120">
        <div class="container">
            <div class="row justify-content-center gy-4">
                @include('Template::partials.blogs')
            </div>

            @if ($blogs->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($blogs) }}
                </div>
            @endif
        </div>
    </section>


    @if (isset($sections->secs) && $sections->secs != null)
        @foreach (json_decode($sections->secs) as $sec)
            @include('Template::sections.' . $sec)
        @endforeach
    @endif
@endsection
