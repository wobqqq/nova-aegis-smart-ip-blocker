<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker;

/**
 * What the blocker needs of a request: who sent it and the headers the exclusions may match.
 */
final readonly class Visit
{
    /**
     * @param array<string, list<string|null>> $headers lowercase names
     */
    public function __construct(public ?string $ip, public array $headers = [])
    {
    }

    /**
     * @return list<string|null>
     */
    public function header(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }
}
