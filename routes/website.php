<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
| Registered BEFORE routes/web.php. Laravel matches routes in registration
| order, so declaring these first stops the legacy `/{slug}` catch-all in
| web.php from swallowing paths like /quizzes and /categories.
*/

/*
| Five paths overlap the legacy template site: /, /exams, /blog, /blog/{slug}
| and /contact. Those legacy definitions are commented out in web.php, and the
| replacements below deliberately KEEP THE ORIGINAL ROUTE NAMES (home, exams,
| blog, blog.details, contact) because ~35 places across payment gateways,
| helpers.php, SiteController and the legacy template call route('home') and
| friends. Reusing the names keeps every one of those call sites working.
*/
/*
| Discovery endpoints. Served through Laravel (not static files) so the sitemap
| tracks the database and both use the configured app URL in every environment.
| Declared first so the exact paths win over any dynamic route.
*/
Route::namespace('Website')->group(function () {
    Route::get('sitemap.xml', 'SitemapController@index')->name('sitemap');
    Route::get('robots.txt', 'SitemapController@robots')->name('robots');
    Route::get('llms.txt', 'SitemapController@llms')->name('llms');
    // Dynamic Open Graph card per quiz (for social share previews).
    Route::get('og/quiz/{slug}', 'OgImageController@quiz')->name('og.quiz');
});

/*
| Auth lives under the /user prefix (routes/user.php), but the conventional
| bare paths /login and /register are what Google, browsers and external links
| assume — and they otherwise fall through to web.php's /{slug} catch-all and
| 404. Permanent-redirect them to the real pages. route() is used (not a static
| string) so the target stays correct under the /qz subpath locally and at the
| domain root in production.
*/
Route::get('login', fn() => redirect()->route('user.login', [], 301))->name('login.redirect');
Route::get('register', fn() => redirect()->route('user.register', [], 301))->name('register.redirect');

/*
| Pricing page retired. The named route is commented out in web.php, but a
| legacy CMS "pricing" page still exists, so web.php's /{slug} catch-all would
| otherwise resurrect it. Declaring the exact path here (this file loads first)
| makes /pricing return 404 so it no longer appears anywhere on the site.
| To restore Pricing: delete this line and uncomment the route in web.php.
*/
Route::get('pricing', fn() => abort(404));

Route::namespace('Website')->group(function () {
    Route::get('/', 'HomeController@index')->name('home');
    Route::get('exams', 'ExamController@index')->name('exams');
    Route::get('blog', 'BlogController@index')->name('blog');
    Route::get('blog/{slug}', 'BlogController@show')->name('blog.details');
    Route::get('contact', 'PageController@contact')->name('contact');
});

