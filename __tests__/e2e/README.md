# End-to-end tests (`__tests__/e2e`)

Playwright-based e2e tests for this repository.

## Prerequisites

- Node.js + npm
- VIP CLI (`@automattic/vip`)

## Run from repo root (recommended)

```bash
npm --prefix __tests__/e2e ci
npm run setup-e2e-env
npm run lint:e2e
npm run typecheck:e2e
npm run test-e2e
npm run destroy-e2e-env
```

## Run from this directory

```bash
npm ci
npm run setup-env
npm run lint
npm run typecheck
npm test
npm run destroy-env
```

## Optional environment variables

- `E2E_BASE_URL`
- `E2E_USER`
- `E2E_PASSWORD`

Defaults are configured in `playwright.config.ts` and `lib/global-setup.ts`.

## Setup options

Pass setup flags through `setup-env`:

```bash
npm run setup-env -- -v 6.8
npm run setup-env -- -p /path/to/mu-plugins
npm run setup-env -- -c /path/to/client-code
```
