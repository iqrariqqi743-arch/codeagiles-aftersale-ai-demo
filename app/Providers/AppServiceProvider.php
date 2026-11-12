<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
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
         ResetPassword::createUrlUsing(function (User $user, string $token) {
            return rtrim(config('app.url'), '/')
                . '/reset-password?token=' . urlencode($token)
                . '&email=' . urlencode($user->email);
        });
    }
}
