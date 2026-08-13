<?php

use Illuminate\Support\Facades\Route;

Route::get('/clear', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
});

Route::get('cron', 'CronController@cron')->name('cron');

// User Support Ticket
Route::controller('TicketController')->prefix('ticket')->name('ticket.')->group(function () {
    Route::get('/', 'supportTicket')->name('index');
    Route::get('new', 'openSupportTicket')->name('open');
    Route::post('create', 'storeSupportTicket')->name('store');
    Route::get('view/{ticket}', 'viewTicket')->name('view');
    Route::post('reply/{id}', 'replyTicket')->name('reply');
    Route::post('close/{id}', 'closeTicket')->name('close');
    Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
});

Route::get('app/deposit/confirm/{hash}', 'Gateway\PaymentController@appDepositConfirm')->name('deposit.app.confirm');

/*
| The public site is served by routes/website.php (loaded before this file).
| Laravel replaces an earlier route when a later one declares the SAME method
| and URI, so the legacy definitions for /, /exams, /blog, /blog/{slug} and
| /contact are retired below to stop them overriding the new website. They are
| commented out rather than deleted so the legacy template site can be restored
| by uncommenting. SiteController itself is untouched and still serves
| /pricing, /policy/{slug}, /cookie-policy, /verify-certificate and the rest.
*/
Route::controller('SiteController')->group(function () {
    // Retired - replaced by website.contact / website.contact.submit.
    // Both verbs must be retired: a later route with the same method+URI
    // replaces the earlier one, which would drop the new route's name.
    // Route::get('/contact', 'contact')->name('contact');
    // Route::post('/contact', 'contactSubmit');
    Route::get('/change/{lang?}', 'changeLanguage')->name('lang');

    Route::get('verify-certificate/{secret}', 'verifyCertificate')->name('verify.certificate');

    Route::get('cookie-policy', 'cookiePolicy')->name('cookie.policy');
    Route::get('/cookie/accept', 'cookieAccept')->name('cookie.accept');

    Route::get('pricing', 'pricing')->name('pricing');
    // Retired - replaced by website.exams / website.blog / website.blog.show
    // Route::get('exams', 'exams')->name('exams');
    // Route::get('blog', 'blog')->name('blog');
    // Route::get('blog/{slug}', 'blogDetails')->name('blog.details');

    Route::get('policy/{slug}', 'policyPages')->name('policy.pages');

    Route::get('placeholder-image/{size}', 'placeholderImage')->withoutMiddleware('maintenance')->name('placeholder.image');
    Route::get('maintenance-mode', 'maintenance')->withoutMiddleware('maintenance')->name('maintenance');

    Route::get('/{slug}', 'pages')->name('pages');
    // Retired - replaced by website.home
    // Route::get('/', 'index')->name('home');
});
