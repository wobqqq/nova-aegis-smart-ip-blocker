<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerModule;
use Wobqqq\AegisSmartIpBlocker\Tests\Fixtures\User;
use Wobqqq\AegisSmartIpBlocker\Tests\TestCase;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

function admin(): User
{
    return User::query()->create(['email' => 'admin@example.com', 'is_admin' => true, 'last_login_at' => now()]);
}

function editor(): User
{
    return User::query()->create(['email' => 'editor@example.com', 'is_admin' => false, 'last_login_at' => now()]);
}

/**
 * Saves the section from the console side, where the lock-out rule does not apply.
 *
 * @param array<string, mixed> $values
 *
 * @return array<string, mixed>
 */
function configureBlocker(array $values): array
{
    app()->instance('request', Request::create('/'));

    return Aegis::save(
        SmartIpBlockerModule::KEY,
        $values + ['enabled' => true] + resolve(SmartIpBlockerModule::class)->defaults(),
    );
}

/**
 * Writes the stored row as a hand edit or an older release would, then saves the core's own section unchanged so that Aegis reads the rows again.
 *
 * @param array<string, mixed> $values
 */
function storeRawBlockerSettings(array $values): void
{
    DB::table('aegis_settings')->updateOrInsert(['section' => SmartIpBlockerModule::KEY], ['values' => json_encode($values, JSON_THROW_ON_ERROR)]);

    Aegis::save('hardening', Aegis::settings('hardening'));
}

/**
 * @param array<string, string> $headers
 *
 * @return TestResponse<Response>
 */
function requestFrom(string $ip, string $uri = '/page', array $headers = []): TestResponse
{
    return Pest\Laravel\withServerVariables(['REMOTE_ADDR' => $ip])->get($uri, $headers);
}
