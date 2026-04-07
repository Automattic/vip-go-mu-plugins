# Release

## Branch/release model

- `develop` merge triggers deployment sync to `vip-go-mu-plugins-built` (`.github/workflows/deploy.yml`).
- `staging` push triggers staging release automation (`.github/workflows/release-staging.yml`).
- `production` push triggers production tagging + changelog automation (`.github/workflows/release-prod.yml`).

Release version format: `vYYYYMMDD.N`.

## Preflight checks

Before merging release PRs:

```bash
git submodule update --init --recursive
composer install
npm install
npm run lint
npm run test
```

Also verify required CI workflows are green.

## Staging release

1. Create PR from `develop` into `staging`.
2. Title pattern: `Staging release: vYYYYMMDD.N`.
3. Merge after checks pass.
4. Confirm `Release - Staging` workflow completed (PR label updates + changelog publish).

## Production release

1. Create PR from `staging` into `production`.
2. Title pattern: `Production release: vYYYYMMDD.N`.
3. Merge after checks pass.
4. Confirm `Release - Production` workflow completed:
   - tag created/pushed
   - GitHub release created
   - PR labels updated
   - changelog published

## Rollback notes

- Preferred rollback is a revert PR merged through normal branch flow.
- After a revert, run the same staging -> production promotion process to publish the fix.
- If a production tag already exists for the day, the next release increments the minor suffix automatically.

## Next docs

- [Setup](setup.md)
- [Testing](testing.md)
- [Architecture](architecture.md)
- [Agent guide](../AGENTS.md)
