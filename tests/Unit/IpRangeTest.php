<?php

declare(strict_types=1);

use Wobqqq\AegisSmartIpBlocker\Support\IpRange;

it('matches an address against an IP or a subnet', function (string $range, string $ip, bool $matches): void {
    expect(IpRange::parse($range)?->contains($ip))->toBe($matches);
})->with([
    ['203.0.113.10', '203.0.113.10', true],
    ['203.0.113.10', '203.0.113.11', false],
    ['10.0.0.0/8', '10.255.1.2', true],
    ['10.0.0.0/8', '11.0.0.1', false],
    ['192.168.1.128/25', '192.168.1.200', true],
    ['192.168.1.128/25', '192.168.1.100', false],
    ['0.0.0.0/0', '8.8.8.8', true],
    ['2001:db8::/32', '2001:0DB8:ffff::1', true],
    ['2001:db8::/32', '2001:db9::1', false],
    ['10.0.0.0/8', '::ffff:10.0.0.1', false],
    ['10.0.0.0/8', 'not-an-ip', false],
]);

it('refuses what is not an IP or a subnet', function (string $value): void {
    expect(IpRange::parse($value))->toBeNull();
})->with(['', 'nope', '10.0.0.0/33', '2001:db8::/129', '10.0.0.0/', '10.0.0.0/-1', '10.0.0.0/8/8', '300.1.1.1']);

it('writes every spelling of an address the same way', function (): void {
    expect(IpRange::normalize('2001:0DB8:0000::0001'))->toBe('2001:db8::1')
        ->and(IpRange::normalize('203.0.113.7'))->toBe('203.0.113.7')
        ->and(IpRange::normalize('nope'))->toBeNull()
        ->and(IpRange::parse('2001:db8::1')?->isSingleAddress())->toBeTrue()
        ->and(IpRange::parse('2001:db8::/64')?->isSingleAddress())->toBeFalse();
});
