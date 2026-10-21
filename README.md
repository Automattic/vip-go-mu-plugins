# VIP Go mu-plugins

Development repository for WordPress VIP Go MU plugins.

## Repo map

- `000-vip-init.php`, `001-core.php`, `z-client-mu-plugins.php`: main MU bootstrap/load path.
- `001-core/`, `lib/`, `cache/`, `security/`, `vip-helpers/`, `wp-cli/`: platform feature modules.
- [`config/`](config/README.md): site details metadata sent to SDS.
- `tests/`: PHPUnit test suite for root MU code.
- `__tests__/e2e/`: Playwright e2e suite.
- `ci/`, `.github/workflows/`: automation, CI, release/deploy workflows.
- `advanced-post-cache/`, `http-concat/`, `jetpack/`, `lightweight-term-count-update/`, `rewrite-rules-inspector/`, `search/elasticpress/`, `wp-parsely/`, `gutenberg-ramp/`, `drop-ins/hyperdb/`: external submodules/upstream code.

## Quickstart

```bash
git submodule update --init --recursive
composer install
npm install
```

Run a fast smoke check:

```bash
npm run phplint
npm run test:smoke
```

Run the main local quality/test commands:

```bash
npm run lint
npm run test
```

Search's shared-counter integration test starts two PHP workers against an
isolated Memcached service: `CI=1 ./bin/test.sh --memcached --filter Test_Concurrency_Shared_Cache`.
See [testing](docs/testing.md) for runtime requirements and test scope.

## Local development

VIP local dev environment (recommended):

```bash
vip dev-env create --mu-plugins "$(pwd)"
vip dev-env start
```

## e2e workflows

Run from repo root:

```bash
npm --prefix __tests__/e2e ci
npm run setup-e2e-env
npm run test-e2e
npm run destroy-e2e-env
```

## Canonical docs

- [Architecture](docs/architecture.md)
- [Setup](docs/setup.md)
- [Testing](docs/testing.md)
- [Release](docs/release.md)
- [Agent guide](AGENTS.md)

Local SQL import cleanup removes imported service connection credentials even
when Jetpack is unavailable. See [local import testing](docs/testing.md#local-sql-import-credentials).

For the isolated configuration sync runtime regression, see
[configuration sync testing](docs/testing.md#configuration-sync-runtime).

Jetpack SSO status is stored in WordPress session metadata. Existing sessions need a fresh SSO login to receive the flags; see [SSO session metadata](docs/architecture.md#jetpack-sso-session-metadata).
