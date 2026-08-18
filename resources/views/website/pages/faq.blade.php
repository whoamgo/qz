@extends('website.layouts.app')

@section('breadcrumb')
    <x-website::breadcrumbs :trail="['Home' => route('home'), 'FAQ' => route('website.faq')]" />
@endsection

@section('content')
<section class="w-section">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-start">

            {{-- Questions --}}
            <div class="col-lg-8">
                <div class="mb-4">
                    <span class="w-badge d-inline-block mb-3">
                        {{ $heading?->data_values?->heading ?? 'Frequently Asked Questions' }}
                    </span>
                    <h1 class="mb-2">
                        {{ $heading?->data_values?->subheading ?? 'Everything you need to know' }}
                    </h1>
                    @if (!empty($heading?->data_values?->short_description))
                        <p class="w-muted mb-0">{{ $heading->data_values->short_description }}</p>
                    @else
                        <p class="w-muted mb-0">
                            Common questions about how {{ gs('site_name') ?: 'Quiz Mitra' }} works — quizzes, XP, accounts and exam prep.
                        </p>
                    @endif
                </div>

                @if (count($faqs))
                    {{-- Reuses the site's existing accordion; title suppressed so
                         it does not duplicate the H1 above. --}}
                    <x-website::faq-accordion :faqs="$faqs" id="wPageFaq" :title="false" />
                @else
                    <div class="w-card"><div class="w-card-body">
                        <p class="w-muted mb-0">No FAQs have been published yet. Please check back soon.</p>
                    </div></div>
                @endif
            </div>

            {{-- Help sidebar --}}
            <div class="col-lg-4">
                <div class="w-card" style="position: sticky; top: 88px;">
                    <div class="w-card-body">
                        <h2 class="w-card-title"><i class="bi bi-chat-dots"></i> Still have questions?</h2>
                        <p class="w-muted">Can’t find the answer you’re looking for? Our team is happy to help.</p>
                        <a href="{{ route('contact') }}" class="btn w-btn-primary w-100 mb-2">Contact us</a>

                        <hr>

                        <p class="w-text-sm w-muted mb-2">Popular links</p>
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2"><a href="{{ route('website.quizzes') }}">Browse all quizzes</a></li>
                            <li class="mb-2"><a href="{{ route('website.categories') }}">Quiz categories</a></li>
                            <li class="mb-2"><a href="{{ route('website.about') }}">About {{ gs('site_name') ?: 'Quiz Mitra' }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>
@endsection
