---
name: package-upgrades
description: "How a change reaches the Laravel applications that already run the Smart IP Blocker. Use before changing a setting (its key, type, meaning or default), a cache key or what is cached, a console command, the middleware groups, the use of the Aegis core contract, composer.json constraints, or when preparing a release or a tag."
license: MIT
---

# Upgrading installed applications

Applications update the core and this module independently with Composer. Every change is written for an application that has been running the previous version for months, possibly with an older or newer core.

## Versions and releases

- Semantic versions: a fix is a patch, a new option or check a minor, a removed option, a renamed command or a stricter requirement a major.
- Every change adds a line under *Unreleased* in `CHANGELOG.md` saying what changes for the developer. A release moves them under the version and date.
- Release: merge the pull request, then `git tag -a v1.0.1 -m "..." && git push origin v1.0.1`. Packagist reads the tag.

## Constraints

- `laravel/nova` stays `^5.0`, `laravel/framework` `^12.0` and `wobqqq/nova-aegis` `^1.0`: whole majors. Supporting a new major is a minor release with both ranges and tests against both.
- `dev-main` and the `path` repository serve development until the core is on Packagist; once it is, drop the path repository and keep `^1.0`.
- The lock file is for development only (export-ignored); the ranges are what applications resolve.

## Stored settings

The section is one row of the core's `aegis_settings` table under the key `smart-ip-blocker`. The module owns no table and no migration.

- The core merges the stored values over `defaults()` and drops keys the defaults no longer name, so adding a setting needs no migration.
- Changing a setting's type or meaning: add a **new key**, never reinterpret an old one. `SmartIpBlockerSettings::fromArray()` keeps reading the old shape and falls back on anything it does not understand.
- Never rename the section key `smart-ip-blocker`.

## Cached values

- Counters, bans and the tracked list live under `aegis.smart-ip-blocker.v1.`. A change to the shape of a cached value bumps the version in `SmartIpBlocker::KEY_PREFIX`; old entries then expire on their own (a minute for counters, the ban duration for bans).
- Bumping the prefix lifts every current ban: say so in the changelog.

## The core's contract

- Use only what the core's AGENTS.md lists as public, plus `aegis.cache_store` and `SettingsRepository::save()` in the disable command. `ArchitectureTest` enforces it.
- A newer core API is used behind `method_exists()` / `class_exists()` with a fallback.
- Run this suite against the core's latest `main` before releasing.

## Defaults

- The module ships disabled. A new option ships with a value that cannot ban the administrator or a legitimate visitor.
- Changing a default changes the behaviour of every application that never saved the section: say so in the changelog, or keep the old default.

## Recovery commands

`aegis:smart-ip-blocker:remove-ip` and `aegis:smart-ip-blocker:disable` are documented as the way back from a lock-out. Never rename or remove them; add an alias first if a rename is ever needed.
