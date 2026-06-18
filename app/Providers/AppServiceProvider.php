<?php

namespace App\Providers;

use App\Events\MediaUploadCompleted;
use App\Events\MediaUploadFailed;
use App\Listeners\LogFailedUpload;
use App\Listeners\NotifyUserOnUploadComplete;
use App\Models\Article;
use App\Models\Client;
use App\Models\Media;
use App\Observers\ArticleObserver;
use App\Policies\MediaPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        Article::observe(ArticleObserver::class);

        Passport::useClientModel(Client::class);
    }
}
