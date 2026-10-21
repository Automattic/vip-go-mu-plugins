# Temporary revision metadata workaround (Core #66329)

## Cause and scope

[Core ticket #66329](https://core.trac.wordpress.org/ticket/66329) is documented
in the public [Core PR #14359](https://github.com/WordPress/wordpress-develop/pull/14359).
The revisions REST controller reads every REST-visible post meta key from the
revision, although WordPress only copies revision-enabled keys onto revisions.
A non-revisioned key therefore appears as its default rather than the live value.
The block editor restores a revision by saving its content and metadata to the
parent post, unintentionally overwriting that parent's non-revisioned metadata.

`001-core.php` loads `001-core/revision-meta.php`, a platform-wide
`rest_prepare_revision` response filter. It removes REST-visible registered
metadata that is absent from `wp_post_revision_meta_keys()` for the parent post
type. It handles global and subtype registrations (including subtype overrides),
custom REST names, and keys enabled or disabled by the revision-meta filter.
It has no RTC dependency or hardcoded metadata keys. This protects ordinary
custom fields, pattern sync status, and Gutenberg's non-revisioned CRDT document.

Revision-enabled metadata is retained. The filter only changes actual revision
objects, including autosave revisions; normal post reads/writes and own-draft
autosaves returning the parent post are unchanged. Field projections, HEAD
semantics, permissions, and unregistered custom response fields are not modified.
Cores without the revision-meta API are left alone.

This is a **response-level workaround**, not a full backport of #14359: REST
OPTIONS schemas still describe the original registered fields, and metadata is
filtered after the controller prepares it rather than before it is read. It works
with the Core and Gutenberg controllers' existing `rest_prepare_revision` hook
without replacing either controller. It does not alter metadata registrations,
revision storage, normal saves, or WebSocket behavior. Already-cleared data is
not recovered. On cores that already omit non-revisioned fields it is a no-op.
No upstream release number or class name is used to assume the bug is fixed.

## Verification

```sh
CI=1 ./bin/test.sh --filter VIP_Revision_Meta_Test
CI=1 ./bin/test.sh --multisite 1 --filter VIP_Revision_Meta_Test
npm run phplint
npm run phpcs
npm run test:smoke
```

The regression uses real REST controllers and database persistence. It saves and
restores revision content and metadata, checking that revisioned values restore
while non-revisioned values survive. It covers global/subtype registrations,
aliases, filtered revision keys, invalid filter results, subtype revision opt-ins
and opt-outs, pattern sync status, revision collections, autosaves, own-draft
saves, field projections, HEAD responses, and authorization. The CRDT fixture
uses Gutenberg's registration contract and a genuine base64-encoded Yjs update,
but does not load the external managed plugin build or live WebSocket service.

## Sandbox rollout and removal

Before production application, use the normal reviewed VIP sandbox/staged rollout.
This now applies to every site, not only those with RTC enabled. No data migration
or database write is performed by the workaround.

1. On a disposable site without RTC, register revision-enabled and non-revisioned
   REST metadata (including an alias). Save multiple versions of a post and
   restore an older revision in the block editor. Confirm revision-enabled values
   restore and non-revisioned live values survive. Repeat with a pattern and
   confirm its sync status does not change.
2. Confirm revision and autosave-revision responses omit non-revisioned fields,
   while normal post responses and own-draft autosaves retain them. Exercise
   custom post types and customer integrations that read revision metadata.
3. On an RTC-enabled sandbox with the deployed Gutenberg build, open a disposable
   post in two editors, save edits, and record the populated `_crdt_document`
   through an authorized REST edit-context read.
4. Restore an older revision. Confirm content and revisioned metadata restore and
   the live document is not cleared. With no concurrent CRDT saves in flight,
   compare its bytes before/after. Confirm both editors converge, keep editing
   and saving, and reload without lost or duplicated edits.

No sandbox validation or deployment has been performed. Roll back by reverting
this change; no stored-data rollback is needed.

Remove the require, hook, and compatibility module once all supported/deployed
cores include the upstream fix. Rerun the regression and sandbox checks without
the module. Keep the regression as protection against future metadata loss.
