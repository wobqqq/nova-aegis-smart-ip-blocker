<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Checks;

use Override;
use Wobqqq\Aegis\Checks\CheckResult;
use Wobqqq\Aegis\Contracts\Check;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlocker;
use Wobqqq\AegisSmartIpBlocker\Support\Message;

/**
 * A client writes its own headers, so a header exclusion is a way around the limit for anyone who knows it.
 */
final readonly class ExcludedHeadersCheck implements Check
{
    private const string KEY = 'smart-ip-blocker-headers';

    public function __construct(private SmartIpBlocker $blocker)
    {
    }

    #[Override]
    public function run(): CheckResult
    {
        $label = Message::get('aegis-smart-ip-blocker::smart-ip-blocker.checks.headers.label');
        $settings = $this->blocker->settings();

        if (!$settings->enabled) {
            return CheckResult::info(self::KEY, $label, Message::get('aegis-smart-ip-blocker::smart-ip-blocker.checks.off'));
        }

        return $settings->excludedHeaders === []
            ? CheckResult::pass(self::KEY, $label, Message::get('aegis-smart-ip-blocker::smart-ip-blocker.checks.headers.pass'))
            : CheckResult::warn(self::KEY, $label, Message::get('aegis-smart-ip-blocker::smart-ip-blocker.checks.headers.warn', [
                'headers' => implode(', ', array_keys($settings->excludedHeaders)),
            ]));
    }
}
