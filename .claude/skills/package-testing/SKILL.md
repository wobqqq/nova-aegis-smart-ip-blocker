---
name: package-testing
description: "How the Smart IP Blocker is tested. Use when writing or changing anything in tests/ (Pest files, tests/TestCase.php, tests/Pest.php, tests/Fixtures), phpunit.xml.dist, when a test needs Nova, the Aegis core, a request from a given IP, the console or the clock, when a test fails only in the suite, or when PHPStan complains about a test."
license: MIT
---

# Testing the package

## The harness

- Pest 4 on Orchestra Testbench 10 (Laravel 12) with the real `laravel/nova` from nova.laravel.com and the Aegis core from the `path` repository. SQLite in memory, array cache and session (`phpunit.xml.dist`).
- `tests/TestCase.php` loads `Inertia\ServiceProvider`, `NovaCoreServiceProvider`, `AegisServiceProvider` and `SmartIpBlockerServiceProvider`, runs the core's migrations, creates the `users` table for `tests/Fixtures/User.php`, registers `AegisTool`, defines `viewAegis` as `is_admin`, and adds two `web` routes (`/page`, `/api-page`).
- `tests/Pest.php` gives the helpers:
  - `admin()` and `editor()`, users the gate allows and refuses;
  - `configureBlocker([...])`, which saves the section enabled over the defaults from a request without a route, so the lock-out rule stays out of the way;
  - `requestFrom($ip, $uri, $headers)`, a GET from that `REMOTE_ADDR`.
- `Feature/` exercises the module through HTTP, the core's API, the console and its checks; `Unit/` holds `IpRange` and the `arch()` rules (strict types, final classes, readonly value objects, only the core's public contract, no network calls).

## Rules

- Test what a visitor, an administrator or the application sees: the status code and `Retry-After`, the JSON of the core's API, the result of a check, the exit code and output of a command. Not private methods.
- A security rule is a test: the lock-out rule, a spoofable header, an invalid setting, a hand-written stored row, a broken cache.
- Time moves with `travel()`; the array cache and `Date::now()` follow it. Never `sleep()`.
- A setting is saved through `configureBlocker()` or the API, never written to the table by hand, unless the test is about a stored row the rules would refuse (then flush `SettingsRepository` and `SmartIpBlocker::forget()`).
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
3. `make ready` before the commit.
