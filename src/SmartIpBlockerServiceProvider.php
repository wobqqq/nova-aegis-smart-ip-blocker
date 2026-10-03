<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Override;
use Psr\Clock\ClockInterface;
use Wobqqq\Aegis\Aegis;
use Wobqqq\Aegis\Events\SettingsSaved;
use Wobqqq\AegisSmartIpBlocker\Checks\CacheStoreCheck;
use Wobqqq\AegisSmartIpBlocker\Checks\ExcludedHeadersCheck;
use Wobqqq\AegisSmartIpBlocker\Console\DisableCommand;
use Wobqqq\AegisSmartIpBlocker\Console\RemoveIpCommand;
use Wobqqq\AegisSmartIpBlocker\Http\Middleware\BlockExcessiveRequests;
use Wobqqq\AegisSmartIpBlocker\Support\SystemClock;

final class SmartIpBlockerServiceProvider extends ServiceProvider
{
    /**
     * The site and every Nova route: Nova's groups usually include web, but an application may drop it.
     */
    private const array MIDDLEWARE_GROUPS = ['web', 'nova', 'nova:auth'];

    #[Override]
    public function register(): void
    {
        $this->app->bindIf(ClockInterface::class, SystemClock::class);
        $this->app->singleton(SmartIpBlocker::class, static function (Application $app): SmartIpBlocker {
            $store = $app->make(Config::class)->get('aegis.cache_store');

            return new SmartIpBlocker(
                $app->make(CacheFactory::class)->store(is_string($store) && $store !== '' ? $store : null),
                $app->make(ClockInterface::class),
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'aegis-smart-ip-blocker');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'aegis-smart-ip-blocker');

        Aegis::module($this->app->make(SmartIpBlockerModule::class));
        Aegis::check($this->app->make(CacheStoreCheck::class));
        Aegis::check($this->app->make(ExcludedHeadersCheck::class));

        $this->app->make('events')->listen(SettingsSaved::class, function (SettingsSaved $event): void {
            if ($event->section === SmartIpBlockerModule::KEY) {
                $this->app->make(SmartIpBlocker::class)->forget();
            }
        });

        // Nova defines its groups while it boots, which may come after this provider.
        $this->app->booted(function (): void {
            $router = $this->app->make(Router::class);

            foreach (self::MIDDLEWARE_GROUPS as $group) {
                $router->prependMiddlewareToGroup($group, BlockExcessiveRequests::class);
            }
        });

        if ($this->app->runningInConsole()) {
            $this->commands([RemoveIpCommand::class, DisableCommand::class]);
        }
    }
}
