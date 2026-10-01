---
name: nova-development
description: >-
  Use whenever you change how the Smart IP Blocker meets Nova or the Aegis
  page: the middleware groups it joins (web, nova, nova:auth), the settings
  section the core draws from SmartIpBlockerModule::fields(), the help texts
  and labels in resources/lang, the overview line from status(), the checks
  on the Aegis dashboard, or the service provider's wiring. Use it with
  aegis-security for anything about access or the lock-out rule.
metadata:
  author: project
---

# Nova development (this module)

The module has no Nova tool, card or JavaScript of its own: the Aegis core draws everything from the module's PHP declarations. Check the core's source in `vendor/wobqqq/nova-aegis` before relying on a behaviour. `vendor/laravel/nova` here is the test double in `stubs/nova`, not Nova: check a version-specific API in a real Nova install, and add any Nova class or method you start using to the core's `stubs/nova` first (see `package-testing`).

## The pieces

- **Settings section** — `SmartIpBlockerModule::fields()` returns `Wobqqq\Aegis\Settings\Field`s (`toggle`, `number`, `text`, `table`). The core's page renders them and sends every value back on save, so `rules()` can use `present` for the tables.
- **Overview line** — `status()` returns a `CheckResult` (pass when on, warn when off).
- **Checks** — `CacheStoreCheck` and `ExcludedHeadersCheck`, registered with `Aegis::check()`, answer `info` while the module is off.
- **Middleware** — `BlockExcessiveRequests` is prepended to `web`, `nova` and `nova:auth` in `$this->app->booted()`, because Nova defines its groups while it boots. It runs before the session starts, so a banned request costs no session or authentication work.

## Rules

- No business logic in the provider or the module: it belongs in `SmartIpBlocker` or `SmartIpBlockerSettings`, which tests call directly.
- Labels and help texts live in `resources/lang/en/smart-ip-blocker.php`. The excluded-IP help shows the administrator's IP; the excluded-header help says the header can be spoofed.
- The preset of the administrator's IP depends on the core's `nova.aegis.settings` route name: if the core ever renames it, the preset disappears but the lock-out rule still refuses an unsafe save.
- A new field type needs a core release first: use only the `Field` factories the core already has.

## Checklist

- [ ] Every new field has a rule, a default and a `fromArray()` fallback.
- [ ] New strings in `resources/lang/en/smart-ip-blocker.php`.
- [ ] The Nova routes still answer 429 to a banned IP and count a request once (`BlockerTest`).
- [ ] `make ready` passes.
