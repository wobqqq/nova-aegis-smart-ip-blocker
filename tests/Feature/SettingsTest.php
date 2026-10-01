<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlocker;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerModule;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerSettings;

use function Pest\Laravel\actingAs;

/**
 * @param array<string, mixed> $values
 *
 * @return array<string, mixed>
 */
function blockerValues(array $values = []): array
{
    return $values + ['enabled' => true, 'excluded_ips' => [['ip' => '127.0.0.1']]] + resolve(SmartIpBlockerModule::class)->defaults();
}

it("adds its section to the Aegis settings with the administrator's IP already excluded", function (): void {
    $response = actingAs(admin())->getJson('/nova-vendor/aegis/settings')->assertOk();

    /** @var list<array<string, mixed>> $sections */
    $sections = $response->json('sections');
    $section = collect($sections)->firstWhere('key', SmartIpBlockerModule::KEY);

    expect(data_get($section, 'label'))->toBe('Smart IP Blocker')
        ->and(data_get($section, 'values.enabled'))->toBeFalse()
        ->and(data_get($section, 'values.excluded_ips'))->toBe([['ip' => '127.0.0.1']])
        ->and(data_get($section, 'fields.5.help'))->toContain('127.0.0.1')
        ->and(data_get($section, 'fields.5.columns.0.name'))->toBe('ip');
});

it('keeps the preset out of the values every other request reads', function (): void {
    expect(Aegis::settings(SmartIpBlockerModule::KEY)['excluded_ips'] ?? null)->toBe([])
        ->and(resolve(SmartIpBlocker::class)->settings()->excludedIps)->toBe([]);
});

it("refuses to turn the blocker on unless the administrator's own IP is excluded", function (): void {
    actingAs($admin = admin())->putJson('/nova-vendor/aegis/settings/smart-ip-blocker', ['values' => blockerValues(['excluded_ips' => [['ip' => '198.51.100.10']]])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['excluded_ips' => 'Add your own IP address, 127.0.0.1']);

    actingAs($admin)->putJson('/nova-vendor/aegis/settings/smart-ip-blocker', ['values' => blockerValues(['excluded_ips' => []])])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('excluded_ips');

    actingAs($admin)->putJson('/nova-vendor/aegis/settings/smart-ip-blocker', ['values' => blockerValues(['excluded_ips' => [['ip' => '127.0.0.0/8']]])])
        ->assertOk()
        ->assertJsonPath('values.enabled', true);

    actingAs($admin)->putJson('/nova-vendor/aegis/settings/smart-ip-blocker', ['values' => blockerValues(['enabled' => false, 'excluded_ips' => []])])
        ->assertOk();
});

it('refuses invalid values', function (string $field, mixed $value): void {
    $errors = actingAs(admin())->putJson('/nova-vendor/aegis/settings/smart-ip-blocker', ['values' => blockerValues([$field => $value])])
        ->assertUnprocessable()
        ->json('errors');

    $keys = is_array($errors) ? array_map(strval(...), array_keys($errors)) : [];

    expect($keys)->not->toBeEmpty()
        ->and(array_filter($keys, static fn (string $key): bool => !str_starts_with($key, $field)))->toBe([]);
})->with([
    'no requests' => ['requests_per_minute', 0],
    'too many requests' => ['requests_per_minute', 10_001],
    'a ban too long' => ['ban_hours', 721],
    'enabled as text' => ['enabled', 'sometimes'],
    'a view path' => ['view', '../../.env'],
    'a view with markup' => ['view', '<script>alert(1)</script>'],
    'a missing view' => ['view', 'missing::view'],
    'too many tracked IPs' => ['max_tracked_ips', 10_001],
    'not an IP' => ['excluded_ips', [['ip' => '127.0.0.1'], ['ip' => 'not-an-ip']]],
    'a bad prefix' => ['excluded_ips', [['ip' => '127.0.0.1'], ['ip' => '10.0.0.0/33']]],
    'too many IPs' => ['excluded_ips', array_fill(0, 151, ['ip' => '127.0.0.1'])],
    'a header name with spaces' => ['excluded_headers', [['header' => 'User Agent', 'value' => 'bot']]],
    'a header without a value' => ['excluded_headers', [['header' => 'User-Agent', 'value' => '']]],
    'a value without a header' => ['excluded_headers', [['header' => '', 'value' => 'bot']]],
    'a value too long' => ['excluded_headers', [['header' => 'User-Agent', 'value' => str_repeat('a', 256)]]],
]);

it('keeps only the columns it knows in the stored rows', function (): void {
    $saved = configureBlocker(['excluded_ips' => [['ip' => '10.0.0.1', 'note' => '<b>x</b>']]]);

    expect($saved['excluded_ips'] ?? null)->toBe([['ip' => '10.0.0.1']]);
});

it('reads stored values the rules would refuse with safe fallbacks', function (): void {
    storeRawBlockerSettings([
        'enabled' => 'yes',
        'requests_per_minute' => 'many',
        'ban_hours' => 100_000,
        'view' => '../secret',
        'max_tracked_ips' => -5,
        'excluded_ips' => [['ip' => 'nope'], ['ip' => '10.0.0.0/99'], 'junk', ['ip' => '192.0.2.0/24'], ['ip' => '192.0.2.1']],
        'excluded_headers' => 'junk',
    ]);

    $settings = resolve(SmartIpBlocker::class)->settings();

    expect($settings->enabled)->toBeTrue()
        ->and($settings->requestsPerMinute)->toBe(100)
        ->and($settings->banHours)->toBe(720)
        ->and($settings->view)->toBe(SmartIpBlockerSettings::DEFAULT_VIEW)
        ->and($settings->maxTrackedIps)->toBe(0)
        ->and($settings->excludedIps)->toBe(['192.0.2.1' => true])
        ->and($settings->excludedRanges)->toHaveCount(1)
        ->and($settings->excludedHeaders)->toBe([]);
});

it('drops header rules a hand-written row breaks', function (): void {
    $settings = SmartIpBlockerSettings::fromArray(['excluded_headers' => [
        ['header' => 'User-Agent', 'value' => 'Bot'],
        ['header' => 'user-agent', 'value' => 'bot'],
        ['header' => 'X Bad', 'value' => 'bot'],
        ['header' => 'X-Long', 'value' => str_repeat('a', 300)],
        ['header' => 'X-Array', 'value' => ['bot']],
        'junk',
    ]]);

    expect($settings->excludedHeaders)->toBe(['user-agent' => ['bot']]);
});

it('reports its state on the Aegis overview', function (): void {
    actingAs($admin = admin())->getJson('/nova-vendor/aegis/overview')
        ->assertOk()
        ->assertJsonFragment(['key' => SmartIpBlockerModule::KEY, 'status' => 'warn']);

    configureBlocker(['requests_per_minute' => 30, 'ban_hours' => 4]);

    actingAs($admin)->getJson('/nova-vendor/aegis/overview')
        ->assertJsonFragment(['key' => SmartIpBlockerModule::KEY, 'status' => 'pass', 'message' => 'On: more than 30 requests a minute bans an IP for 4 h.']);
});

it('validates a save from code as well', function (): void {
    configureBlocker(['requests_per_minute' => -1]);
})->throws(ValidationException::class);
