@php
    // Footer categories come from the same cached lookup the nav uses.
    $footerCats = \Illuminate\Support\Facades\Cache::remember('website.footer.categories', 3600, function () {
        return \App\Models\Category::whereNull('parent_id')->where('status', 1)
            ->orderBy('name')->limit(8)->get(['name', 'slug']);
    });

    $footerExams = \Illuminate\Support\Facades\Cache::remember('website.footer.exams', 3600, function () {
        return \App\Models\Category::whereIn('slug', [
            'ssc-exams', 'railway-exams', 'banking-exams', 'upsc-exams',
            'defence-exams', 'state-psc-exams',
        ])->where('status', 1)->orderBy('name')->get(['name', 'slug']);
    });

    $socials = \App\Models\Frontend::where('data_keys', 'social_icon.element')->get();
@endphp

<footer class="w-footer">
    <div class="container">
        <div class="row g-4 align-items-start">

            <div class="col-lg-4 col-md-6">
                <a href="{{ route('home') }}" class="d-inline-block mb-3">
                    <img src="{{ getImage(getFilePath('logoIcon') . '/logo.png') }}"
                         alt="{{ gs('site_name') }}" width="130" height="40" loading="lazy">
                </a>
                <p class="w-text-sm mb-4" style="max-width: 30rem;">
                    Practice quizzes for General Knowledge, Current Affairs and competitive exams.
                    Track your progress, earn XP, unlock badges and compete on the leaderboard.
                </p>


                {{-- Newsletter --}}
                <div class="w-subscribe">
                    <h5>@lang('Subscribe')</h5>
                    <p class="w-text-sm mb-3">@lang('Join our community to receive updates')</p>

                    <form id="wSubscribeForm" data-action="{{ route('website.subscribe') }}" novalidate>
                        @csrf
                        <div class="w-subscribe-field">
                            <label for="wSubscribeEmail" class="visually-hidden">@lang('Email address')</label>
                            <input type="email" id="wSubscribeEmail" name="email" maxlength="40"
                                   placeholder="@lang('Enter your email')" autocomplete="email" required>
                            <button type="submit" id="wSubscribeBtn" aria-label="@lang('Subscribe')">
                                <i class="bi bi-send-fill" aria-hidden="true"></i>
                            </button>
                        </div>
                        <small class="w-subscribe-note" id="wSubscribeMsg" role="status" aria-live="polite">
                            @lang('By subscribing, you agree to our')
                            <a href="{{ route('website.privacy') }}">@lang('Privacy Policy')</a>
                        </small>
                    </form>
                </div>

                @if ($socials->count())
                    <div class="w-social">
                        @foreach ($socials as $social)
                            <a href="{{ $social->data_values->url ?? '#' }}" target="_blank" rel="noopener noreferrer"
                               aria-label="{{ $social->data_values->title ?? 'Social link' }}">
                                @php echo $social->data_values->social_icon ?? '<i class="bi bi-link-45deg"></i>'; @endphp
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h5>Categories</h5>
                <ul class="list-unstyled mb-0">
                    @foreach ($footerCats as $cat)
                        <li><a href="{{ route('website.category.show', $cat->slug) }}">{{ $cat->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('website.categories') }}" class="fw-semibold">View all &rarr;</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h5>Exams</h5>
                <ul class="list-unstyled mb-0">
                    @foreach ($footerExams as $exam)
                        <li><a href="{{ route('website.exam.show', $exam->slug) }}">{{ $exam->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('exams') }}" class="fw-semibold">All exams &rarr;</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h5>Practice</h5>
                <ul class="list-unstyled mb-0">
                    <li><a href="{{ route('website.quizzes') }}">All Quizzes</a></li>
                    <li><a href="{{ route('website.current.affairs.today') }}">Today's Current Affairs</a></li>
                    <li><a href="{{ route('website.current.affairs.weekly') }}">Weekly Current Affairs</a></li>
                    <li><a href="{{ route('website.mock.tests') }}">Mock Tests</a></li>
                    <li><a href="{{ route('website.pyq') }}">Previous Year Questions</a></li>
                    <li><a href="{{ route('website.leaderboard') }}">Leaderboard</a></li>
                </ul>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h5>Company</h5>
                <ul class="list-unstyled mb-0">
                    <li><a href="{{ route('website.about') }}">About Us</a></li>
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                    <li><a href="{{ route('blog') }}">Blog</a></li>
                    <li><a href="{{ route('website.privacy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('website.terms') }}">Terms &amp; Conditions</a></li>
                    <li><a href="{{ route('website.disclaimer') }}">Disclaimer</a></li>
                </ul>
            </div>
        </div>

        <div class="w-footer-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>&copy; {{ date('Y') }} {{ gs('site_name') ?: config('app.name') }}. All rights reserved.</span>
            <span class="w-text-sm">Made for learners preparing for competitive exams.</span>
        </div>
    </div>
</footer>
