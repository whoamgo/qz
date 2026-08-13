@extends('Template::layouts.frontend')
@section('content')

    <section class="exam-section my-120">
        <div class="container">
            <div class="exam-filter mb-4">
                <form action="" class="exam-filter-search">
                    <input class="exam-filter-search-input" name="search" type="text" placeholder="@lang('Search Exam')" value="{{ request()->search }}">
                    <button type="submit" class="exam-filter-search-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path
                                  d="M11 19C15.4183 19 19 15.4183 19 11C19 6.58172 15.4183 3 11 3C6.58172 3 3 6.58172 3 11C3 15.4183 6.58172 19 11 19Z"
                                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M20.9992 21.0002L16.6992 16.7002" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                  stroke-linejoin="round" />
                        </svg>

                    </button>
                </form>
            </div>
            <div class="row justify-content-center gy-4">
                @include('Template::partials.exam_card')
            </div>

            @if ($exams->hasPages())
                <div class="pagination-wrapper">
                    {{ paginateLinks($exams) }}
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
