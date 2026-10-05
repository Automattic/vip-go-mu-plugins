# Testing

## Test scopes

From repository root:

```bash
npm run phplint         # syntax lint
npm run phpcs           # coding standards
npm run format:check    # alias to phpcs
npm run lint            # phplint + phpcs
npm run test:smoke      # one fast PHPUnit test in Docker
npm run test            # full PHPUnit suite in Docker
```

## PHPUnit details

`bin/test.sh`:

- creates an isolated Docker network and MySQL container
- runs tests in `ghcr.io/automattic/vip-container-images/wp-test-runner`
- uses `tests/bootstrap.php` to load MU plugin stack

The repository is mounted into the container, so run `composer install` and
`git submodule update --init --recursive` first (including in fresh worktrees).
Without `vendor/yoast/phpunit-polyfills`, the runner fails before any test runs with
`Failed opening required '.../vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php'`.

Useful variants:

```bash
CI=1 ./bin/test.sh --multisite 1
CI=1 ./bin/test.sh --wp 6.8.x --php 8.2
CI=1 ./bin/test.sh --filter test__administrator_should_not_have_update_core_cap
```

The Search shared-counter integration test uses two independent PHP workers and
the production `drop-ins/wp-memcached` cache implementation. Start an isolated
Memcached service with:

```bash
CI=1 ./bin/test.sh --memcached --filter 'Test_Concurrency_(Limiter|Shared_Cache)'
```

`--memcached` starts and removes a `memcached:1.6-alpine` container on the test
network. The runner must provide the PHP `memcached` extension and `proc_open`.
Without these or `VIP_TEST_MEMCACHED_SERVER`, the shared-cache test explicitly
skips; the deterministic hook tests still run. Barriers hold both workers at the
counter operation and retain their increments until both admission results are
reported. The test requires exactly one admitted request at limit one, then
reads the shared counter from a fresh process and requires zero.

`CI=1` is recommended in non-interactive shells.

## e2e tests

From repository root:

```bash
npm --prefix __tests__/e2e ci
npm run lint:e2e
npm run typecheck:e2e

# Runs __tests__/e2e tests; this will set up and tear down the e2e env
# via the package's pretest/posttest hooks.
npm run test-e2e

# Optional: to manage the env lifecycle manually instead of using hooks:
# npm run setup-e2e-env
# npm run test-e2e
# npm run destroy-e2e-env
```

Equivalent package-local commands are in `__tests__/e2e/package.json`.

## CI coverage

Current workflows cover:

- Lint: `.github/workflows/lint.yml`
- Typecheck + e2e lint/test: `.github/workflows/e2e.yml`
- Unit/integration tests: `.github/workflows/ci.yml`, `.github/workflows/core-tests.yml`, `.github/workflows/parsely.yml`

## Troubleshooting

- `permission denied while trying to connect to docker API`: Docker daemon/socket access is not available in your shell.
- `the input device is not a TTY`: rerun with `CI=1`.
- `No tests executed!`: `--filter` value did not match any test names.
- `Missing __tests__/e2e dependencies.`: run `npm --prefix __tests__/e2e ci`.
- `npm run phpcs` reports errors in local-only directories: ensure you are linting from the intended repo state and review untracked plugin directories.

## Next docs

- [Setup](setup.md)
- [Release](release.md)
- [Architecture](architecture.md)
- [Agent guide](../AGENTS.md)

### Local SQL import credentials

`wp vip data-cleanup sql-import` removes imported Jetpack, VaultPress and
Akismet connection options on local environments before customer cleanup hooks.
It also removes Jetpack handshake secrets and the imported Connection Pilot
heartbeat. This runs independently of Jetpack availability and covers inactive
subsites whose options tables still exist. Other environments retain their
connection options. Removing `jetpack_options` also resets settings stored in
that container, matching the existing migration cleanup command.

Run the local import regression tests with:

```sh
CI=1 ./bin/test.sh --filter Local_Import_Cleanup_Test
```

Cleanup skips Connection Pilot on local environments. Hosted environments still
run it when available, respect `VIP_JETPACK_SKIP_LOAD`, and warn when the class is
unexpectedly unavailable. Test the missing-class fallback with
`CI=1 ./bin/test.sh --filter Cleanup_Connection_Pilot_Test`.

Jetpack compatibility checks use the real WordPress header parser in the existing
Jetpack tests: `CI=1 ./bin/test.sh --filter test_jetpack_compatibility_requirements`.

The e2e setup also runs the real import command with Jetpack disabled and verifies
that credentials are removed before customer hooks while unrelated options remain.
