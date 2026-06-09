# Large media upload warning

Warns editors at file-pick time when they upload large images, in both Gutenberg and Classic Editor, before bytes reach the VIP file service.

## Why

Uploads to the file service are remote object-store writes. Large images tie up PHP workers, can cause request timeouts, and have caused worker exhaustion on customer sites. This module surfaces a confirmation dialog before the upload starts so editors get a chance to resize.

## Configuration

| Knob | Constant | Filter | Default |
|---|---|---|---|
| Enabled | `VIP_LARGE_MEDIA_WARNING_ENABLED` | `vip_large_media_warning_enabled` | `false` at initial release; flipped to `true` after one cycle of staging soak |
| Threshold | `VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES` | `vip_large_media_warning_threshold_bytes` | `8388608` (8 MB) |
| MIME allowlist | n/a | `vip_large_media_warning_mime_types` | `image/jpeg`, `image/png`, `image/webp`, `image/tiff`, `image/heic` |

The filter wins over the constant. Disabling the module skips all JS enqueue and unhooks the telemetry filter.

Two related observability seams (used primarily by tests):

- `pre_vip_large_media_warning_log` — a `pre_*`-style filter; return non-null to short-circuit the real Logstash call.
- `\Automattic\VIP\Logstash\log2logstash` — the underlying log sink. Events use feature name `large_media_upload_attempted`.

## How it works

Two browser interception points, one shared `<dialog>`-based confirm helper. Both are loaded in the head so they install before any editor JS runs.

### 1. Capture-phase `change` listener on `document`

The primary chokepoint. Catches every file-input selection — Media Library "Select Files", `media-new.php`'s drop-zone Select Files, Classic Editor "Add Media", Gutenberg image-block placeholder Upload, REST/Gutenberg file inputs. On an oversized image we call `stopImmediatePropagation`, run the dialog, and either:
- **Confirm:** re-attach the files via a fresh `DataTransfer` and dispatch a new `change` event marked with an internal `Symbol` so our own listener passes it through to the original pipeline. A short-lived `pendingApprovals` map keeps the network-layer wrap (below) from re-prompting.
- **Cancel:** clear `input.value` so the original pipeline never sees the file.

### 2. `XMLHttpRequest.send` + `globalThis.fetch` wraps

The safety net. Catches uploads whose file-pick path doesn't go through a `change` event we'd see — drag-drop onto the Media Library modal (plupload's drop-zone, which intercepts `drop` itself), drag-drop onto a Gutenberg canvas, and any pipeline that programmatically `FormData.append(file)`. When the request body is a `FormData` containing one or more `File`s and any is oversized, we:
- Pause `send`/`fetch` until the user answers.
- **Confirm:** call the original `send`/`fetch` with the same arguments.
- **Cancel (XHR):** call `up.removeFile(file)` on plupload's tracker (so plupload's internal queue resets — otherwise the modal wedges), `xhr.abort()`, remove `#media-item-<plupload-id>` DOM nodes that `wp-includes/js/plupload/handlers.js` leaves behind on `media-new.php`, and walk `wp.Uploader.queue` / `state.library` / `state.selection` / `wp.media.model.Attachments.all` to remove the orphan `wp.media.model.Attachment` that backs the visible "uploading…" tile. Sets `attachment.destroyed = true` so the library's validator refuses to re-add on the next `observe()` re-sync.
- **Cancel (fetch):** return a WP-error-shaped `Response` (`{ success: false, code: 'large_media_upload_cancelled', message, data: { status: 400, message } }`) so Gutenberg's apiFetch / `mediaUpload` surfaces "Upload cancelled." instead of the generic "The response is not a valid JSON response."

The dialog itself is rendered without React or Backbone so it works in every editor context. A tiny `plupload-tracker.js` is inlined immediately after `wp-plupload` to wrap `plupload.Uploader` and expose the currently-uploading `{ up, file }` so the XHR cancel path can call `up.removeFile(file)`.

