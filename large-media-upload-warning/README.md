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

Two browser interception points:

1. `wp.Uploader.prototype.init` is wrapped to bind plupload's `BeforeUpload` event. This covers the Media Library modal and Classic Editor "Add Media".
2. `wp.mediaUtils.uploadMedia` is wrapped at runtime to gate uploads triggered from inside Gutenberg blocks (drag-drop, paste, file-pick).

Both surfaces share a single `<dialog>` confirm helper rendered without React or Backbone so it works regardless of editor context.

Server-side, a priority-5 filter on `wp_handle_upload_prefilter` (and the matching `wp_handle_sideload_prefilter`) emits a Logstash event when an oversized image reaches PHP. The filter **never sets `$file['error']`** — it is observe-only.

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

E2E coverage lives at `__tests__/e2e/specs/large_media_warning.spec.ts` and exercises six cases (Media Library cancel/confirm/below-threshold, Classic confirm, Gutenberg cancel/confirm). The test environment lowers the threshold to 512 KB via `wp config set` in `__tests__/e2e/bin/setup-env.sh`.

## PR description notes

The v1 implementation **does not cover** REST media uploads (`POST /wp/v2/media`). The working assumption is that REST uploads are largely programmatic — import scripts, mobile apps, headless setups — and not the editorial path we're protecting. **This assumption is not verified.** After v1 ships, Logstash data on `large_media_upload_attempted` events can be used to estimate the REST share and decide whether v2 should add REST coverage.

Other v1 deferrals:

- `wp_set_script_translations()` is not yet wired; user-facing strings are translation-ready (called through `wp.i18n.__`) but no `.po`/`.json` translations are loaded.
- HEIC and TIFF are in the allowlist even though they are not in WordPress core's default upload-allowed MIME list. The warning fires at file-pick time, independent of core's upload-validation pass.
