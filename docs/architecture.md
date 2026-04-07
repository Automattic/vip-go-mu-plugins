# Architecture

## Purpose

This repository contains the MU plugin layer used by VIP Go WordPress environments.

## Load path

- `000-vip-init.php`: early bootstrap.
- `001-core.php` + `001-core/`: core platform behavior.
- Root plugin entry files (`security.php`, `vip-mail.php`, `vip-rest-api.php`, etc.) load feature-specific modules.
- `z-client-mu-plugins.php`: client plugin loader.

## Code layout

- `lib/`: shared libraries/helpers used across modules.
- `cache/`, `vip-cache-manager/`: cache variation + purge orchestration.
- `security/` and `security.php`: auth hardening, login/lost-password protections.
- `files/`: files API integration and helpers.
- `config/`: config sync and indexing helpers.
- `wp-cli/`: WP-CLI commands.
- `tests/`: root PHPUnit tests (`tests/bootstrap.php` wires the MU stack).
- `__tests__/e2e/`: Playwright tests against a VIP dev environment.

## External/upstream code boundaries

These paths are external code (submodules/upstreams) and should only be changed intentionally:

- `advanced-post-cache/`
- `http-concat/`
- `jetpack/`
- `lightweight-term-count-update/`
- `rewrite-rules-inspector/`
- `search/elasticpress/`
- `wp-parsely/`

If you need to update one of them, use its upstream workflow instead of ad-hoc edits.

## Runtime dependencies

- WordPress + MySQL.
- Memcached/object-cache path (see `.devcontainer/docker-compose.yml` and `drop-ins/wp-memcached/`).
- Optional Elasticsearch-dependent paths (search and e2e env setup).
- Optional external APIs behind config constants (Akismet, Twilio, purge APIs, SMTP).

## Next docs

- [Setup](setup.md)
- [Testing](testing.md)
- [Release](release.md)
- [Agent guide](../AGENTS.md)
