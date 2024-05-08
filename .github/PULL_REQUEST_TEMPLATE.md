<!--
## For Automatticians!

:wave: Just a quick reminder that this is a public repo. Please don't include any internal links or sensitive data (like PII, private code, customer names, site URLs, etc. Any fixes related to security should be discussed with Platform before opening a PR. If you're not sure if something is safe to share, please just ask!

## For external contributors!

Welcome! We look forward to your contribution! ❤️
-->
## Description
<!--
A few sentences providing more context and describing the overall goals of the Pull Request.

Should include any special considerations, decisions, and links to relevant publicly available sources (e.g. GitHub issues)
-->

## Changelog Description

<!-- Changelogs are published for our customers. Well-written entries help them stay informed on platform changes and all of the great work that we do! -->

<!-- Write a concise description of changes in the relevant section.
- Add new line items as needed.
- Entries should follow the [Common Changelog Style Guide](https://github.com/vweevers/common-changelog). 
- Remove all unused sections before merging.
- Proof-read.
-->

### Added
- <!-- e.g. "Added a new set of filters for MFA status" -->
- <!-- e.g. "Dev-env: Added PHP 8.3 image" -->

### Removed
- <!-- e.g. "Dropped support of Node.js 14" -->
- 

### Fixed
- <!-- e.g. "Fixed a bug causing blank lines in content to be ignored when using the Regex Parser" -->

### Changed
- <!-- e.g. "Increased priority of wp_mail_from filter in VIP Dashboard to prevent unintentional overriding" -->
- <!-- e.g. "HyperDB: Updated to latest version to fix PHP error with addslashes()" -->


## Pre-review checklist

Please make sure the items below have been covered before requesting a review:

- [ ] This change works and has been tested locally or in Codespaces (or has an appropriate fallback).
- [ ] This change works and has been tested on a sandbox.
- [ ] This change has relevant unit tests (if applicable).
- [ ] This change uses a rollout method to ease with deployment (if applicable - especially for large scale actions that require writes).
- [ ] This change has relevant documentation additions / updates (if applicable).
- [ ] I've created a changelog description that aligns with the provided examples.

## Pre-deploy checklist

- [ ] VIP staff: Ensure any alerts added/updated conform to internal standards (see internal documentation). 

## Steps to Test

<!--
Outline the steps to test and verify the PR here.

Example:

1. Check out PR.
1. Go to `wp-admin` > `Tools` > `Bakery`
1. Click on "Bake Cookies" button.
1. Verify cookies are delicious.
-->