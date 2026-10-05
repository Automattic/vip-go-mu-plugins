# End-to-end tests (`__tests__/e2e`)

Playwright-based e2e tests for this repository.

## Prerequisites

- Node.js + npm
- VIP CLI (`@automattic/vip`) for a locally managed site

## Run from repo root (recommended)

```bash
npm --prefix __tests__/e2e ci
npm run lint:e2e
npm run typecheck:e2e
npm run test-e2e
```

## Run from this directory

```bash
npm ci
npm run lint
npm run typecheck
npm test
```

`npm test` creates a uniquely named local VIP site, runs Playwright, then destroys that site even if setup or a test fails. Set `E2E_PRESERVE_ON_FAILURE=true` to keep a failed local site for investigation. Set `E2E_SITE_SLUG` to choose a specific local site slug.

Set `E2E_BASE_URL` to run against an existing site. In that mode the runner skips VIP setup and cleanup; the caller owns the target site's lifecycle.

## Optional environment variables

- `E2E_BASE_URL`
- `E2E_USER`
- `E2E_PASSWORD`
- `E2E_SITE_SLUG`
- `E2E_PRESERVE_ON_FAILURE`
- `WORDPRESS_VERSION`

Defaults are configured in `playwright.config.ts` and `lib/global-setup.ts`.

## Setup options

Pass setup flags through `setup-env`:

```bash
npm run setup-env -- -v 6.8
npm run setup-env -- -p /path/to/mu-plugins
npm run setup-env -- -c /path/to/client-code
```
