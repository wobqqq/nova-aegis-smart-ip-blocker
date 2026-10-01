<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\Request;
use Override;
use Wobqqq\Aegis\Support\Values;
use Wobqqq\AegisSmartIpBlocker\Support\IpRange;
use Wobqqq\AegisSmartIpBlocker\Support\Message;
use Wobqqq\AegisSmartIpBlocker\Support\Rows;

/**
 * The administrator who turns the blocker on from the browser must stay excluded from it.
 */
final class CoversCurrentIp implements DataAwareRule, ValidationRule
{
    /** @var array<mixed> */
    private array $data = [];

    public function __construct(private readonly Request $request)
    {
    }

    /**
     * @param array<mixed> $data
     */
    #[Override]
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $ip = IpRange::normalize((string)$this->request->ip());

        // Without a route the save comes from the console, where there is no administrator to lock out.
        if ($ip === null || $this->request->route() === null || !Values::bool(['enabled' => $this->data['enabled'] ?? false], 'enabled')) {
            return;
        }

        foreach (Rows::of(['rows' => $value], 'rows') as $row) {
            if (IpRange::parse(Rows::text($row, 'ip'))?->contains($ip) === true) {
                return;
            }
        }

        $fail(Message::get('aegis-smart-ip-blocker::smart-ip-blocker.validation.current_ip', ['ip' => $ip]));
    }
}
