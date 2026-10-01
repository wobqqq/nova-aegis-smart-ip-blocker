<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Support;

/**
 * One IP address or CIDR subnet, compared on its binary form so that every spelling of an IPv6 address matches.
 */
final readonly class IpRange
{
    private function __construct(public string $bytes, public int $prefix)
    {
    }

    public static function parse(string $value): ?self
    {
        $value = trim($value);
        $parts = explode('/', $value, 2);
        $bytes = filter_var($parts[0], FILTER_VALIDATE_IP) === false ? false : inet_pton($parts[0]);

        if ($bytes === false) {
            return null;
        }

        $bits = strlen($bytes) * 8;

        if (!isset($parts[1])) {
            return new self($bytes, $bits);
        }

        if (preg_match('/^\d{1,3}$/', $parts[1]) !== 1 || (int)$parts[1] > $bits) {
            return null;
        }

        return new self($bytes, (int)$parts[1]);
    }

    /**
     * The canonical text form of an IP address, or null when it is not one.
     */
    public static function normalize(string $ip): ?string
    {
        $bytes = filter_var($ip, FILTER_VALIDATE_IP) === false ? false : inet_pton($ip);
        $text = $bytes === false ? false : inet_ntop($bytes);

        return $text === false ? null : $text;
    }

    public function isSingleAddress(): bool
    {
        return $this->prefix === strlen($this->bytes) * 8;
    }

    public function address(): string
    {
        return (string)inet_ntop($this->bytes);
    }

    public function contains(string $ip): bool
    {
        $bytes = filter_var($ip, FILTER_VALIDATE_IP) === false ? false : inet_pton($ip);

        if ($bytes === false || strlen($bytes) !== strlen($this->bytes)) {
            return false;
        }

        $whole = intdiv($this->prefix, 8);

        if (strncmp($bytes, $this->bytes, $whole) !== 0) {
            return false;
        }

        $rest = $this->prefix % 8;

        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($bytes[$whole]) & $mask) === (ord($this->bytes[$whole]) & $mask);
    }
}
