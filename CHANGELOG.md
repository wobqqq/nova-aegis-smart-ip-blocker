# Changelog

All notable changes are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [semantic versioning](https://semver.org/).

## [Unreleased]

### Changed

- Internal refactoring along the architecture skills, no change for applications: the blocker takes a `Visit` instead of the request and reads the time from a PSR clock, and the disable command calls `DisableSmartIpBlocker`.

## [1.1.0] - 2026-10-01

### Added

- Laravel 13 support; CI runs the suite on Laravel 12 and 13.
- Works with Aegis 2 as well as Aegis 1.1 or later.

### Changed

- PHP 8.4 or later is required.
- Native types throughout: typed constants and properties, `#[\Override]` on every overriding method, readonly value objects; no behaviour change.

## [1.0.1] - 2026-10-01

### Changed

- Development and CI run on a test double of Nova (`stubs/nova`, not shipped) and need no Nova license; `make test.nova` runs the PHP suite on the real Nova. Nothing changes for applications.
- The README splits the installation into numbered steps.
- The Dependabot config no longer reads the Nova registry.

## [1.0.0] - 2026-10-01

### Added

- The Smart IP Blocker section of **Aegis → Settings**, off until enabled.
- A per-IP request limit counted over a one-minute window, and a ban of 1 to 720 hours for an IP that exceeds it, on the `web` routes and in Nova.
- `429 Too Many Requests` with `Retry-After` for a banned IP: a configurable Blade view, or JSON for clients that ask for it.
- Excluded IPs and CIDR subnets (IPv4 and IPv6, every spelling of an address matches) and excluded headers matched as a case-insensitive substring.
- Lock-out protection: the administrator's IP is preset in the excluded IPs, and the blocker cannot be saved enabled from the browser unless it stays excluded.
- An optional limit on the number of IPs counted at once, which drops the oldest count and never a ban.
- Checks on the Aegis dashboard: a cache store that keeps the counts between requests, and excluded headers that a client can spoof.
- `aegis:smart-ip-blocker:remove-ip` and `aegis:smart-ip-blocker:disable` console commands.
- Requires Aegis 1.1 or later.

[Unreleased]: https://github.com/wobqqq/nova-aegis-smart-ip-blocker/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/wobqqq/nova-aegis-smart-ip-blocker/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/wobqqq/nova-aegis-smart-ip-blocker/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/wobqqq/nova-aegis-smart-ip-blocker/releases/tag/v1.0.0
