<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\View;
use Wobqqq\Aegis\Settings\AegisSetting;
use Wobqqq\Aegis\Settings\SettingsRepository;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlocker;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerModule;

use function Pest\Laravel\getJson;
use function Pest\Laravel\travel;
use function Pest\Laravel\withServerVariables;

/**
 * @return list<int>
 */
function statuses(string $ip, int $requests, string $uri = '/page'): array
{
    return array_map(static fn (): int => requestFrom($ip, $uri)->getStatusCode(), range(1, $requests));
}

it('lets every request through until it is enabled', function (): void {
    expect(array_unique(statuses('203.0.113.7', 150)))->toBe([200]);
});

it('bans an IP once it exceeds the requests allowed per minute', function (): void {
    configureBlocker(['requests_per_minute' => 3]);

    expect(statuses('203.0.113.7', 5))->toBe([200, 200, 200, 429, 429])
        ->and(requestFrom('203.0.113.8')->getStatusCode())->toBe(200);
});

it('answers a banned visitor 429 with Retry-After and the blocked page', function (): void {
    configureBlocker(['requests_per_minute' => 1, 'ban_hours' => 2]);

    requestFrom('203.0.113.7');

    requestFrom('203.0.113.7')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '7200')
        ->assertSee('Too many requests')
        ->assertSee('120 minutes')
        ->assertDontSee('page body');
});

it('answers JSON to a client that asks for it', function (): void {
    configureBlocker(['requests_per_minute' => 1]);

    requestFrom('203.0.113.7');

    withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/api-page')
        ->assertStatus(429)
        ->assertHeader('Retry-After', '3600')
        ->assertExactJson(['message' => 'Your IP address sent too many requests and is temporarily blocked.']);
});

it('counts the requests per minute, not per ban duration', function (): void {
    configureBlocker(['requests_per_minute' => 2, 'ban_hours' => 6]);

    expect(statuses('203.0.113.7', 2))->toBe([200, 200]);

    travel(SmartIpBlocker::WINDOW_SECONDS + 1)->seconds();

    expect(statuses('203.0.113.7', 2))->toBe([200, 200]);
});

it('keeps the ban for the configured hours and counts the time down', function (): void {
    configureBlocker(['requests_per_minute' => 1, 'ban_hours' => 2]);

    statuses('203.0.113.7', 2);

    travel(119)->minutes();
    requestFrom('203.0.113.7')->assertStatus(429)->assertHeader('Retry-After', '60');

    travel(2)->minutes();
    requestFrom('203.0.113.7')->assertOk();
});

it('shows the configured view, and its own page when that view is gone', function (): void {
    View::addNamespace('fixtures', __DIR__ . '/../Fixtures/views');
    configureBlocker(['requests_per_minute' => 1, 'view' => 'fixtures::custom']);

    statuses('203.0.113.7', 1);
    requestFrom('203.0.113.7')->assertStatus(429)->assertSee('Custom page, back in 3600 seconds.');

    AegisSetting::query()->where('section', SmartIpBlockerModule::KEY)->update(['values' => json_encode(['view' => 'fixtures::deleted'] + Wobqqq\Aegis\Aegis::settings(SmartIpBlockerModule::KEY))]);
    resolve(SettingsRepository::class)->flush();
    resolve(SmartIpBlocker::class)->forget();

    requestFrom('203.0.113.7')->assertStatus(429)->assertSee('Too many requests');
});

it('never counts an excluded IP, subnet or header', function (): void {
    configureBlocker([
        'requests_per_minute' => 1,
        'excluded_ips' => [['ip' => '198.51.100.10'], ['ip' => '10.0.0.0/8'], ['ip' => '2001:db8::1'], ['ip' => '']],
        'excluded_headers' => [
            ['header' => 'User-Agent', 'value' => 'Googlebot'],
            ['header' => 'User-Agent', 'value' => 'bingbot'],
            ['header' => '', 'value' => ''],
        ],
    ]);

    expect(array_unique(statuses('198.51.100.10', 5)))->toBe([200])
        ->and(array_unique(statuses('10.20.30.40', 5)))->toBe([200])
        ->and(array_unique(statuses('2001:0db8:0000:0000:0000:0000:0000:0001', 5)))->toBe([200]);

    foreach (range(1, 5) as $request) {
        requestFrom('203.0.113.7', '/page', ['User-Agent' => 'Mozilla/5.0 (compatible; GOOGLEBOT/2.1)'])->assertOk();
        requestFrom('203.0.113.7', '/page', ['User-Agent' => 'Mozilla/5.0 (compatible; bingbot/2.0)'])->assertOk();
    }

    expect(statuses('203.0.113.9', 2))->toBe([200, 429]);
});

