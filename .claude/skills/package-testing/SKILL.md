---
name: package-testing
description: "How the Smart IP Blocker is tested. Use when writing or changing anything in tests/ (Pest files, tests/TestCase.php, tests/Pest.php, tests/Fixtures), phpunit.xml.dist or stubs/nova (the Nova test double), when code or a test uses a Nova class or method not used before, when a test needs Nova, the Aegis core, a request from a given IP, the console or the clock, when a test fails only in the suite, or when PHPStan complains about a test."
license: MIT
---

# Testing the package

## The harness

- Pest 4 on Orchestra Testbench 10 (Laravel 12) with `laravel/nova` resolved to the test double in `stubs/nova` (see below) and the Aegis core from the `path` repository. SQLite in memory, array cache and session (`phpunit.xml.dist`).
- `tests/TestCase.php` loads `Inertia\ServiceProvider`, `NovaCoreServiceProvider`, `AegisServiceProvider` and `SmartIpBlockerServiceProvider`, runs the core's migrations, creates the `users` table for `tests/Fixtures/User.php`, registers `AegisTool`, defines `viewAegis` as `is_admin`, and adds two `web` routes (`/page`, `/api-page`).
- `tests/Pest.php` gives the helpers:
  - `admin()` and `editor()`, users the gate allows and refuses;
  - `configureBlocker([...])`, which saves the section enabled over the defaults from a request without a route, so the lock-out rule stays out of the way;
  - `requestFrom($ip, $uri, $headers)`, a GET from that `REMOTE_ADDR`.
- `Feature/` exercises the module through HTTP, the core's API, the console and its checks; `Unit/` holds `IpRange` and the `arch()` rules (strict types, final classes, readonly value objects, only the core's public contract, no network calls).

## The Nova test double (`stubs/nova`)

- `composer.json` declares `stubs/nova` as the `nova` path repository (`"versions": {"laravel/nova": "5.99.0"}`, `"symlink": true`), so `vendor/laravel/nova` links to it. No license, no `auth.json`, the same in CI. It is export-ignored; applications install the real Nova.
- It is a verbatim copy of the core's `stubs/nova` (see the core's `package-testing` skill for what it provides and leaves out). Never edit it here alone, and never copy Nova's code or comments into it.
- **The module uses a Nova API the double lacks:** add it in the core's `stubs/nova` with the real signature (read it in a Nova install), then copy the directory here unchanged and run `make ready` and, with a license, `make test.nova`.
- A test is about the module, not Nova: when a test only passes on one of them, rewrite it against the behaviour of the double instead of deleting the coverage.
- `make test.nova` runs the suite on the real Nova in a throwaway copy of the module and the core (`docker/test-nova.sh`): it needs `auth.json` with your license; `NOVA_VERSION=5.9.3 make test.nova` pins a release, `AEGIS_CORE` points at another core checkout.

## Rules

- Test what a visitor, an administrator or the application sees: the status code and `Retry-After`, the JSON of the core's API, the result of a check, the exit code and output of a command. Not private methods.
- A security rule is a test: the lock-out rule, a spoofable header, an invalid setting, a hand-written stored row, a broken cache.
- Time moves with `travel()`; the array cache and `Date::now()` follow it. Never `sleep()`.
- A setting is saved through `configureBlocker()` or the API, never written to the table by hand, unless the test is about a stored row the rules would refuse: then `storeRawBlockerSettings()` writes it and makes Aegis read it again.
- No test reaches the network.
- Coverage stays at 90 % or more (`make test.coverage`).

## PHPStan max on tests, without ignores

- Use the global `Pest\Laravel\*` functions (`getJson`, `putJson`, `actingAs`, `withServerVariables`, `travel`), never `$this->` in a closure.
- Console: `expect(Artisan::call('aegis:smart-ip-blocker:disable'))->toBe(0)` and `Artisan::output()`.
- Annotate mocks (`/** @var CacheRepository&MockInterface $cache */`) and type closure parameters.
- Read `mixed` JSON with `data_get()` or narrow it with `is_array()` before indexing; avoid higher-order expectations on union types.

## Workflow

1. Write the change and its tests; iterate with `docker compose run --rm php vendor/bin/pest --filter='...'`.
2. `make composer.test.coverage` for gaps; cover the uncovered decisions, not getters.
3. `make ready` before the commit; `make test.nova` too when the change touches Nova and you have a license.
