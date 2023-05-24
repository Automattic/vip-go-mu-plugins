<!--
## For Automatticians!

:wave: Just a quick reminder that this is a public repo. Please don't include any internal links or sensitive data (like PII, private code, customer names, site URLs, etc. Any fixes related to security should be discussed with Platform before opening a PR. If you're not sure if something is safe to share, please just ask!

### BEFORE YOU PROCEED!!

If you’re editing a feature without changing the spirit of the implementation, fixing bugs, or performing upgrades, then please proceed!

If you’re adding a feature or changing the spirit of an existing implementation, please create a proposal in Cantina P2 using the MU Plugins Proposal Block Pattern. Please mention the [CODEOWNERS](.github/CODEOWNERS) of this repository and relevant stakeholders in your proposal :). Please be aware that any unplanned work may take some time to get reviewed. Thank you 🙇‍♀️🙇!

## For external contributors!

Welcome! We look forward to your contribution! ❤️
-->
## Description
<!--
A few sentences describing the overall goals of the Pull Request.

Should include any special considerations, decisions, and links to relevant GitHub issues.

Please don't include internal or private links :)
-->

## Changelog Description
<!--
A description of the context of the change for a changelog. It should have a title, examples (if applicable), and why the change was made.

**Please keep the changelog title format same as in example below (### <Title>), as this is later used to generate the changelog entry title.**

Example for a plugin upgrade:

### Plugin Updated: Jetpack 9.2.1

We upgraded Jetpack 9.2 to Jetpack 9.2.1.

Not a lot of significant changes in this patch release, just bugfixes and compatibility improvements.
-->
## Pre-review checklist

Please make sure the items below have been covered before requesting a review:

- [ ] This change works and has been tested locally (or has an appropriate fallback).
- [ ] This change works and has been tested on a Go sandbox.
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
