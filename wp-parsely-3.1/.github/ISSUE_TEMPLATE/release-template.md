---
name: Release template
about: Internally used for new releases
title: Release x.y.z
labels: 'Type: Maintenance'

---

:warning: DO NOT MERGE (YET) :warning:

[Remaining work for this Milestone](https://github.com/Parsely/wp-parsely/milestone/14)

PR for tracking changes for the X.Y.Z release. Target release date: DOW DD MMMM YYYY.

- [ ] Merge any outstanding PRs due for this release to `develop`.
- [ ] Notify stakeholders of an upcoming release.
- [ ] Add [PR](https://github.com/Parsely/wp-parsely/pull/XXX) against `develop` which:
  - Increases version numbers in the README, plugin bootstrap file `wp-parsely.php`, and `package.json`.
  - Adds or updates the changelog for the release.
- [ ] Merge this PR.
- [ ] Create a branch from `trunk` named `release-x.y.z`. Merge `develop` into that branch. Open a PR from `release-x.y.z` to `trunk` named _Release x.y.z_.
- [ ] Add signed release tag against `trunk` using GitHub's UI.
- [ ] Close the current milestone.
- [ ] Open a new milestone for the next release.
- [ ] If any open PRs/issues which were milestoned for this release do not make it into the release, update their milestone.
- [ ] Sync changes from `trunk` to `develop`. Update versions in `develop` to be `x.y.z-alpha` (where `x.y.z` is the next milestone).
- [ ] Write a Lobby post.
- [ ] Write an internal P2 post.
