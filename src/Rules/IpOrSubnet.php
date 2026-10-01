<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Wobqqq\AegisSmartIpBlocker\Support\IpRange;

final readonly class IpOrSubnet implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !IpRange::parse($value) instanceof IpRange) {
            $fail((string)__('aegis-smart-ip-blocker::smart-ip-blocker.validation.ip'));
        }
    }
}