Route::namespace('Website')->name('website.')->group(function () {

    // ---------------------------------------------------------------- quizzes
    Route::controller('QuizController')->group(function () {
        Route::get('quizzes', 'index')->name('quizzes');
        Route::get('quiz/{slug}', 'show')->name('quiz.show');

        Route::middleware('auth')->group(function () {
            Route::post('quiz/{slug}/start', 'start')->name('quiz.start');
            Route::get('quiz/attempt/{attempt}', 'attempt')->name('quiz.attempt');
            Route::post('quiz/attempt/{attempt}/answer', 'saveAnswer')->name('quiz.answer');
            Route::post('quiz/attempt/{attempt}/review', 'markReview')->name('quiz.mark.review');
            Route::post('quiz/attempt/{attempt}/submit', 'submit')->name('quiz.submit');
            Route::get('quiz/result/{attempt}', 'result')->name('quiz.result');
            Route::get('quiz/review/{attempt}', 'review')->name('quiz.review');
        });
    });

    // ------------------------------------------------------------- categories
    Route::controller('CategoryController')->group(function () {
        Route::get('categories', 'index')->name('categories');
        Route::get('category/{slug}', 'show')->name('category.show');
        Route::get('category/{parent}/{child}', 'subCategory')->name('subcategory.show');
    });

    // ------------------------------------------------------------------ exams
    Route::controller('ExamController')->group(function () {
        Route::get('exams/{slug}', 'show')->name('exam.show');
        Route::get('mock-tests', 'mockTests')->name('mock.tests');
        Route::get('pyq', 'pyq')->name('pyq');
    });

    // -------------------------------------------------------- current affairs
    Route::controller('CurrentAffairsController')->prefix('current-affairs')->name('current.affairs.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('today', 'today')->name('today');
        Route::get('weekly', 'weekly')->name('weekly');
        Route::get('monthly', 'monthly')->name('monthly');
    });

    // ------------------------------------------------------------ leaderboard
    Route::get('leaderboard', 'LeaderboardController@index')->name('leaderboard');

    // ---------------------------------------------------------- static pages
    Route::controller('PageController')->group(function () {
        Route::get('about', 'about')->name('about');
        Route::get('faq', 'faq')->name('faq');
        Route::post('contact', 'contactSubmit')->name('contact.submit');
        Route::get('privacy-policy', 'privacy')->name('privacy');
        Route::get('terms-and-conditions', 'terms')->name('terms');
        Route::get('disclaimer', 'disclaimer')->name('disclaimer');
    });

    // ------------------------------------------------------------ subscribe
    Route::post('subscribe', 'SubscriberController@store')->name('subscribe');

    // --------------------------------------------------------------- search
    Route::get('search', 'SearchController@index')->name('search');
    Route::get('search/suggest', 'SearchController@suggest')->name('search.suggest');

    // --------------------------------------------------------------- profile
    Route::middleware('auth')->prefix('profile')->name('profile.')->controller('ProfileController')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('quizzes', 'quizzes')->name('quizzes');
        Route::get('progress', 'progress')->name('progress');
        Route::get('xp', 'xp')->name('xp');
        Route::get('badges', 'badges')->name('badges');
        Route::get('streak', 'streak')->name('streak');
        Route::get('bookmarks', 'bookmarks')->name('bookmarks');
        Route::get('settings', 'settings')->name('settings');
        Route::post('settings', 'settingsUpdate')->name('settings.update');
    });

    // ------------------------------------------------------------- bookmarks
    Route::middleware('auth')->post('bookmark/toggle', 'BookmarkController@toggle')->name('bookmark.toggle');

    // --------------------------------------------------- multiplayer rooms
    // Public, indexable "how it works" landing page — the marketing entry point.
    Route::get('play-live', 'RoomController@landing')->name('play.live');

    Route::middleware('auth')->prefix('rooms')->name('rooms.')->controller('RoomController')->group(function () {
        Route::get('create', 'create')->name('create');
        Route::get('quizzes', 'quizzesByCategory')->name('quizzes');   // AJAX: ?category_id=
        Route::post('/', 'store')->name('store');

        Route::get('join', 'join')->name('join');
        Route::post('preview', 'preview')->name('preview');            // AJAX
        Route::post('join', 'joinStore')->name('join.store');

        Route::get('{room}/waiting', 'waiting')->name('waiting');
        Route::get('{room}/status', 'status')->name('status');         // AJAX poll
        Route::get('{room}/play', 'play')->name('play');
        Route::get('{room}/results', 'results')->name('results');
        Route::get('{room}/results/data', 'resultsData')->name('results.data'); // AJAX poll
        Route::post('{room}/start', 'start')->name('start');
        Route::post('{room}/leave', 'leave')->name('leave');
        Route::post('{room}/replay', 'replay')->name('replay');
        // Graceful GET fallback for the POST-only actions above (back button,
        // reload or a typed URL) — redirect instead of "Method Not Allowed".
        Route::get('{room}/start', 'startRedirect');
        Route::get('{room}/leave', 'startRedirect');
        Route::get('{room}/replay', 'startRedirect');
    });
});
