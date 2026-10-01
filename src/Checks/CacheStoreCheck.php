<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Checks;

use Illuminate\Contracts\Config\Repository as Config;
use Wobqqq\Aegis\Checks\CheckResult;
use Wobqqq\Aegis\Contracts\Check;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlocker;

/**
 * The counters and bans live in the cache: a store that forgets them between requests blocks nobody.
 */
final readonly class CacheStoreCheck implements Check
{
    private const KEY = 'smart-ip-blocker-cache';

    public function __construct(private SmartIpBlocker $blocker, private Config $config)
    {
    }

    public function run(): CheckResult
    {
        $label = (string)__('aegis-smart-ip-blocker::smart-ip-blocker.checks.cache.label');

        if (!$this->blocker->settings()->enabled) {
            return CheckResult::info(self::KEY, $label, (string)__('aegis-smart-ip-blocker::smart-ip-blocker.checks.off'));
        }

        $store = $this->config->get('aegis.cache_store');
        $store = is_string($store) && $store !== '' ? $store : $this->config->get('cache.default');
        $driver = is_string($store) ? $this->config->get('cache.stores.' . $store . '.driver') : null;
        $driver = is_string($driver) ? $driver : 'unknown';

        return in_array($driver, ['array', 'null'], true)
            ? CheckResult::fail(self::KEY, $label, (string)__('aegis-smart-ip-blocker::smart-ip-blocker.checks.cache.fail', ['driver' => $driver]))
            : CheckResult::pass(self::KEY, $label, (string)__('aegis-smart-ip-blocker::smart-ip-blocker.checks.cache.pass', ['driver' => $driver]));
    }
}
