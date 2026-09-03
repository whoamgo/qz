<?php

namespace App\Providers;

use App\Constants\Status;
use App\Lib\Searchable;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\Frontend;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider {
    /**
     * Register any application services.
     */
    public function register(): void {
        Builder::mixin(new Searchable);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {
        // Auto-draft a social post when a quiz is published. The observer is a
        // no-op unless social_settings.auto_draft_from_quiz is switched on, and
        // it only ever creates a draft.
        \App\Models\Quiz::observe(\App\Observers\QuizSocialObserver::class);

        // Public website Blade components live outside resources/views/components,
        // so their directory is registered under the "website" namespace:
        // <x-website.quiz-card /> resolves to views/website/components/quiz-card.
        \Illuminate\Support\Facades\Blade::anonymousComponentPath(
            resource_path('views/website/components'),
            'website'
        );

        // Request throttle for the public analytics tracking endpoint. Configurable
        // via ANALYTICS_ENDPOINT_THROTTLE; keyed per IP so it never affects others.
        \Illuminate\Support\Facades\RateLimiter::for('analytics-track', function ($request) {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(
                (int) config('analytics.endpoint_throttle_per_minute', 90)
            )->by($request->ip());
        });

        if (!cache()->get('SystemInstalled')) {
            $envFilePath = base_path('.env');
            if (!file_exists($envFilePath)) {
                header('Location: install');
                exit;
            }
            $envContents = file_get_contents($envFilePath);
            if (empty($envContents)) {
                header('Location: install');
                exit;
            } else {
                cache()->put('SystemInstalled', true);
            }
        }

        $viewShare['emptyMessage'] = 'Data not found';
        view()->share($viewShare);

        view()->composer('admin.partials.sidenav', function ($view) {
            $view->with([
                'bannedUsersCount'           => User::banned()->count(),
                'emailUnverifiedUsersCount'  => User::emailUnverified()->count(),
                'mobileUnverifiedUsersCount' => User::mobileUnverified()->count(),
                'kycUnverifiedUsersCount'    => User::kycUnverified()->count(),
                'kycPendingUsersCount'       => User::kycPending()->count(),
                'pendingTicketCount'         => SupportTicket::whereIN('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count(),
                'pendingDepositsCount'       => Deposit::pending()->count(),
                'updateAvailable'            => version_compare(gs('available_version'), systemDetails()['version'], '>') ? 'v' . gs('available_version') : false,
            ] + $this->socialSidebarCounters());
        });

        view()->composer('admin.partials.topnav', function ($view) {
            $view->with([
                'adminNotifications'     => AdminNotification::where('is_read', Status::NO)->with('user')->orderBy('id', 'desc')->take(10)->get(),
                'adminNotificationCount' => AdminNotification::where('is_read', Status::NO)->count(),
            ]);
        });

        view()->composer('partials.seo', function ($view) {
            $seo = Frontend::where('data_keys', 'seo.data')->first();
            $view->with([
                'seo' => $seo ? $seo->data_values : $seo,
            ]);
        });

        if (gs('force_ssl')) {
            \URL::forceScheme('https');
        }

        Paginator::useBootstrapFive();
    }

    /**
     * Badge counts for the Social Media sidebar section.
     *
     * Wrapped in a try/catch because the sidebar renders on every admin page,
     * including immediately after a deploy where the social tables may not have
     * been migrated yet - a missing table must not take the whole panel down.
     *
     * @return array<string,int>
     */
    protected function socialSidebarCounters(): array
    {
        try {
            return [
                'socialFailedCount'          => \App\Models\Social\SocialPostPlatform::where('status', \App\Constants\SocialStatus::FAILED)->count(),
                'socialPendingApprovalCount' => \App\Models\Social\SocialPost::where('status', \App\Constants\SocialStatus::PENDING_APPROVAL)->count(),
                'socialUnreadCount'          => \App\Models\Social\SocialComment::where('is_read', false)->count(),
            ];
        } catch (\Throwable $e) {
            return [
                'socialFailedCount'          => 0,
                'socialPendingApprovalCount' => 0,
                'socialUnreadCount'          => 0,
            ];
        }
    }
}
