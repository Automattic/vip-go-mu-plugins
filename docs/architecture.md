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

## Connector Controls runtime reporting

Connector Controls defines the provider API-key constant from the managed
credential during integration configuration. Existing nonempty environment
variables and nonempty string PHP constants retain precedence; customer constants
are never redefined. Managed database credential options remain inert.

The AI Client normally discovers the constant when registering the provider.
Direct registry injection at `wp_connectors_init` is reserved for two fallback
cases: an empty environment variable masks the chosen constant, or an existing
empty/non-string constant cannot be replaced. No environment variables are
changed. With no managed assignment, no managed credential is supplied; an
existing valid customer constant can still receive the empty-environment fix.

Constants created by Connector Controls are reported as `integration`; pre-existing
customer constants are reported as `php_constant`. This distinction describes
credential ownership, while the actual AI Client registry value determines what
is reported. The normal constant path preserves explicit authentication installed
by other code; an unrecognized registry value is reported as `unknown`.

On the WordPress Connectors screen, the OpenAI, Anthropic, and Google descriptions
also explain credential management in VIP Integration Center. The integration
uses the supported `wp_connectors_init` unregister/modify/register pattern,
preserving each connector's existing description and other metadata. Descriptions
reflect the AI Client source at connector initialization, after credential fallbacks run: managed,
environment variable, customer constant, no credential, unavailable provider, or
unknown source. Missing connectors and custom connectors are left alone.
Core's field help and read-only markers remain unchanged, including its constant
label; the card description supplies the management context. Like SDS reporting,
this does not verify provider acceptance of a key. Code that replaces authentication
after connector initialization can change the runtime source after this metadata
snapshot; SDS observes the registry again when collecting its report.

The existing Site Details Service (SDS) report includes `connector_controls` with
an `active` flag and per-provider `source` and `status` enums. The reporter inspects
the actual registry authentication: integration, environment variable, PHP
constant, no credential, or unknown source. Missing providers are reported
separately. If another plugin replaces the authentication with an unrecognized
value, the source becomes unknown. A switched blog cannot report another blog's
registry; its report is null until a request initializes that blog normally.

No key, key fragment, fingerprint, or authentication object enters this payload.
Collection reads request-local state without an additional HTTP request or
option write. It uses SDS's existing timestamp, change detection, scheduled
uploads, and heartbeats. The Integration Center displays the last observed
source, including stale or unavailable observations; this is not confirmation
that a provider accepts the key or that a newly saved assignment has propagated.
