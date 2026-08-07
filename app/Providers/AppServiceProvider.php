<?php

namespace App\Providers;

use App\Events\MediaUploadCompleted;
use App\Events\MediaUploadFailed;
use App\Listeners\LogFailedUpload;
use App\Listeners\NotifyUserOnUploadComplete;
use App\Listeners\RecordFailedQueueJob;
use App\Listeners\RecordScheduledTaskFailure;
use App\Models\Article;
use App\Models\Client;
use App\Models\Media;
use App\Observers\ArticleObserver;
use App\Policies\MediaPolicy;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prefer APP_URL over the incoming request host (Docker often uses "backend").
        // Only force for real domains (contain a dot) — skip localhost / compose service names.
        $appUrl = config('app.url');
        if (is_string($appUrl) && $appUrl !== '') {
            $host = parse_url($appUrl, PHP_URL_HOST);
            if (is_string($host) && str_contains($host, '.')) {
                URL::forceRootUrl(rtrim($appUrl, '/'));
                if (str_starts_with($appUrl, 'https://')) {
                    URL::forceScheme('https');
                }
            }
        }

        Passport::tokensExpireIn(now()->addDays(30));

        Passport::refreshTokensExpireIn(
            now()->addDays(60)
        );

        Gate::policy(Media::class, MediaPolicy::class);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        Event::listen(MediaUploadCompleted::class, NotifyUserOnUploadComplete::class);
        Event::listen(MediaUploadFailed::class, LogFailedUpload::class);
        Event::listen(ScheduledTaskFailed::class, RecordScheduledTaskFailure::class);
        Event::listen(JobFailed::class, RecordFailedQueueJob::class);

        Article::observe(ArticleObserver::class);

        Passport::useClientModel(Client::class);
    }
}
