<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Console;

use Illuminate\Console\Command;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlocker;
use Wobqqq\AegisSmartIpBlocker\Support\IpRange;

final class RemoveIpCommand extends Command
{
    /** @var string */
    protected $signature = 'aegis:smart-ip-blocker:remove-ip {ip : The IP address to lift the ban from}';

    /** @var string */
    protected $description = 'Lift the ban and reset the request count of an IP address.';

    public function handle(SmartIpBlocker $blocker): int
    {
        $argument = $this->argument('ip');
        $ip = IpRange::normalize(is_string($argument) ? trim($argument) : '');

        if ($ip === null) {
            $this->components->error('That is not an IP address.');

            return self::FAILURE;
        }

        $wasBanned = $blocker->isBanned($ip);
        $blocker->removeIp($ip);

        $this->components->info(sprintf($wasBanned ? 'The ban of %s is lifted.' : '%s was not banned; its request count is reset.', $ip));

        return self::SUCCESS;
    }
}
