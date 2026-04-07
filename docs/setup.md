# Setup

## Prerequisites

- Git (with submodule support)
- PHP (8.0+ compatible; local CI images use newer versions)
- Composer
- Node.js + npm
- Docker (required for `bin/test.sh`)
- Optional: [VIP CLI](https://docs.wpvip.com/technical-references/vip-cli/installing-vip-cli/) for local dev/e2e

## First run

From repository root:

```bash
git submodule update --init --recursive
composer install
npm install
npm --prefix __tests__/e2e ci
```

Quick validation:

```bash
npm run phplint
npm run test:smoke
```

## Local services

- `bin/test.sh` starts temporary Docker network + MySQL 8 + `ghcr.io/automattic/vip-container-images/wp-test-runner`.
- `.devcontainer/docker-compose.yml` uses:
  - `mysql:8.4`
  - `memcached:1.6-alpine`
- e2e environment (`__tests__/e2e/bin/setup-env.sh`) uses VIP CLI and enables Elasticsearch in the dev env.

## Important config and secrets

Do not commit secrets. Define sensitive constants in environment-specific config (`vip-config.php` / platform config), not in this repo.

Common secret-backed constants used by this repo:

- `WPCOM_API_KEY` (Akismet)
- `TWILIO_SID`, `TWILIO_SECRET`, `VIP_TWILIO_VERIFY_SERVICE_SID` (2FA providers)
- `VIP_SMTP_ENABLED`, `VIP_SMTP_USERNAME`, `VIP_SMTP_PASSWORD` (SMTP)
- `VIP_GO_AUTH_COOKIE_KEY`, `VIP_GO_AUTH_COOKIE_IV` (vary-cache encryption)
- `EDGE_CACHE_PURGE_CLIENT_TOKEN`, `PURGE_SERVER_TYPE`, `PURGE_BATCH_SERVER_URL` (cache purge)
- `WP_CRON_CONTROL_SECRET` (cron-control REST security)

## Reset steps

Rebuild dependencies:

```bash
rm -rf vendor node_modules
composer install
npm install
```

Reset submodules:

```bash
git submodule sync --recursive
git submodule update --init --recursive
```

Tear down e2e dev env:

```bash
npm run destroy-e2e-env
```

## Next docs

- [Architecture](architecture.md)
- [Testing](testing.md)
- [Release](release.md)
- [Agent guide](../AGENTS.md)
