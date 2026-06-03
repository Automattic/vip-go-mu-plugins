# Release

## Branch/release model

- `develop` merge triggers deployment sync to `vip-go-mu-plugins-built` (`.github/workflows/deploy.yml`).
- `staging` push triggers staging release automation (`.github/workflows/release-staging.yml`).
- `production` push triggers production tagging + changelog automation (`.github/workflows/release-prod.yml`).

Release version format: `vYYYYMMDD.N`.

## Scheduled release PRs

`.github/workflows/create-release-prs.yml` creates weekly release PRs on Tuesdays at 11:00 MST:

- `Production release: vYYYYMMDD.N` from `staging` into `production`.
- `Staging release: vYYYYMMDD.N` from `develop` into `staging`.

The `N` suffix increments from existing same-day release PRs and tags. When no `vYYYYMMDD.N` release exists yet for the day, the scheduled production PR uses `.0` and the scheduled staging PR uses `.1`.

The workflow can also be run manually with `workflow_dispatch`. Select `both`, `production-release`, or `staging-release` to choose which release PRs to create.

Manual runs are not limited to Tuesday at 11:00 MST. They use the current MST date for the release version and still skip PR creation when a matching open PR already exists or when there are no commits to promote.

The workflow skips PR creation when a matching open release PR already exists or when there are no commits to promote between the selected branches.

The workflow uses `GITHUB_TOKEN` to create release PRs. Repository Actions settings must allow GitHub Actions to create pull requests, and workflows will not auto-run for PRs opened by `GITHUB_TOKEN` (trigger via `workflow_dispatch` if needed).

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
