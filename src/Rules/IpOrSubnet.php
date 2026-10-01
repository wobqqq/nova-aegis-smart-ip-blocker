<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;
use Wobqqq\AegisSmartIpBlocker\Support\IpRange;
use Wobqqq\AegisSmartIpBlocker\Support\Message;

final readonly class IpOrSubnet implements ValidationRule
{
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !IpRange::parse($value) instanceof IpRange) {
            $fail(Message::get('aegis-smart-ip-blocker::smart-ip-blocker.validation.ip'));
        }
    }
}
