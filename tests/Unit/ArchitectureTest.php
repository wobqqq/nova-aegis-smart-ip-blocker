<?php

declare(strict_types=1);

arch('every file declares strict types')
    ->expect('Wobqqq\AegisSmartIpBlocker')
    ->toUseStrictTypes();

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'var_dump', 'print_r', 'ray', 'die', 'exit'])
    ->not->toBeUsed();

arch('classes are final')
    ->expect('Wobqqq\AegisSmartIpBlocker')
    ->classes()
    ->toBeFinal();

arch('value objects are immutable')
    ->expect([Wobqqq\AegisSmartIpBlocker\SmartIpBlockerSettings::class, Wobqqq\AegisSmartIpBlocker\Support\IpRange::class])
    ->toBeReadonly();

arch('the module reaches the core through its public contract only')
    ->expect('Wobqqq\AegisSmartIpBlocker')
    ->not->toUse([
        Wobqqq\Aegis\AegisServiceProvider::class,
        'Wobqqq\Aegis\Audit',
        Wobqqq\Aegis\Checks\CheckRegistry::class,
        Wobqqq\Aegis\Checks\CheckRunner::class,
        'Wobqqq\Aegis\Hardening',
        'Wobqqq\Aegis\Http',
        'Wobqqq\Aegis\Modules',
        'Wobqqq\Aegis\Nova',
        'Wobqqq\Aegis\Scanners',
        Wobqqq\Aegis\Settings\AegisSetting::class,
        Wobqqq\Aegis\Settings\SettingsRepository::class,
    ]);

arch('nothing opens a network connection')
    ->expect('Wobqqq\AegisSmartIpBlocker')
    ->not->toUse(['stream_socket_client', 'fsockopen', 'curl_init', 'file_get_contents', Illuminate\Support\Facades\Http::class]);
