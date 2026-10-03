<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Wobqqq\Aegis\AegisServiceProvider;
use Wobqqq\Aegis\Checks\CheckRegistry;
use Wobqqq\Aegis\Checks\CheckRunner;
use Wobqqq\Aegis\Settings\AegisSetting;
use Wobqqq\Aegis\Settings\SettingsRepository;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlocker;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerSettings;
use Wobqqq\AegisSmartIpBlocker\Support\IpRange;

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
    ->expect([SmartIpBlockerSettings::class, IpRange::class])
    ->toBeReadonly();

arch('the module reaches the core through its public contract only')
    ->expect('Wobqqq\AegisSmartIpBlocker')
    ->not->toUse([
        AegisServiceProvider::class,
        'Wobqqq\Aegis\Audit',
        CheckRegistry::class,
        CheckRunner::class,
        'Wobqqq\Aegis\Hardening',
        'Wobqqq\Aegis\Http',
        'Wobqqq\Aegis\Modules',
        'Wobqqq\Aegis\Nova',
        'Wobqqq\Aegis\Scanners',
        AegisSetting::class,
        SettingsRepository::class,
    ]);

arch('nothing opens a network connection')
    ->expect('Wobqqq\AegisSmartIpBlocker')
    ->not->toUse(['stream_socket_client', 'fsockopen', 'curl_init', 'file_get_contents', Http::class]);

arch('the blocker reads requests and time only through what it is given')
    ->expect([SmartIpBlocker::class, 'Wobqqq\AegisSmartIpBlocker\Actions'])
    ->not->toUse([Request::class, Date::class, 'now']);
