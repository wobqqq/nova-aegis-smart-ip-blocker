<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Console;

use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Wobqqq\Aegis\Aegis;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerModule;

final class DisableCommand extends Command
{
    /** @var string */
    protected $signature = 'aegis:smart-ip-blocker:disable';

    /** @var string */
    protected $description = 'Turn the Smart IP Blocker off, for an administrator it locked out.';

    public function handle(SmartIpBlockerModule $module): int
    {
        try {
            Aegis::save(SmartIpBlockerModule::KEY, array_replace(Aegis::settings(SmartIpBlockerModule::KEY), ['enabled' => false]));
        } catch (ValidationException) {
            // A stored value the rules now refuse must not keep the blocker on.
            Aegis::save(SmartIpBlockerModule::KEY, ['enabled' => false] + $module->defaults());
            $this->components->warn('The stored settings were invalid and have been reset to the defaults.');
        }

        $this->components->info('The Smart IP Blocker is off.');

        return self::SUCCESS;
    }
}
