<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker;

use Wobqqq\AegisSmartIpBlocker\Support\IpRange;
use Wobqqq\AegisSmartIpBlocker\Support\Values;

final readonly class SmartIpBlockerSettings
{
    public const DEFAULT_VIEW = 'aegis-smart-ip-blocker::blocked';

    public const VIEW_PATTERN = '/^(?:[A-Za-z0-9_-]+::)?[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/';

    public const HEADER_PATTERN = '/^[A-Za-z0-9-]{1,50}$/';

    /**
     * @param array<string, true> $excludedIps canonical address => true
     * @param list<IpRange> $excludedRanges
     * @param array<string, list<string>> $excludedHeaders lower-case header => lower-case values it may contain
     */
    public function __construct(
        public bool $enabled,
        public int $requestsPerMinute,
        public int $banHours,
        public string $view,
        public int $maxTrackedIps,
        public array $excludedIps = [],
        public array $excludedRanges = [],
        public array $excludedHeaders = [],
    ) {
    }

    /**
     * Reads the stored values again, whatever they are: a value the rules never saw falls back to its default or is skipped.
     *
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $view = Values::string($values, 'view', self::DEFAULT_VIEW);
        $excludedIps = [];
        $excludedRanges = [];
        $excludedHeaders = [];

        foreach (Values::rows($values, 'excluded_ips') as $row) {
            $range = IpRange::parse(Values::text($row, 'ip'));

            if (!$range instanceof IpRange) {
                continue;
            }

            if ($range->isSingleAddress()) {
                $excludedIps[$range->address()] = true;
            } else {
                $excludedRanges[] = $range;
            }
        }

        foreach (Values::rows($values, 'excluded_headers') as $row) {
            $header = Values::text($row, 'header');
            $value = mb_strtolower(Values::text($row, 'value'));

            if ($value !== '' && mb_strlen($value) <= 255 && preg_match(self::HEADER_PATTERN, $header) === 1) {
                $excludedHeaders[strtolower($header)][] = $value;
            }
        }

        return new self(
            Values::bool($values, 'enabled'),
            Values::int($values, 'requests_per_minute', 100, 1, 10_000),
            Values::int($values, 'ban_hours', 1, 1, 720),
            strlen($view) <= 100 && preg_match(self::VIEW_PATTERN, $view) === 1 ? $view : self::DEFAULT_VIEW,
            Values::int($values, 'max_tracked_ips', 0, 0, 10_000),
            $excludedIps,
            $excludedRanges,
            array_map(static fn (array $needles): array => array_values(array_unique($needles)), $excludedHeaders),
        );
    }
}
