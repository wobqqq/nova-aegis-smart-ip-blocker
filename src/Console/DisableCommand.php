<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Console;

use Illuminate\Console\Command;
use Wobqqq\AegisSmartIpBlocker\Actions\DisableSmartIpBlocker;

final class DisableCommand extends Command
{
    /** @var string */
    protected $signature = 'aegis:smart-ip-blocker:disable';

    /** @var string */
    protected $description = 'Turn the Smart IP Blocker off, for an administrator it locked out.';

    public function handle(DisableSmartIpBlocker $disable): int
    {
        if ($disable->handle()) {
            $this->components->warn('The stored settings were invalid and have been reset to the defaults.');
        }

        $this->components->info('The Smart IP Blocker is off.');

        return self::SUCCESS;
    }
}
