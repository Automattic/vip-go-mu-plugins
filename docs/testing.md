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

Useful variants:

```bash
CI=1 ./bin/test.sh --multisite 1
CI=1 ./bin/test.sh --wp 6.8.x --php 8.2
CI=1 ./bin/test.sh --filter test__administrator_should_not_have_update_core_cap
```

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
