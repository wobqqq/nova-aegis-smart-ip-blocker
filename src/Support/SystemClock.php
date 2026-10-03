<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Support;

use DateTimeImmutable;
use Illuminate\Support\Facades\Date;
use Override;
use Psr\Clock\ClockInterface;

/**
 * The application's clock, so time travel in tests applies; used when the application binds no clock of its own.
 */
final readonly class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return Date::now()->toImmutable();
    }
}
