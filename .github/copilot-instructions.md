# VIP Go MU-Plugins - Coding Agent Instructions

## Repository Overview

This is the development repository for mu-plugins (must-use plugins) that power the VIP Go platform. It's a large WordPress plugin collection (~60+ top-level directories) written primarily in PHP, supporting PHP 7.4, 8.0, 8.1, 8.2, 8.3, and 8.4.

**Key Characteristics:**
- Type: WordPress mu-plugins collection
- Primary Language: PHP (with JavaScript for WordPress admin features and E2E tests)
- Package Manager: Composer for PHP dependencies, npm for Node.js tooling
- Git Submodules: 8 submodules (jetpack, elasticpress, wp-parsely, akismet, etc.)
- Testing: PHPUnit (Docker-based), E2E tests (Playwright), Core WordPress tests
- CI/CD: GitHub Actions workflows (no CircleCI despite references in README)

## Critical Setup Steps

### ALWAYS Run These Commands First

Dependencies MUST be installed in this exact order before any other operations:

```bash
git submodule update --init --recursive
composer install --no-interaction
npm install
```

**Important:** Composer install can take 5-10 minutes. Use `--no-interaction` flag to prevent hangs. Never skip submodule initialization - many directories are submodules and tests will fail without them.

## Build, Test, and Validation Commands

### PHP Linting

```bash
npm run phplint
```
- Runs in ~5 seconds
- Uses npx phplint on all PHP files except vendor/, node_modules/, jetpack*/, wp-parsely*/
- Always run before committing PHP changes

### PHP Code Standards (PHPCS)

```bash
npm run phpcs
```
- Uses WordPress VIP coding standards (see `phpcs.xml.dist`)
- Scans incrementally via lint-staged on pre-commit (see `.husky/pre-commit` and `.lintstagedrc`)
- Full scan takes 30-60 seconds
- Configuration: PHP 8.1+ compatibility, WordPress 6.2+ support
- Excludes: vendor/, submodules (advanced-post-cache, cron-control, http-concat, jetpack, etc.), __tests__/, bin/

To fix auto-fixable issues:
```bash
npm run phpcs:fix
```

### PHPUnit Tests

**Primary Method (Docker-based):**
```bash
./bin/test.sh
```
- Self-contained Docker environment (downloads MySQL 8 and test runner images on first run)
- Takes 2-5 minutes for full test suite
- Uses `phpunit.xml` configuration
- Supports filtering: `./bin/test.sh --filter test_name_here`

**Options:**
- `--wp VERSION`: WordPress version (default: latest)
- `--multisite yes/no`: Enable multisite mode
- `--php VERSION`: PHP version
- `--filter PATTERN`: Run specific tests

The script creates a Docker network and MySQL container automatically. It will clean up on exit.

**Common Test Failures:**
- If Docker images aren't present, first run will download them (can take 5-10 minutes)
- Ensure Docker daemon is running
- Database connection failures: script waits up to 60 seconds for MySQL to start

### End-to-End Tests

E2E tests are in `__tests__/e2e/` using Playwright and TypeScript:

```bash
cd __tests__/e2e
npm ci
npx playwright install chromium
npm test  # Sets up environment, runs tests, tears down
```

**Note:** E2E tests require VIP CLI (`npm install -g @automattic/vip`). Tests automatically manage test environment lifecycle.

### Run All Quality Checks

```bash
npm run lint  # Runs both phplint and phpcs
```

## GitHub Actions CI Pipelines

All PRs to `develop` branch trigger these workflows:

1. **CI Workflow** (`.github/workflows/ci.yml`):
   - Runs PHPUnit tests across matrix of WP versions (6.4.x-nightly), PHP versions (8.1-8.4), multisite yes/no, Jetpack yes/no
   - Uses MySQL 8 service container
   - Takes 30-45 minutes for full matrix
   - Generates code coverage for latest WP + PHP 8.1

2. **Lint Workflow** (`.github/workflows/lint.yml`):
   - Runs `npm run lint` (phplint + phpcs)
   - Also lints Search Dev Tools separately
   - Takes 1-2 minutes

3. **E2E Workflow** (`.github/workflows/e2e.yml`):
   - Runs Playwright E2E tests
   - Includes ESLint check for E2E test code
   - Takes 5-10 minutes

4. **Core Tests Workflow** (`.github/workflows/core-tests.yml`):
   - Runs WordPress core PHPUnit tests with mu-plugins loaded
   - Complex setup, takes 15-20 minutes
   - Many core tests are ignored (see workflow for filters)

## Repository Structure

### Root-Level Files
- `000-vip-init.php`: VIP platform initialization (loaded first)
- `001-core.php`, `001-cron.php`: Core mu-plugin loaders
- `z-client-mu-plugins.php`: Loads client-specific mu-plugins (loaded last)
- `composer.json.tpl`: Template for generated composer.json
- `phpcs.xml.dist`: PHPCS configuration
- `phpunit.xml`, `phpunit-multisite.xml`: PHPUnit configurations

