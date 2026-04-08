# Agent Guide

This file defines safe working rules for AI coding agents in this repository.

## Architecture at a glance

- MU load path starts at `000-vip-init.php` and `001-core.php`.
- Root feature entry files (for example `security.php`, `vip-mail.php`, `vip-rest-api.php`) wire feature modules.
- Shared code lives in `lib/`.
- Automated tests:
  - PHPUnit: `tests/` via `bin/test.sh` (use `CI=1 ./bin/test.sh --filter <test>` for running specific tests).
  - e2e: `__tests__/e2e/`

See [docs/architecture.md](docs/architecture.md) for full map.

## Where to put code

- New platform logic: add to existing module directories (`001-core/`, `lib/`, `cache/`, `security/`, `vip-helpers/`, `wp-cli/`) and keep a thin root entrypoint.
- New unit tests: add to `tests/` with `test-*.php` naming.
- New e2e tests: add to `__tests__/e2e/specs/`.

Avoid direct edits in external/upstream directories unless the task is explicitly to update upstream code:

- `advanced-post-cache/`
- `http-concat/`
- `jetpack/`
- `lightweight-term-count-update/`
- `rewrite-rules-inspector/`
- `search/elasticpress/`
- `wp-parsely/`
- `gutenberg-ramp/`
- `drop-ins/hyperdb/`

## Security rules

- Never commit credentials, API tokens, or secret constants.
- Keep secret-backed values in environment/site config only (`vip-config.php` or platform secrets), not in this repo.
- Preserve and test capability checks when changing auth, REST, cache purge, or admin actions.
- Treat these paths as high-risk and require extra care + tests:
  - `security.php`
  - `cache/class-vary-cache.php`
  - `vip-cache-manager/vip-cache-manager.php`
  - `001-core/constants.php`

## Coding conventions

- Run local checks before proposing merge:
  - `npm run phplint`
  - `npm run phpcs`
  - `npm run test:smoke`
- Keep changes minimal and module-local.
- Prefer extending existing helper classes/functions over introducing parallel abstractions.
- Update docs when changing setup/testing/release behavior.

## Performance pitfalls

- Avoid unbounded or repeated `WP_Query` calls inside loops (N+1 query patterns).
- Avoid expensive operations on high-frequency hooks (`init`, `template_redirect`, `parse_request`) without caching/guards.
- Be careful with cache purge fan-out and auth-cookie variation changes; these can affect global cache hit rate.

## Safe-change checklist

- Confirm target area is not an upstream/submodule path.
- Add or update tests near changed behavior.
- Run lint + at least smoke tests locally.
- Validate no secret values were introduced in diffs.
- Update impacted docs in `docs/` and root `README.md`.

## Related docs

- [README](README.md)
- [docs/setup.md](docs/setup.md)
- [docs/testing.md](docs/testing.md)
- [docs/release.md](docs/release.md)
- [docs/architecture.md](docs/architecture.md)
