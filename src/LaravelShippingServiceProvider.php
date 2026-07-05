<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping;

use Illuminate\Support\ServiceProvider;

class LaravelShippingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/shipping.php',
            'shipping'
        );

        $this->app->singleton('shipping', function ($app) {
            return new ShippingManager($app);
        });

        $this->app->alias('shipping', ShippingManager::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/shipping.php' => config_path('shipping.php'),
            ], 'laravel-shipping-config');
        }
    }
}
