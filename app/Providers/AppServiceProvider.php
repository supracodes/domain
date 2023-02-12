<?php

namespace App\Providers;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        Http::macro('seoMetrics', function () {
            return Http::withOptions([
                RequestOptions::HEADERS => [
                    'X-RapidAPI-Host' => 'seo-metrics1.p.rapidapi.com',
                    'X-RapidAPI-Key' => 'a16e2d87fdmsh0ef0c55fd0d2370p17c1d6jsn979b926159b0',
                ],
            ])->baseUrl('https://seo-metrics1.p.rapidapi.com');
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }
}
