<?php

namespace App\Providers;

use App\Mail\PhpMailerTransport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        Schema::defaultStringLength(191);

        Mail::extend('phpmailer', fn (array $config) => new PhpMailerTransport($config));

        // Force HTTPS for secure Ngrok tunnel asset loading, but keep HTTP for local dev
        if (request()->header('x-forwarded-proto') === 'https') {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }
}
