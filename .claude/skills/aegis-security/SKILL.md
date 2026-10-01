---
name: aegis-security
description: "Security checklist for the Smart IP Blocker module of Aegis. Use for any change to what a request, a setting or the console can do: the BlockExcessiveRequests middleware and the groups it joins, SmartIpBlocker (counting, bans, exclusions, the tracked-IP list), a setting and its validation rules, the lock-out rule CoversCurrentIp, the blocked view, a console command, the cache keys, the checks, or a security review of this package."
license: MIT
---

# Smart IP Blocker security checklist

The module sits in front of every request of a production application. A mistake bans the administrator, bans legitimate visitors, takes the site down, or lets an attacker through. Treat every rule below as a test to write, not a guideline to remember.

## 1. Input: validate settings twice

- `SmartIpBlockerModule::rules()` bounds every value: `boolean`, `integer|min|max`, `max:` lengths, `array|max:150` row counts, `IpOrSubnet` for each excluded IP, a strict `regex` for header names and view names (no `..`, no `/`), and `View::exists()` for the view.
- The core validates against those rules, merges the defaults and drops unknown keys and columns.
- `SmartIpBlockerSettings::fromArray()` reads the stored values again through `Support\Values` and `IpRange::parse()`: a value the rules would refuse falls back to its default or is skipped, never reaches a comparison or a view call.
- Add a test with a hand-written row for every new setting (`SettingsTest`).

## 2. Lock-out protection

- `CoversCurrentIp` refuses to save the blocker enabled from a routed request unless an excluded IP or subnet contains the administrator's own IP. Console saves (no route) are exempt: they are the recovery path.
- `defaults()` presets the administrator's IP in the excluded IPs, only on the core's `nova.aegis.settings` route, so the preset never reaches the values other requests read.
- `aegis:smart-ip-blocker:disable` always succeeds: when the stored values no longer validate, it saves the defaults with the blocker off.
- `aegis:smart-ip-blocker:remove-ip` lifts a ban; it accepts only a valid IP and prints no input back.

## 3. The request path

- The IP is `$request->ip()`, normalized with `IpRange::normalize()` so every IPv6 spelling is one visitor. Never read `X-Forwarded-For` or another header for the IP: that is Laravel's trusted proxies' job.
- Excluded headers are a case-insensitive substring match on a header the client sends: a convenience, never a security boundary. The help text, `ExcludedHeadersCheck` and the README say so.
- Cheap on every request: settings from the core's cache and a per-process memo, at most a ban read, an `add` and an `increment`. No database query, no loop over traffic. The tracked list is read and written only when a counter starts.
- The counter window is `WINDOW_SECONDS` (60), never the ban duration. A counter recreated without a TTL by a store gets its window back.
- A request is counted once even when it passes several groups (the `counted` request attribute).
- A cache failure is reported and the request passes: the module never turns a request into a 500.

## 4. Output

- The blocked page prints with `{{ }}` only. A JSON client gets `{"message": ...}` with the same 429 and `Retry-After`.
- Validation messages carry the administrator's IP as text; the core's page escapes it.
- Never log or print IP lists, header values, cookies, `.env` or `auth.json`.

## 5. Caching

- Every key starts with `SmartIpBlocker::KEY_PREFIX` (`aegis.smart-ip-blocker.v1.`): the store is shared with the application.
- Values in the cache are integers and arrays of integers, never objects.
- The bounded tracked list drops the oldest counter, never a ban.

## Review procedure

1. `git diff --stat` and list every changed setting, rule, cache key, middleware group, view and command.
2. Walk each through sections 1 to 5 and name the test that pins it.
3. Run `make ready`.
4. Report each finding as: file:line, what an attacker or an administrator does, what happens, the fix.
