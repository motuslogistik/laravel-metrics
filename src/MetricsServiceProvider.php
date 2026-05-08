<?php

namespace motuslogistik\Metrics;

use Illuminate\Support\Facades\Route;
use motuslogistik\Metrics\Contracts\Store;
use motuslogistik\Metrics\Exporters\PrometheusExporter;
use motuslogistik\Metrics\Http\Controllers\MetricsController;
use motuslogistik\Metrics\Stores\ArrayStore;
use motuslogistik\Metrics\Stores\RedisStore;
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
        $this->registerStores();
        $this->registerExporter();
        $this->registerRoute();
    }

    private function registerExporter(): void
    {
        $this->app->bind(
            PrometheusExporter::class,
            fn () => new PrometheusExporter(Metrics::stores()),
        );
    }

    private function registerStores(): void
    {
        $this->bindStore(Store::class, config('metrics.store') ?? ArrayStore::class);

        $globalStore = config('metrics.global_store');
        if ($globalStore !== null) {
            $this->bindStore(Metrics::GLOBAL_STORE, $globalStore);
        }
    }

    private function bindStore(string $abstract, string $store): void
    {
        if ($store === RedisStore::class) {
            $this->app->singleton($abstract, fn () => new RedisStore(
                connection: config('metrics.redis.connection'),
            ));

            return;
        }

        if ($store === SwooleTableStore::class) {
            $this->app->singleton($abstract, fn () => new SwooleTableStore(
                size: (int) config('metrics.swoole.size', 4096),
                stringSize: (int) config('metrics.swoole.string_size', 64),
            ));

            $this->app->make($abstract);

            return;
        }

        $this->app->singleton($abstract, $store);
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
