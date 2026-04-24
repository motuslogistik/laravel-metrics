<?php

namespace motuslogistik\Metrics;

use Illuminate\Support\Facades\Route;
use motuslogistik\Metrics\Contracts\Store;
use motuslogistik\Metrics\Http\Controllers\MetricsController;
use motuslogistik\Metrics\Stores\ArrayStore;
use motuslogistik\Metrics\Stores\SwooleTableStore;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MetricsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('metrics')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        $this->registerStore();
        $this->registerRoute();
    }

    private function registerStore(): void
    {
        $store = config('metrics.store') ?? ArrayStore::class;

        if ($store === SwooleTableStore::class) {
            $this->app->singleton(Store::class, fn () => new SwooleTableStore(
                size: (int) config('metrics.swoole.size', 4096),
                stringSize: (int) config('metrics.swoole.string_size', 64),
            ));

            // Create the Swoole tables before Octane forks workers.
            $this->app->make(Store::class);

            return;
        }

        $this->app->singleton(Store::class, $store);
    }

    private function registerRoute(): void
    {
        if (! config('metrics.route.enabled', true)) {
            return;
        }

        Route::middleware(config('metrics.route.middleware', []))
            ->get(config('metrics.route.path', '/metrics'), MetricsController::class)
            ->name('metrics');
    }
}
