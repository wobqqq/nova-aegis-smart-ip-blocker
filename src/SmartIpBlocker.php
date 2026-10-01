<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisSmartIpBlocker\Support\IpRange;

final class SmartIpBlocker
{
    public const int WINDOW_SECONDS = 60;

    /**
     * Part of every cache key: a release that changes what is cached bumps it.
     */
    public const string KEY_PREFIX = 'aegis.smart-ip-blocker.v1.';

    private ?SmartIpBlockerSettings $settings = null;

    public function __construct(private readonly Cache $cache)
    {
    }

    public function settings(): SmartIpBlockerSettings
    {
        return $this->settings ??= SmartIpBlockerSettings::fromArray(Aegis::settings(SmartIpBlockerModule::KEY));
    }

    public function forget(): void
    {
        $this->settings = null;
    }

    /**
     * Counts the request and answers the seconds its IP has to wait, or null when it may pass.
     */
    public function hit(Request $request): ?int
    {
        $settings = $this->settings();
        $ip = IpRange::normalize((string)$request->ip());

        if (!$settings->enabled || $ip === null || $this->isExcluded($settings, $ip, $request)) {
            return null;
        }

        $now = Date::now()->getTimestamp();
        $bannedUntil = $this->cache->get($this->banKey($ip));

        if (is_int($bannedUntil) && $bannedUntil > $now) {
            return $bannedUntil - $now;
        }

        $rateKey = $this->rateKey($ip);
        $added = $this->cache->add($rateKey, 0, self::WINDOW_SECONDS);
        $hits = (int)$this->cache->increment($rateKey);

        if (!$added && $hits === 1) {
            // The counter expired between add() and increment(), which some stores recreate without a TTL.
            $this->cache->put($rateKey, 1, self::WINDOW_SECONDS);
        }

        if ($hits > $settings->requestsPerMinute) {
            $seconds = $settings->banHours * 3600;
            $this->cache->put($this->banKey($ip), $now + $seconds, $seconds);
            $this->cache->forget($rateKey);

            return $seconds;
        }

        if ($hits === 1 && $settings->maxTrackedIps > 0) {
            $this->track($ip, $now, $settings->maxTrackedIps);
        }

        return null;
    }

    public function isBanned(string $ip): bool
    {
        $ip = IpRange::normalize($ip);
        $bannedUntil = $ip === null ? null : $this->cache->get($this->banKey($ip));

        return is_int($bannedUntil) && $bannedUntil > Date::now()->getTimestamp();
    }

    public function removeIp(string $ip): void
    {
        $ip = IpRange::normalize($ip) ?? $ip;

        $this->cache->forget($this->rateKey($ip));
        $this->cache->forget($this->banKey($ip));

        $tracked = $this->tracked();

        if (isset($tracked[$ip])) {
            unset($tracked[$ip]);
            $this->cache->put($this->trackedKey(), $tracked, self::WINDOW_SECONDS * 2);
        }
    }

    /**
     * The IPs whose counter is live, oldest first.
     *
     * @return array<string, int> IP => the time its counter started
     */
    public function tracked(): array
    {
        $tracked = $this->cache->get($this->trackedKey());
        $since = Date::now()->getTimestamp() - self::WINDOW_SECONDS;
        $result = [];

        foreach (is_array($tracked) ? $tracked : [] as $ip => $startedAt) {
            if (is_int($startedAt) && $startedAt > $since) {
                $result[(string)$ip] = $startedAt;
            }
        }

        return $result;
    }

    private function isExcluded(SmartIpBlockerSettings $settings, string $ip, Request $request): bool
    {
        if (isset($settings->excludedIps[$ip])) {
            return true;
        }

        foreach ($settings->excludedRanges as $range) {
            if ($range->contains($ip)) {
                return true;
            }
        }

        foreach ($settings->excludedHeaders as $header => $needles) {
            foreach ($request->headers->all($header) as $value) {
                $value = mb_strtolower((string)$value);

                foreach ($needles as $needle) {
                    if (str_contains($value, $needle)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Keeps at most $max live counters: past it, the oldest counter is dropped, never a ban.
     */
    private function track(string $ip, int $now, int $max): void
    {
        $tracked = $this->tracked();
        unset($tracked[$ip]);

        while (count($tracked) >= $max) {
            $oldest = (string)array_key_first($tracked);
            unset($tracked[$oldest]);
            $this->cache->forget($this->rateKey($oldest));
        }

        $tracked[$ip] = $now;

        $this->cache->put($this->trackedKey(), $tracked, self::WINDOW_SECONDS * 2);
    }

    private function rateKey(string $ip): string
    {
        return self::KEY_PREFIX . 'rate.' . $ip;
    }

    private function banKey(string $ip): string
    {
        return self::KEY_PREFIX . 'ban.' . $ip;
    }

    private function trackedKey(): string
    {
        return self::KEY_PREFIX . 'tracked';
    }
}
