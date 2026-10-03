# AGENTS.md

Guidance for coding agents working in this repository.

## What this is

**Smart IP Blocker** (`wobqqq/nova-aegis-smart-ip-blocker`) is an add-on module of [Aegis](https://github.com/wobqqq/nova-aegis), the security suite for Laravel Nova (Laravel 12 or 13, PHP 8.4+). It counts the requests of every IP address over a one-minute window and bans an IP that exceeds the limit for a number of hours, on the `web` routes and in Nova, answering `429 Too Many Requests` with `Retry-After` and the page the administrator chose.

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
make test.nova      # optional: the PHP suite on the real Nova (needs a license)
```

`make ready` must pass. PHPStan runs at `level: max` with strict rules and **no baseline**: fix the type, never add an ignore. Advisories from `composer audit` are fixed by updating the package, never ignored.

The container mounts the parent directory (`..:/work`) so that the Composer `path` repository `../nova-aegis` resolves while the core is not on Packagist: keep the core checked out next to this repository. No Nova license is needed: `laravel/nova` resolves to the test double in `stubs/nova` (see *Tests*). `make test.nova` runs the PHP suite on the real Nova and is the only command that needs a license, read from `auth.json` (gitignored and export-ignored). Never read, print or commit it.

## How the code is laid out

| Path | Holds |
|------|-------|
| `src/SmartIpBlockerServiceProvider.php` | Wiring only: the `SmartIpBlocker` singleton on the Aegis cache store, the module and its checks, the `SettingsSaved` listener, the middleware in the `web`, `nova` and `nova:auth` groups, the commands. |
| `src/SmartIpBlockerModule.php` | The `smart-ip-blocker` section: defaults (with the administrator's IP preset), rules, fields, the overview line. |
| `src/SmartIpBlockerSettings.php` | The typed, re-validated settings (`final readonly`), built from `Aegis::settings()`. |
| `src/SmartIpBlocker.php` | The rate limit: exclusions, the per-minute counter, the ban, the bounded list of tracked IPs, `removeIp()`. It takes a `Visit` (address and headers) and reads the time from the injected clock. |
| `src/Visit.php` | What the blocker needs of a request, built by the middleware. |
| `src/Actions/` | `DisableSmartIpBlocker`, the work of the disable command. |
| `src/Http/Middleware/BlockExcessiveRequests.php` | Runs the count once per request and answers 429 (HTML view or JSON). |
| `src/Rules/` | `IpOrSubnet` (one row of the excluded IPs) and `CoversCurrentIp` (the lock-out protection). |
| `src/Support/` | `IpRange` (IP and CIDR matching on the binary form), `Rows` (typed reads of the rows of a stored table setting) and `SystemClock` (the clock bound when the application has none). |
| `src/Checks/` | `CacheStoreCheck` (a cache that keeps counts between requests) and `ExcludedHeadersCheck` (spoofable exclusions). |
| `src/Console/` | `aegis:smart-ip-blocker:remove-ip` and `aegis:smart-ip-blocker:disable`, the recovery path: they parse the input and call the blocker or the action. |
| `resources/lang/en/smart-ip-blocker.php` | Every label and message, under `aegis-smart-ip-blocker::smart-ip-blocker.*`. |
| `resources/views/blocked.blade.php` | The default page of a banned visitor, `aegis-smart-ip-blocker::blocked`. |
| `stubs/nova/` | The Nova test double the suite and PHPStan run on, a copy of the core's (export-ignored). |

### How the module uses the core

The core and the module are separate packages that applications update independently. The module uses only the core's public contract (listed in the core's AGENTS.md):

- `Aegis::module()` registers `SmartIpBlockerModule`, `Aegis::check()` the two checks, `Aegis::settings('smart-ip-blocker')` is the only way the module reads its settings (cached by the core, so a request costs no database query), and `Aegis::save()` the only way it writes them (the disable command);
- `Support\Values` for the typed reads of stored values, `Contracts\Module`, `Contracts\Check`, `Checks\CheckResult`, `Settings\Field`, `Events\SettingsSaved`;
- the core's `aegis.cache_store` config, so counts and settings share the store the administrator chose;
- the `nova.aegis.settings` route name, only to preset the administrator's IP in the form;

The `ArchitectureTest` refuses any other core class. A newer core API is used only behind `method_exists()` / `class_exists()` with a fallback, so the module keeps working on every released core of the same major.

The settings memo in `SmartIpBlocker` is cleared on `SettingsSaved` for the `smart-ip-blocker` section, so a save applies at once.

## Architecture

The architecture skills in `.claude/skills/` are the rules for how code is shaped; read the one that matches the change before writing it:

- `application-layer`: entry points (middleware, controllers, console commands, the module's Nova pieces) only translate input and output; the work sits in classes named after what they do, with typed input.
- `dependency-injection`: collaborators and configuration arrive through the constructor; facades stay in entry points; interfaces only at I/O boundaries (HTTP, sockets, the clock, processes).
- `error-handling`, `validation`: failures are typed exceptions, never `null` or `false`; input shape is validated at the entry point, business rules where the work is done.
- `events`: reactions run after the commit, from events that say what happened.
- `testing-architecture`: unit tests for pure logic, feature tests for use cases, fakes only at boundaries.
- `domain-layer-cqrs`: when (rarely) a separate domain layer or read side pays off.
- `package-boundaries`: what is public API here and how it may change.

In this module: the middleware maps the request to a `Visit` and asks `SmartIpBlocker` for a decision; the blocker never sees `Request` and reads time only from `Psr\Clock\ClockInterface` (an architecture test keeps it so); the recovery commands parse their input and call the blocker or `Actions\DisableSmartIpBlocker`.

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

Pest 4 on Orchestra Testbench 10 with the Aegis core from the path repository (SQLite in memory, array cache). No test reaches the network. Read the `package-testing` skill.

`laravel/nova` is the test double in `stubs/nova`: a path repository (`"versions": {"laravel/nova": "5.99.0"}`, symlinked) declared in `composer.json`, so `make install`, CI and PHPStan need no license; the `require` stays `laravel/nova: ^5.0`, and applications get the real Nova because a dependency's repositories are ignored. It is a verbatim copy of the core's `stubs/nova`: change it in the core first (with the real Nova signature), then copy it here unchanged. Check a change that touches Nova with `make test.nova` when you have a license.

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
