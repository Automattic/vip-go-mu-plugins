# Temporary RTC revision metadata workaround

## Cause and scope

[Core ticket #66329](https://core.trac.wordpress.org/ticket/66329) is documented
in the public [core PR #14359](https://github.com/WordPress/wordpress-develop/pull/14359).
The revisions REST controller reads every REST-visible post meta key from the
revision, although WordPress only copies revision-enabled keys onto revisions.
A non-revisioned key therefore appears as its default rather than the live value.

Gutenberg registers `_crdt_document` as a single string, visible in REST edit
context, with revisions explicitly disabled. It must retain the latest shared
document across content restores. The editor restores content by reading a
revision and saving its content and meta to the parent post. Sending the
revision's empty CRDT default back erases the live persisted document.

The managed RTC loader in `integrations/real-time-collaboration.php` installs a
`rest_prepare_revision` compatibility filter. Only when the RTC plugin is loaded,
and the response represents a revision (including an autosave revision), it omits
the non-revisioned `_crdt_document` field. An explicitly revision-enabled CRDT key
is left alone. Other metadata, REST schemas, permissions, ordinary post reads and
writes, and own-draft autosaves returning the parent post are unchanged.

This is deliberately **not** a full backport of #14359: unrelated non-revisioned
metadata still has core's existing behavior. No database migration, document
rewrite, upstream Gutenberg edit, or WebSocket protocol change is involved.
Already-cleared CRDT state is not recovered by this patch. It targets Gutenberg's
current unaliased `_crdt_document` REST field; a custom REST alias for that key
is outside scope. Unrelated metadata aliases remain unchanged.

## Verification

Run the real REST/controller and database regression:

```sh
CI=1 ./bin/test.sh --filter RTC_Revision_Meta_Test
CI=1 ./bin/test.sh --multisite 1 --filter RTC_Revision_Meta_Test
npm run phplint
npm run phpcs
npm run test:smoke
```

The fixture uses Gutenberg's CRDT meta registration contract and a genuine
base64-encoded Yjs update, not a mocked metadata API. It saves through REST,
restores revision content and meta through REST, then checks the persisted CRDT
bytes and restored revisioned metadata. It also exercises revision collections,
autosave creation/reads, own-draft autosaves, unaffected metadata, the inactive
integration, field projection with and without meta, HEAD responses, global and
subtype-specific CRDT revision opt-ins, and read/write authorization. The external
managed plugin build and WebSocket service are not loaded by this PHP regression.

## Sandbox rollout and removal

Before applying to production, use a disposable VIP sandbox with the managed RTC
integration enabled and the deployed Gutenberg build:

1. Have two editors open a disposable post, make/save edits, and confirm that
   `_crdt_document` is populated through an authorized REST edit-context read.
2. Restore an older revision. Confirm content restores, revision responses omit
   `_crdt_document`, and the live document is not reset to an empty string.
   With no other CRDT saves in flight, compare its bytes before/after the restore.
3. Confirm both editors converge, can continue editing/saving, and can reload
   without losing or duplicating edits. Exercise autosaves and own-draft saves.
4. Verify normal post edit responses still include the document, customer
   revision-enabled meta still restores, and a site without RTC is unaffected.

This local patch has not been deployed or sandbox-tested. Use the normal reviewed
VIP platform sandbox/staged rollout; revert this commit to roll back. Removing the
response-only filter does not require reverting stored data.

Remove the hook and callback after every supported/deployed core version has the
upstream revision-meta fix (track #66329/#14359), and rerun the end-to-end restore
regression and sandbox checks without it. Do not assume a release version from an
open upstream PR. The filter is harmless when core already omits the field.