### Key Directories

**Core Functionality:**
- `search/`: Enterprise Search integration (ElasticPress, ES-WP-Query, search-dev-tools)
- `cache/`: Caching layer implementations
- `security/`: Security features
- `performance/`: Performance optimizations
- `lib/`: Shared library code
- `vip-helpers/`: VIP platform helper functions

**WordPress Integration:**
- `jetpack/`: Jetpack (submodule)
- `wp-parsely/`: Parse.ly plugin (submodule)
- `akismet/`: Akismet (submodule, not in PHPCS scope)
- `query-monitor/`: Query Monitor (submodule, not in PHPCS scope)

**Infrastructure:**
- `wp-cli/`: WP-CLI commands and integrations
- `prometheus/`: Metrics collection
- `telemetry/`: Telemetry features
- `drop-ins/`: WordPress drop-ins (e.g., hyperdb for database)

**Testing:**
- `tests/`: PHPUnit tests
- `__tests__/e2e/`: End-to-end tests

**Tooling:**
- `bin/`: Utility scripts (test.sh, php-lint.sh, etc.)
- `ci/`: CI-related scripts (changelog-summary.php)

### Files Excluded from PHPCS
Submodules and third-party code are excluded (see `phpcs.xml.dist` lines 14-41). When editing code, ensure you're not in an excluded directory if you expect PHPCS to validate it.

## Common Pitfalls and Solutions

### Problem: Composer Install Hangs
**Solution:** Use `composer install --no-interaction` flag. The default interactive mode can hang waiting for input.

### Problem: Tests Fail with "Class not found"
**Solution:** Ensure submodules are initialized: `git submodule update --init --recursive`

### Problem: PHPCS Runs Forever
**Solution:** PHPCS caches results. If it seems stuck, it may be scanning for the first time. Wait up to 60 seconds. Cache is stored in `.phpcs-cache` (gitignored).

### Problem: Docker Test Failures
**Solution:** 
- Ensure Docker daemon is running
- First run downloads images (5-10 min wait is normal)
- Check port 3306 isn't already in use
- Script automatically cleans up; if manually stopped, run `docker network prune`

### Problem: Pre-commit Hook Fails
**Solution:** Husky runs lint-staged which runs phplint + phpcs on staged PHP files. Fix reported issues or bypass with `git commit --no-verify` (not recommended).

### Problem: Changes to Submodule Code
**Solution:** Don't edit submodule code directly. These are external dependencies. If you need changes, they should be made in the source repository. Exception: test fixtures or local development overrides.

## Important Constraints

1. **PHP Version Support:** Code must work on PHP 8.1+. Avoid syntax/features for older PHP versions.

2. **WordPress Compatibility:** Minimum WordPress 6.2 (`phpcs.xml.dist` line 65).

3. **No Breaking Changes to Public APIs:** This code runs on thousands of WordPress sites. Breaking changes to public functions/filters/actions require deprecation periods.

4. **Test Coverage Required:** All new features should include PHPUnit tests in `tests/` directory matching the structure of the code being tested.

5. **PHPCS Compliance:** All PHP code must pass PHPCS. Use `npm run phpcs:fix` for auto-fixable issues.

6. **Submodules are Read-Only:** Never commit changes to submodule directories (jetpack, elasticpress, etc.). These are managed separately.

## Quick Reference for Common Tasks

**Adding a New Feature:**
1. Create feature code in appropriate directory (e.g., `security/my-feature.php`)
2. Add PHPUnit tests in `tests/` matching directory structure
3. Run: `npm run lint && ./bin/test.sh`
4. Ensure tests pass for multisite too: `./bin/test.sh --multisite yes`

**Fixing a Bug:**
1. Add failing test case in `tests/` (regression test)
2. Implement fix
3. Run: `./bin/test.sh --filter test_name_of_regression_test`
4. Run full suite: `./bin/test.sh`
5. Verify with linting: `npm run lint`

**Updating Dependencies:**
- PHP dependencies: Edit `composer.json`, run `composer update package-name`
- Node dependencies: Edit `package.json`, run `npm install`
- Submodules: `cd submodule-dir && git checkout new-version && cd .. && git add submodule-dir`

**Checking CI Status:**
- All tests must pass on GitHub Actions before PR can merge
- Check Actions tab in GitHub for detailed logs
- CI runs on push to develop/staging/production branches and all PRs to develop

## Trust These Instructions

These instructions are validated and current as of the repository state. Only search for additional information if:
- A command documented here fails with an unexpected error
- You need information about a specific feature not covered here
- Instructions appear outdated (e.g., file paths don't exist)

When in doubt, check the README.md, but the instructions here take precedence for build/test/CI procedures.
