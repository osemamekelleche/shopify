<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;
use function Illuminate\Filesystem\join_paths;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        $this->registerAdditionalPublishing();

        $this->registerAdditionalRoutes();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');
        $isLocal = true;

        Telescope::filter(
            fn(IncomingEntry $entry) => $isLocal ||
                $entry->isReportableException() ||
                $entry->isFailedRequest() ||
                $entry->isFailedJob() ||
                $entry->isScheduledTask() ||
                $entry->hasMonitoredTag()
        );
    }

    private function getFiles($dir, $relativeTo = ''): array
    {
        $files = [];

        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (\in_array($file, ['.', '..'])) {
                    continue;
                }
                $files = [
                    ...$files,
                    ...is_dir($f = join_paths($dir, $file))
                        ? $this->getFiles($f, $relativeTo)
                        : [str_replace($relativeTo, '', $f)]
                ];
            }
            closedir($handle);
        }
        return $files;
    }

    private function getTelescopeViews(): array
    {
        return $this->getFiles(
            $path = base_path('vendor/laravel/telescope/resources/views'),
            $path
        );
    }

    private function registerAdditionalPublishing(): void
    {
        $viewsPath = resource_path('views/vendor/telescope');
        $vendorViewsPath = base_path('vendor/laravel/telescope/resources/views');
        if ($this->app->runningInConsole()) {
            collect($this->getTelescopeViews())->each(
                function ($path) use ($viewsPath, $vendorViewsPath) {
                    $this->publishes([
                        join_paths($vendorViewsPath, $path) => join_paths($viewsPath, $path)
                    ], 'telescope-views');
                }
            );
        }
    }

    private function registerAdditionalRoutes(): void
    {
        Route::group([
            'domain' => config('telescope.domain', null),
            'prefix' => config('telescope.path'),
            'middleware' => 'web',
        ], function () {
            $this->loadRoutesFrom(base_path('routes/vendor/telescope/web.php'));
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define(
            'viewTelescope',
            fn(?User $user) => request()->cookie(TELESCOP_TOKEN_KEY) === getProxySecret()
        );
    }
}