### Server-side

A priority-5 filter on `wp_handle_upload_prefilter` (and the matching `wp_handle_sideload_prefilter`) emits a Logstash event when an oversized image reaches PHP. The filter **never sets `$file['error']`** — it is observe-only.

The Logstash payload includes `feature`, `severity`, `message`, and an `extra` block with `size`, `mime`, `user_id`, `blog_id`. The spec also listed a `screen` field; that was intentionally dropped in v1 because the upload prefilter hook fires in contexts (async-upload, REST, sideloads) where `get_current_screen()` is not reliably populated, so including the field would surface spurious nulls and make analytics noisier. Add it back if a more reliable source of context (e.g. REFERER parsing) is wired in a later pass.

`wp_handle_upload_prefilter` fires for REST uploads (`POST /wp/v2/media` calls `media_handle_upload` → `wp_handle_upload` internally), so server-side telemetry captures REST regardless of which client sent the request.

## Screens covered

The JS interceptors enqueue on these admin screens:

- `upload.php` (Media Library)
- `media-new.php` (Add new media)
- `post.php`, `post-new.php` (post/page edit, both Classic and Gutenberg)
- `site-editor.php` (block-theme Site Editor)
- `widgets.php` (block-based widgets)

## Failure mode

Every interceptor is wrapped in `try/catch`. On any error the original upload path runs unchanged. If the killswitch flips off, no JS is enqueued and the PHP filter is not registered. Editorial workflows cannot regress from this module.

The MIME allowlist is treated as a positive-match list. If it is empty, no file triggers the warning — this is an intentional quiet failure mode rather than a loud one, consistent with the rest of the module.

## E2E

E2E coverage lives at `__tests__/e2e/specs/large_media_warning.spec.ts`. CI exercises four cases: Media Library cancel, Media Library confirm, Media Library below-threshold (no dialog), and Classic Editor confirm. The test environment lowers the threshold to 512 KB via `wp config set` in `__tests__/e2e/bin/setup-env.sh`.

Two Gutenberg image-block cases (cancel / confirm) are present but `test.skip`'d. They fail because `EditorPage.addImage` does not reliably open the Gutenberg block inserter under Playwright (`.editor-block-list-item-image` never appears before the timeout) — that is, the failure is in the test harness's path to the file picker, before our DOM `change` interceptor would even see the event. The interceptor itself works in Gutenberg in manual testing, and the Media Library cases exercise the same `change` + XHR cancel path that the Gutenberg cases would, so cross-pipeline coverage is preserved.

## Scope and v1 deferrals

### REST coverage

In-browser REST uploads (`POST /wp/v2/media` from Gutenberg's image block, the block editor's `mediaUpload`, or anything else that posts a `FormData` body through `fetch`) **are** intercepted client-side — the `globalThis.fetch` wrap runs the same threshold/MIME check and dialog flow as the file-input path, and returns a WP-error-shaped 400 on cancel.

REST uploads from outside the browser (mobile apps, headless setups, import scripts using cURL or HTTP libraries) are not intercepted client-side because no JS runs there. They are still captured by **server-side telemetry**: `wp_handle_upload_prefilter` fires during REST's `wp_handle_upload` call, so `large_media_upload_attempted` events are emitted regardless of client. That data can drive a v2 decision on whether REST needs a soft warning response header or opt-in enforcement.

### Other v1 deferrals

- `wp_set_script_translations()` is not yet wired; user-facing strings are translation-ready (called through `wp.i18n.__`) but no `.po`/`.json` translations are loaded.
- HEIC and TIFF are in the allowlist even though they are not in WordPress core's default upload-allowed MIME list. The warning fires at file-pick time, independent of core's upload-validation pass.
- Video and other non-image MIME types are out of scope. Video uploads have a different bandwidth profile and likely warrant a separate, higher threshold; defer to a follow-up driven by Logstash data on actual upload size distributions.
