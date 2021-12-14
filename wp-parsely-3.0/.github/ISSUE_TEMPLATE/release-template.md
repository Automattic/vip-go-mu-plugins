---
name: Release template
about: Internally used for new releases
title: Release x.y.z
labels: 'Type: Maintenance'

---

:warning: DO NOT MERGE (YET) :warning:

[Remaining work for this Milestone](https://github.com/Parsely/wp-parsely/milestone/14)

PR for tracking changes for the X.Y.Z release. Target release date: DOW DD MMMM YYYY.

- [ ] Merge any outstanding PRs due for this release.
- [ ] Add/Update changelog for this release: PR #XXX
- [ ] Add commit/PR which increases version numbers in the README, plugin bootstrap file, and in the `test_class_version` test in `tests/class-all-test.php`. 
- [ ] Merge this PR.
- [ ] Add signed release tag against `trunk`.
- [ ] Close the current milestone.
- [ ] Open a new milestone for the next release.
- [ ] If any open PRs/issues which were milestoned for this release do not make it into the release, update their milestone.
- [ ] Write a Lobby post.
- [ ] Write an internal P2 post.
