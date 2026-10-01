<?php

declare(strict_types=1);

use Wobqqq\Aegis\Checks\CheckResult;
use Wobqqq\Aegis\Checks\CheckRunner;
use Wobqqq\Aegis\Contracts\Check;
use Wobqqq\Aegis\Enums\Status;
use Wobqqq\AegisSmartIpBlocker\Checks\CacheStoreCheck;
use Wobqqq\AegisSmartIpBlocker\Checks\ExcludedHeadersCheck;

function check(string $class): CheckResult
{
    $check = resolve($class);

    return $check instanceof Check ? $check->run() : throw new UnexpectedValueException($class);
}

it('registers its checks with Aegis', function (): void {
    $keys = array_map(static fn (CheckResult $result): string => $result->key, resolve(CheckRunner::class)->checks());

    expect($keys)->toContain('smart-ip-blocker-cache', 'smart-ip-blocker-headers');
});

it('only informs while the blocker is off', function (string $class): void {
    expect(check($class)->status)->toBe(Status::INFO);
})->with([CacheStoreCheck::class, ExcludedHeadersCheck::class]);

it('fails on a cache that forgets the counts between requests', function (): void {
    configureBlocker([]);

    config(['cache.default' => 'array']);
    expect(check(CacheStoreCheck::class)->status)->toBe(Status::FAIL);

    config(['aegis.cache_store' => 'file']);
    expect(check(CacheStoreCheck::class)->status)->toBe(Status::PASS)
        ->and(check(CacheStoreCheck::class)->message)->toContain('"file"');

    config(['aegis.cache_store' => 'missing']);
    expect(check(CacheStoreCheck::class)->message)->toContain('"unknown"');
});

it('warns that an excluded header can be sent by anyone', function (): void {
    configureBlocker([]);
    expect(check(ExcludedHeadersCheck::class)->status)->toBe(Status::PASS);

    configureBlocker(['excluded_headers' => [['header' => 'User-Agent', 'value' => 'Googlebot']]]);
    expect(check(ExcludedHeadersCheck::class)->status)->toBe(Status::WARN)
        ->and(check(ExcludedHeadersCheck::class)->message)->toContain('user-agent');
});
