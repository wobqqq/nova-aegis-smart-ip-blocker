<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlocker;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerModule;

it('lifts a ban from the console', function (): void {
    configureBlocker(['requests_per_minute' => 1, 'max_tracked_ips' => 10]);

    requestFrom('203.0.113.7');
    requestFrom('203.0.113.7')->assertStatus(429);

    expect(Artisan::call('aegis:smart-ip-blocker:remove-ip', ['ip' => ' 203.0.113.7 ']))->toBe(0)
        ->and(Artisan::output())->toContain('The ban of 203.0.113.7 is lifted.');

    requestFrom('203.0.113.7')->assertOk();
});

it('resets the count of an IP that is not banned, whatever its spelling', function (): void {
    configureBlocker(['requests_per_minute' => 2, 'max_tracked_ips' => 10]);

    requestFrom('2001:db8::7');
    requestFrom('2001:db8::7');

    expect(Artisan::call('aegis:smart-ip-blocker:remove-ip', ['ip' => '2001:0DB8::0007']))->toBe(0)
        ->and(Artisan::output())->toContain('2001:db8::7 was not banned')
        ->and(resolve(SmartIpBlocker::class)->tracked())->not->toHaveKey('2001:db8::7');

    requestFrom('2001:db8::7')->assertOk();
    requestFrom('2001:db8::7')->assertOk();
});

it('refuses to lift a ban for something that is not an IP', function (): void {
    expect(Artisan::call('aegis:smart-ip-blocker:remove-ip', ['ip' => '<script>']))->toBe(1)
        ->and(Artisan::output())->toContain('That is not an IP address.')
        ->and(Artisan::output())->not->toContain('<script>');
});

it('turns itself off from the console and keeps the other settings', function (): void {
    configureBlocker(['requests_per_minute' => 7, 'excluded_ips' => [['ip' => '10.0.0.0/8']]]);

    expect(Artisan::call('aegis:smart-ip-blocker:disable'))->toBe(0)
        ->and(Artisan::output())->toContain('The Smart IP Blocker is off.')
        ->and(Aegis::settings(SmartIpBlockerModule::KEY))->toMatchArray(['enabled' => false, 'requests_per_minute' => 7, 'excluded_ips' => [['ip' => '10.0.0.0/8']]]);

    expect(array_unique(array_map(static fn (): int => requestFrom('203.0.113.7')->getStatusCode(), range(1, 10))))->toBe([200]);
});

it('turns itself off even when the stored settings are broken', function (): void {
    storeRawBlockerSettings(['enabled' => true, 'view' => 'missing::view']);

    expect(Artisan::call('aegis:smart-ip-blocker:disable'))->toBe(0)
        ->and(Artisan::output())->toContain('reset to the defaults')
        ->and(Aegis::settings(SmartIpBlockerModule::KEY)['enabled'] ?? null)->toBeFalse();
});
