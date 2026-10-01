# Aegis Smart IP Blocker

[![CI](https://github.com/wobqqq/nova-aegis-smart-ip-blocker/actions/workflows/ci.yml/badge.svg)](https://github.com/wobqqq/nova-aegis-smart-ip-blocker/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/wobqqq/nova-aegis-smart-ip-blocker)](https://packagist.org/packages/wobqqq/nova-aegis-smart-ip-blocker)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://github.com/wobqqq/nova-aegis-smart-ip-blocker/blob/main/LICENSE.md)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4)](https://github.com/wobqqq/nova-aegis-smart-ip-blocker/blob/main/composer.json)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen)](https://github.com/wobqqq/nova-aegis-smart-ip-blocker/blob/main/phpstan.neon.dist)

**Smart IP Blocker** is a module of [Aegis](https://github.com/wobqqq/nova-aegis), the security suite for Laravel Nova. It counts the requests of every IP address per minute and bans an IP that sends more than you allow, on the site and in Nova, to slow down brute-force attempts, scrapers and request floods.

## 🚀 Features

- **Per-minute limit**: every IP gets a one-minute window of requests; past the limit it is banned.
- **Bans for hours**: a banned IP gets `429 Too Many Requests` with a `Retry-After` header for 1 to 720 hours, then it starts over.
- **Your own blocked page**: any Blade view of your application, or the module's own page. Clients that ask for JSON get JSON.
- **Exclusions**: IP addresses and CIDR subnets, IPv4 and IPv6 (`2001:db8::1` and `2001:0db8:0:0:0:0:0:1` are the same visitor), and headers whose value contains a text (`User-Agent` contains `Googlebot`).
- **Lock-out protection**: the form presets your own IP in the exclusions, and refuses to switch the blocker on unless your IP stays excluded.
- **Bounded memory**: optionally count at most N IPs at once; past it the oldest count is dropped, never a ban.
- **Cheap on every request**: the settings come from the Aegis cache, a request costs two or three cache operations and no database query.
- **Dashboard**: a line on the Aegis overview, and checks that warn about a cache store that forgets the counts and about spoofable header exclusions.
- **Recovery from the console**: lift a ban or turn the module off.

## 📦 Requirements

- PHP 8.2 or higher
- Laravel 12
- Laravel Nova 5
- [Aegis](https://github.com/wobqqq/nova-aegis) 1.1 or higher (installed with the module)
- A cache store shared by every server (Redis, Memcached, database; `file` on a single server)

## 📥 Installation

### 1. Install the package

```bash
composer require wobqqq/nova-aegis-smart-ip-blocker
```

The service provider is discovered automatically.

### 2. Run the migrations

```bash
php artisan migrate
```

This creates the Aegis settings table if the core is new to the application; the module adds no table of its own.

### 3. Set up Aegis (once per application)

If Aegis is new to the application, register its tool and define the `viewAegis` gate as the [Aegis README](https://github.com/wobqqq/nova-aegis#-installation) describes. Skip this step if you already use another Aegis module.

### 4. Turn it on in Nova

Open **Aegis → Settings → Smart IP Blocker** in Nova, check that your own address is in **Excluded IPs**, set the limits, switch **Enable the Smart IP Blocker** on and save.

## ⚙️ Configuration

Everything is set in the Aegis settings section:

| Setting | Default | Range |
|---|---|---|
| Enable the Smart IP Blocker | off | |
| Requests per minute | `100` | 1 – 10000 |
| Ban duration (hours) | `1` | 1 – 720 |
| Blocked page view | `aegis-smart-ip-blocker::blocked` | an existing Blade view |
| Tracked IPs limit | `0` (no limit) | 0 – 10000 |
| Excluded IPs | your IP | up to 150 IPs or subnets |
| Excluded headers | none | up to 150 header / text pairs |

The counts and bans live in the cache store Aegis uses (`AEGIS_CACHE_STORE`, the default store otherwise), under keys starting with `aegis.smart-ip-blocker.v1.`. The `array` and `null` stores forget them after each request: the Aegis dashboard fails a check when the module runs on one.

A custom view receives `$retryAfter` (seconds) and `$message`.

## 🧰 Recovery commands

```bash
php artisan aegis:smart-ip-blocker:remove-ip 203.0.113.7   # lift the ban and reset the request count of an IP
php artisan aegis:smart-ip-blocker:disable                 # turn the module off, keeping its settings
```

## ⚠️ Good to know

- **Proxies and load balancers**: the module counts `$request->ip()`. Behind a load balancer, a reverse proxy or a CDN that is the proxy's address unless Laravel's [trusted proxies](https://laravel.com/docs/12.x/requests#configuring-trusted-proxies) are configured, and every visitor would share one count. Configure them first; if the form shows your proxy's address as your IP, do not exclude it, or nobody is ever counted.
- **Excluded headers can be spoofed**: the client writes its own headers, so anyone who knows the rule can send `User-Agent: Googlebot` and bypass the limit. Use them for convenience, and excluded IPs or subnets for anything you rely on.
- **Shared IPs**: offices, mobile carriers and VPNs put many people behind one IP. Leave headroom in the limit, or exclude the subnets you know.
- **Nova makes several requests per page**: excluding the administrators' IPs keeps a busy session in Nova from being banned.
- **The limit is per server only on the `file` store**: use a shared store when the application runs on several servers.
- A request is counted once even when it passes several middleware groups.

## ⬆️ Upgrading

See [CHANGELOG.md](https://github.com/wobqqq/nova-aegis-smart-ip-blocker/blob/main/CHANGELOG.md).

## 🔒 Security

Please report a vulnerability privately, as described in [SECURITY.md](https://github.com/wobqqq/nova-aegis-smart-ip-blocker/blob/main/SECURITY.md).

## 🛠️ Development

The toolchain runs in Docker, the host needs nothing but `docker` and `make`. Until the core is on Packagist, Composer installs it from `../nova-aegis` (a `path` repository), so clone [nova-aegis](https://github.com/wobqqq/nova-aegis) next to this repository; the container mounts the parent directory for that. Nova is a licensed package, so installing the development dependencies needs your own Nova license: put its credentials in `auth.json` (gitignored) or run `composer config http-basic.nova.laravel.com <email> <license-key>`.

```bash
make install        # composer install
make code.fix       # composer normalize, Rector, PHP CS Fixer
make code.check     # composer validate/audit, php -l, PHP CS Fixer, Rector, PHPStan (level max)
make test.coverage  # Pest with coverage (90 % minimum)
make ready          # everything above
```

GitHub Actions runs the same checks on every pull request. It checks the core out next to this repository and needs the `NOVA_USERNAME` and `NOVA_LICENSE_KEY` repository secrets; while the core repository is private, an `AEGIS_CORE_TOKEN` secret (a token that can read it) as well.
