# AGENTS.md

Guidance for coding agents working in this repository.

## What this is

**Smart IP Blocker** (`wobqqq/nova-aegis-smart-ip-blocker`) is an add-on module of [Aegis](https://github.com/wobqqq/nova-aegis), the security suite for Laravel Nova (Laravel 12, PHP 8.2+). It counts the requests of every IP address over a one-minute window and bans an IP that exceeds the limit for a number of hours, on the `web` routes and in Nova, answering `429 Too Many Requests` with `Retry-After` and the page the administrator chose.

It has no page, no table and no frontend of its own: its settings are a section of **Aegis → Settings**, drawn by the core from the module's `fields()`, and its state is a line on the Aegis overview plus two checks.

This is a **security product installed on production applications**. A bug here locks administrators or visitors out, takes the site down, or silently leaves it unprotected. Security and safe upgrades come before everything else.

## The self-check gate (run before every commit)

Everything runs in Docker; the host needs no PHP.

```bash
make install        # composer install
make code.fix       # composer normalize, Rector, PHP CS Fixer
make code.check     # validate --strict, normalize --dry-run, composer audit, php -l, cs, Rector, PHPStan max
make test           # Pest
make test.coverage  # Pest with coverage, failing below 90 %
make ready          # all of the above
```

`make ready` must pass. PHPStan runs at `level: max` with strict rules and **no baseline**: fix the type, never add an ignore. Advisories from `composer audit` are fixed by updating the package, never ignored.

The container mounts the parent directory (`..:/work`) so that the Composer `path` repository `../nova-aegis` resolves while the core is not on Packagist: keep the core checked out next to this repository. Installing Nova needs a license: `auth.json` (gitignored and export-ignored) holds the credentials. Never read, print or commit it.

## How the code is laid out

| Path | Holds |
|------|-------|
| `src/SmartIpBlockerServiceProvider.php` | Wiring only: the `SmartIpBlocker` singleton on the Aegis cache store, the module and its checks, the `SettingsSaved` listener, the middleware in the `web`, `nova` and `nova:auth` groups, the commands. |
| `src/SmartIpBlockerModule.php` | The `smart-ip-blocker` section: defaults (with the administrator's IP preset), rules, fields, the overview line. |
| `src/SmartIpBlockerSettings.php` | The typed, re-validated settings (`final readonly`), built from `Aegis::settings()`. |
| `src/SmartIpBlocker.php` | The rate limit: exclusions, the per-minute counter, the ban, the bounded list of tracked IPs, `removeIp()`. |
| `src/Http/Middleware/BlockExcessiveRequests.php` | Runs the count once per request and answers 429 (HTML view or JSON). |
| `src/Rules/` | `IpOrSubnet` (one row of the excluded IPs) and `CoversCurrentIp` (the lock-out protection). |
| `src/Support/` | `IpRange` (IP and CIDR matching on the binary form) and `Values` (typed reads of stored values). |
| `src/Checks/` | `CacheStoreCheck` (a cache that keeps counts between requests) and `ExcludedHeadersCheck` (spoofable exclusions). |
| `src/Console/` | `aegis:smart-ip-blocker:remove-ip` and `aegis:smart-ip-blocker:disable`, the recovery path. |
| `resources/lang/en/smart-ip-blocker.php` | Every label and message, under `aegis-smart-ip-blocker::smart-ip-blocker.*`. |
| `resources/views/blocked.blade.php` | The default page of a banned visitor, `aegis-smart-ip-blocker::blocked`. |

### How the module uses the core

The core and the module are separate packages that applications update independently. The module uses only the core's public contract (listed in the core's AGENTS.md):

- `Aegis::module()` registers `SmartIpBlockerModule`, `Aegis::check()` the two checks, `Aegis::settings('smart-ip-blocker')` is the only way the module reads its settings (cached by the core, so a request costs no database query);
- `Contracts\Module`, `Contracts\Check`, `Checks\CheckResult`, `Settings\Field`, `Events\SettingsSaved`;
- the core's `aegis.cache_store` config, so counts and settings share the store the administrator chose;
- the `nova.aegis.settings` route name, only to preset the administrator's IP in the form;
- `SettingsRepository::save()` in the disable command only, like the core's own `aegis:disable`.

The `ArchitectureTest` refuses any other core class. A newer core API is used only behind `method_exists()` / `class_exists()` with a fallback, so the module keeps working on every released core of the same major.

The settings memo in `SmartIpBlocker` is cleared on `SettingsSaved` for the `smart-ip-blocker` section, so a save applies at once.

## Upgrading installed applications safely

Read the `package-upgrades` skill before changing anything that reaches an application that already runs the module. In short:

- A setting's key, type or meaning never changes in place: add a new key, and keep reading the old shape (`SmartIpBlockerSettings::fromArray()` falls back on anything it does not understand).
- A change to what is cached bumps `SmartIpBlocker::KEY_PREFIX` (`aegis.smart-ip-blocker.v1.`).
- Defaults stay safe: the module ships disabled and a new option ships with a value that cannot ban the administrator.
- The console commands are a recovery path documented in the README: never rename them.
- Every change is a line under *Unreleased* in `CHANGELOG.md`.

## Security rules (always)

Read the `aegis-security` skill for the full checklist. For this module in particular:

- **Validate every setting twice**: in `SmartIpBlockerModule::rules()` when it is saved, and in `SmartIpBlockerSettings::fromArray()` when it is read (the stored row may predate the rules or be written by hand).
- **Lock-out protection**: `CoversCurrentIp` refuses to save the blocker enabled from the browser unless the excluded IPs cover the administrator's own IP, and the form presets that IP. Never weaken either.
- The IP is `$request->ip()`: behind a proxy, load balancer or CDN it is the proxy's unless Laravel's trusted proxies are configured. Never read `X-Forwarded-For` yourself.
- An excluded header is sent by the client and can be spoofed: it is a convenience for well-behaved bots, never a security boundary. Say so wherever it is offered (help text, check, README).
- **Every request path is cheap**: settings come from the core's cache and a per-process memo, the count is two or three cache operations, no database query, no loop that grows with traffic. The tracked-IP list is touched only when a counter starts.
- Cache keys are namespaced and versioned (`aegis.smart-ip-blocker.v1.`); the store is shared with the whole application.
- The counter lives one minute (`WINDOW_SECONDS`), never as long as the ban.
- **The application keeps working when the module breaks**: a cache failure is reported and the request passes.
- Escape every output: the blocked page prints with `{{ }}`, the API answers JSON. Never log an IP list, a header value or a request's cookies.

Recovery from the console, for an administrator who locked themselves out:

```bash
php artisan aegis:smart-ip-blocker:remove-ip 203.0.113.7   # lift a ban and reset the IP's count
php artisan aegis:smart-ip-blocker:disable                 # turn the module off
```

## Tests

Pest 4 on Orchestra Testbench 10 with the real `laravel/nova` and the Aegis core from the path repository (SQLite in memory, array cache). No test reaches the network. Read the `package-testing` skill.

## Git workflow

- `main` is protected: **never push to it and never force-push.** After the initial build every change goes through a pull request:
  1. branch off the latest `main`, named after the change (`fix/…`, `feat/…`, `chore/…`, `docs/…`);
  2. commit on the branch and `git push -u origin <branch>`;
  3. open a pull request with the template filled in (what changes, what it means for applications that upgrade);
  4. merge once `make ready` passed, then delete the branch.
- A release is a tag pushed on a merged commit of `main` (`git tag -a v1.0.0 -m "..." && git push origin v1.0.0`); Packagist reads the tag.
- Code, comments, commit messages, pull requests, issues and documentation are written in **English**.

## Conventions

- `declare(strict_types=1);` in every PHP file; PSR-12 via PHP CS Fixer, the same rules as the core.
- Code documents itself: names over comments. A comment explains a non-obvious *why*, in one sentence.
- Classes are `final`; value objects are `final readonly`.
- Commits: imperative subject saying what the change does for the application ("Count the requests per minute"), a body with the why.
