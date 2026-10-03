<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Actions;

use Illuminate\Validation\ValidationException;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerModule;

final readonly class DisableSmartIpBlocker
{
    public function __construct(private SmartIpBlockerModule $module)
    {
    }

    /**
     * @return bool true when the stored settings were invalid and have been reset to the defaults
     */
    public function handle(): bool
    {
        try {
            Aegis::save(SmartIpBlockerModule::KEY, array_replace(Aegis::settings(SmartIpBlockerModule::KEY), ['enabled' => false]));

            return false;
        } catch (ValidationException) {
            // A stored value the rules now refuse must not keep the blocker on.
            Aegis::save(SmartIpBlockerModule::KEY, ['enabled' => false] + $this->module->defaults());

            return true;
        }
    }
}