it('counts every spelling of an IPv6 address as one visitor', function (): void {
    configureBlocker(['requests_per_minute' => 1]);

    requestFrom('2001:db8::5')->assertOk();
    requestFrom('2001:0DB8:0:0:0:0:0:5')->assertStatus(429);
});

it('guards the Nova routes and counts a request once although it passes several groups', function (): void {
    configureBlocker(['requests_per_minute' => 2]);

    $statuses = array_map(
        static fn (): int => withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->getJson('/nova-vendor/aegis/overview')->getStatusCode(),
        range(1, 3),
    );

    expect($statuses)->toBe([401, 401, 429]);
});

it('applies a saved change at once', function (): void {
    expect(resolve(SmartIpBlocker::class)->settings()->enabled)->toBeFalse();

    configureBlocker(['requests_per_minute' => 1]);

    expect(statuses('203.0.113.7', 2))->toBe([200, 429]);
});

it('keeps the application working when the cache fails', function (): void {
    Exceptions::fake();
    configureBlocker(['requests_per_minute' => 1]);

    /** @var CacheRepository&Mockery\MockInterface $cache */
    $cache = Mockery::mock(CacheRepository::class);
    $cache->allows('get')->andThrow(new RuntimeException('cache down'));
    app()->instance(SmartIpBlocker::class, new SmartIpBlocker($cache));

    expect(statuses('203.0.113.7', 3))->toBe([200, 200, 200]);

    Exceptions::assertReported(RuntimeException::class);
});

it('gives a counter that expired between two calls its window back', function (): void {
    configureBlocker(['requests_per_minute' => 5]);

    /** @var CacheRepository&Mockery\MockInterface $cache */
    $cache = Mockery::mock(CacheRepository::class);
    $cache->allows('get')->andReturnNull();
    $cache->allows('add')->andReturnFalse();
    $cache->allows('increment')->andReturn(1);
    $cache->expects('put')->with(SmartIpBlocker::KEY_PREFIX . 'rate.203.0.113.7', 1, SmartIpBlocker::WINDOW_SECONDS);
    app()->instance(SmartIpBlocker::class, new SmartIpBlocker($cache));

    requestFrom('203.0.113.7')->assertOk();
});

it('keeps a bounded list of the IPs it counts and never drops a ban', function (): void {
    configureBlocker(['requests_per_minute' => 1, 'max_tracked_ips' => 50]);
    $blocker = resolve(SmartIpBlocker::class);

    statuses('203.0.113.250', 2);
    requestFrom('10.0.0.1')->assertOk();

    foreach (range(1, 80) as $i) {
        requestFrom('10.1.0.' . $i);
    }

    expect(count($blocker->tracked()))->toBe(50)
        ->and($blocker->tracked())->not->toHaveKey('10.0.0.1')
        ->and($blocker->tracked())->toHaveKey('10.1.0.80')
        ->and($blocker->isBanned('203.0.113.250'))->toBeTrue()
        ->and(requestFrom('10.0.0.1')->getStatusCode())->toBe(200);
});

it('forgets the IPs whose minute is over', function (): void {
    configureBlocker(['max_tracked_ips' => 10]);
    $blocker = resolve(SmartIpBlocker::class);

    requestFrom('10.0.0.1');
    travel(SmartIpBlocker::WINDOW_SECONDS + 1)->seconds();
    requestFrom('10.0.0.2');

    expect(array_keys($blocker->tracked()))->toBe(['10.0.0.2']);
});

it('namespaces its cache keys', function (): void {
    configureBlocker(['requests_per_minute' => 1]);

    statuses('203.0.113.7', 2);

    expect(cache()->has(SmartIpBlocker::KEY_PREFIX . 'ban.203.0.113.7'))->toBeTrue()
        ->and(cache()->has('ban:203.0.113.7'))->toBeFalse();
});

it('ignores a request without a usable IP', function (): void {
    configureBlocker(['requests_per_minute' => 1]);

    expect(statuses('not-an-ip', 3))->toBe([200, 200, 200]);
    getJson('/api-page')->assertOk();
});

it('counts a request once when the middleware runs twice on it', function (): void {
    configureBlocker(['requests_per_minute' => 1]);

    $middleware = resolve(Wobqqq\AegisSmartIpBlocker\Http\Middleware\BlockExcessiveRequests::class);
    $request = Illuminate\Http\Request::create('/page', 'GET', server: ['REMOTE_ADDR' => '203.0.113.7']);
    $next = static fn (): Illuminate\Http\Response => new Illuminate\Http\Response('page body');

    expect($middleware->handle($request, $next)->getStatusCode())->toBe(200)
        ->and($middleware->handle($request, $next)->getStatusCode())->toBe(200);
});
