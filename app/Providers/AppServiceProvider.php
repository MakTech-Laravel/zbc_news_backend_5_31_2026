<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use App\Models\Client;
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

        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        Passport::useClientModel(Client::class);
    }
}
