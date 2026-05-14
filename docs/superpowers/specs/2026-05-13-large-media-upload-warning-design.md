# Large media upload warning — design

**Status:** Approved for plan
**Date:** 2026-05-13
**Owner:** rinat.khaziev@a8c.com

## Problem

The VIP file service is a remote object store fronted by a PHP stream wrapper (`class-vip-filesystem.php`, `class-vip-filesystem-local-stream-wrapper.php`). Reads and writes that look local to PHP are actually network round-trips to the file service API. When editors upload very large images, two failure modes follow:

1. The upload itself ties up a PHP worker for the entire transfer to the file service, raising request clock time.
2. Subsequent reads (image regeneration, EXIF, metadata) re-fetch the same large bytes remotely, compounding worker pressure.

Severe cases have caused worker exhaustion on customer sites. The cause is usually an unsuspecting editor — a phone photo or DSLR JPEG dropped straight into a post — not a malicious actor.

## Goals

- Warn editors at file-pick time, before the bytes leave their browser, when they are about to upload an unusually large image.
- Cover both the Block Editor (Gutenberg) and the Classic Editor, plus the standalone Media Library at `upload.php`.
- Be observable: emit telemetry when a large upload occurs even if the warning was bypassed or never shown.
- Be killswitchable per site, per environment.

## Non-goals (v1)

- Hard-blocking uploads of any size. The feature is advisory.
- Auto-resizing or transcoding on the client. Out of scope.
- Covering programmatic/REST upload paths (`POST /wp/v2/media`). See **Open question: REST coverage** below — flagged for PR discussion.
- Covering non-image MIME types. PDFs and videos have different size norms and warrant separate thresholds.

## Hard gates

These constrain every implementation choice below.

1. **Non-breaking.** Editorial workflow must never regress because of this module. Any defect in our code must degrade to "no warning" rather than "broken upload." All interceptors fall through on exception; all server-side filters are observe-only; the module ships behind a killswitch.
2. **Classic Editor + Gutenberg.** Both surfaces are first-class; neither is a follow-up.
3. **Comprehensive Playwright coverage.** Every supported surface gets at least one positive and one negative test, plus a "below threshold" regression guard.

## Approach (summary)

Client-side warning at file-pick time, on every supported upload surface. Optional server-side telemetry to quantify exposure without changing behavior.

Two browser interception points are necessary because WordPress has two parallel upload pipelines:

- **plupload** drives the Media Library modal and the Classic Editor "Add Media" flow. Hooked via the `plupload_init` PHP filter, which lets us register a JS callback bound to plupload's `BeforeUpload` event.
- **`@wordpress/media-utils` `uploadMedia()`** drives drag-drop / paste / file-pick directly inside Gutenberg blocks. Plupload is not involved. We wrap `window.wp.mediaUtils.uploadMedia` at runtime.

A shared JS confirmation helper renders the dialog so both pipelines present the same UX.

## Module layout

```
large-media-upload-warning.php                # root wire-up: require + bootstrap
large-media-upload-warning/
  class-large-media-upload-warning.php        # PHP: enqueue, localize, telemetry filter
  js/
    plupload-warning.js                       # plupload BeforeUpload binding
    gutenberg-warning.js                      # wraps wp.mediaUtils.uploadMedia
    shared-confirm.js                         # native <dialog> helper (no React dep)
  README.md                                   # operator-facing notes incl. killswitch
```

Follows the existing repo pattern documented in `AGENTS.md`: a thin root entry file wires up a module directory. No edits to upstream/submodule directories.

## Configuration surface

All three knobs are wired through both a constant (for `vip-config.php`) and a filter (for runtime overrides):

| Knob | Constant | Filter | Default |
|---|---|---|---|
| Enabled | `VIP_LARGE_MEDIA_WARNING_ENABLED` | `vip_large_media_warning_enabled` | `false` at initial release; flipped to `true` in a follow-up after one cycle of staging soak (see Rollout) |
| Threshold (bytes) | `VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES` | `vip_large_media_warning_threshold_bytes` | `8 * 1024 * 1024` (8 MB) |
| MIME allowlist | n/a | `vip_large_media_warning_mime_types` | `[ 'image/jpeg', 'image/png', 'image/webp', 'image/tiff', 'image/heic' ]` |

The filter wins over the constant. The killswitch is the single highest-priority knob: when disabled, no JS is enqueued and the telemetry filter is not registered.

## Client behavior

### Shared confirm helper (`shared-confirm.js`)

- Renders a `<dialog>` element with two buttons: **Cancel upload** (default focus) and **Upload anyway**.
- Returns `Promise<boolean>` — `true` for "upload anyway".
- Includes a *"Don't ask again this session"* checkbox stored under `sessionStorage['vip_large_media_warning_dismissed']`. When set, future warnings in the same tab session auto-resolve to `true`.
- Strings via `wp.i18n.__` when available, with literal-English fallback for environments where `wp.i18n` is not yet loaded.
- No React, no Backbone — keeps it usable from both pipelines without bundling.

Copy (v1, subject to i18n polish):

> **Large image upload**
> This image is large (X.X MB). Large images make uploads slow and can cause errors on your site. We recommend resizing the image to under Y MB before uploading.
> Do you want to continue anyway?

### plupload path (`plupload-warning.js`)

- Registered server-side via the `plupload_init` PHP filter, which adds an `init` callback property pointing at a JS function name. plupload calls it once per uploader instance.
- The init callback binds `BeforeUpload(up, file)`:
  - If `file.size < threshold` or `file.type` not in the MIME allowlist → return immediately (no warning).
  - Else, `up.stop()` to pause the queue, then await the shared confirm.
  - On confirm: record `file.id` on a per-uploader `Set` so subsequent rebinds within the same instance don't re-prompt; resume via `up.start()`.
  - On cancel: `up.removeFile(file)`, then `up.start()` to drain any remaining queued files.
- All logic wrapped in `try { ... } catch (e) { console.warn(...) }`. Any exception falls through to plupload's native behavior. **(Gate 1.)**

### Gutenberg path (`gutenberg-warning.js`)

- Runs on `wp.domReady`.
- Wraps `window.wp.mediaUtils.uploadMedia` (and `window.wp.mediaUtilsExperimental.uploadMedia` if present in newer Gutenberg builds):
  - For each entry in the incoming `filesList`, if it exceeds threshold AND matches an allowed MIME, await the shared confirm.
  - Cancelled entries are removed from the list. If `onError` was provided, invoke it once per cancelled file with `{ code: 'large_media_cancelled', message: '...' }`.
  - Confirmed entries pass through unchanged.
  - If the resulting list is empty, return without calling the original `uploadMedia`.
- Idempotent: a sentinel property `__vipLargeMediaWarningWrapped` guards re-wrap on hot reloads or duplicate enqueues.
- Feature-detected: if `wp.mediaUtils.uploadMedia` is absent (older WP, theme override, custom build), no-op. **(Gate 1.)**

### Failure mode

Both interceptors are observe-and-augment. The original upload code path runs unchanged whenever:
- The module is disabled.
- The file is below threshold or not an allowed MIME.
- An exception is thrown anywhere in our code.
- The required global (plupload, `wp.mediaUtils.uploadMedia`) is not present.

This is the load-bearing property for Gate 1.

## Server behavior

A single filter on `wp_handle_upload_prefilter` at priority **5** (before `class-vip-filesystem.php`'s validator at default priority 10). It:

- Reads `$file['size']` and `$file['type']`.
- If size exceeds threshold AND MIME is in the allowlist, emits a Logstash event via the repo's existing `logstash` module, with payload `{ feature: 'large_media_upload_attempted', size, mime, user_id, screen, blog_id }`.
- Returns `$file` unchanged. **Never** sets `$file['error']`. **(Gate 1.)**

Telemetry is intentionally decoupled from client gating: if a file exceeds threshold and reaches PHP, we want to know — whether the warning was shown, dismissed, bypassed, or unavailable.

No REST hook in v1. See open question below.

## Open question — REST coverage (note for PR description)

The decision to skip REST coverage in v1 rests on the assumption that REST media uploads (`POST /wp/v2/media`) are largely **programmatic** — used by import scripts, mobile apps, headless setups, and external integrations — and therefore not the editorial path we're protecting.

**This assumption is not verified.** It is possible that a meaningful share of editorial uploads on customer sites flows through REST (e.g., the WordPress mobile apps, third-party publishing tools, custom block editor extensions). If so, the warning surface is incomplete and we should consider:

- Adding the same telemetry filter to `rest_pre_insert_attachment` or the REST upload handler so we at least *observe* large REST uploads.
- Returning a soft warning header rather than a confirm dialog (no UI on REST).
- Letting clients opt in to enforcement via a request header.

**Action:** flag this in the PR description. After v1 ships, query Logstash for large editorial uploads that bypassed the client warning to estimate the REST share. Decide v2 scope from data, not assumption.

## Testing strategy

### Threshold for tests

Production default is 8 MB. The e2e environment overrides the threshold to **512 KB** via a filter applied in the e2e bootstrap mu-plugin (`dev-env-plugin.php` or an e2e-specific mu-plugin loaded by `bin/setup-env.sh`). This avoids needing multi-MB fixtures and keeps the test signal sharp.

### Fixtures

- **Above threshold:** reuse the existing `__tests__/e2e/test_media/image_01.jpg` (1.9 MB, already committed). At a 512 KB test threshold, it triggers the warning.
- **Below threshold:** add **one new** fixture `__tests__/e2e/test_media/image_small.jpg` at roughly 100 KB. Produced once locally by downscaling `image_01.jpg` (e.g. `sips -Z 400 image_01.jpg --out image_small.jpg`) and committed as a regular binary. No build-time fixture generation.
- No multi-MB fixtures generated or committed.

### New page object

`__tests__/e2e/lib/pages/large-media-warning-modal.ts` with:

- `waitForVisible(): Promise<void>`
- `isVisible(): Promise<boolean>` (for the negative case — must not throw if absent)
- `confirm(): Promise<void>`
- `cancel(): Promise<void>`

### New spec

`__tests__/e2e/specs/large_media_warning.spec.ts`, five cases:

| # | Surface | Fixture | Action | Expected |
|---|---|---|---|---|
| 1 | Media Library (`upload.php`) | `image_01.jpg` | Cancel in modal | No attachment created; no upload POST |
| 2 | Media Library (`upload.php`) | `image_01.jpg` | Confirm in modal | Attachment created, URL returned |
| 3 | Classic Editor | `image_01.jpg` | Confirm in modal | Image inserted into post (skipped via `E2E_CLASSIC_TESTS`) |
| 4a | Gutenberg image block | `image_01.jpg` | Confirm | Image block populated |
| 4b | Gutenberg image block | `image_01.jpg` | Cancel | Image block not populated, no upload network call |
| 5 | Media Library | `image_small.jpg` | (no modal expected) | Upload completes; `isVisible()` of modal returns `false` |

### Non-regression of existing specs

Existing specs use `image_01.jpg` and `image_02.jpg` (both ~1.7–1.9 MB). At the test threshold of 512 KB they would now trigger the warning, breaking those specs.

Two options to keep them green without rewriting:

- **A. Swap their fixture to `image_small.jpg`.** Lowest-touch change; these specs don't exercise large-file behavior so the size is incidental to their intent.
- **B. Extend `MediaUploadPage` with an `{ expectWarning?: boolean }` option** that auto-confirms when set, defaulting to `false`. Existing specs need no signature change but will fail on warnings — which is the intent: warnings during unrelated tests are themselves a regression signal.

**Recommendation: A.** Swap fixtures in existing specs. Keeps each spec focused on one behavior and avoids hiding warnings behind auto-confirm helpers in unrelated tests.

## Rollout

1. Ship with `VIP_LARGE_MEDIA_WARNING_ENABLED` defaulting to `false`. Module loaded, JS not enqueued, telemetry off.
2. Enable in staging environments; verify e2e suite green, verify telemetry events appear.
3. Flip default to `true` in a subsequent release. Sites can override via constant or filter.
4. Monitor Logstash for `large_media_upload_attempted` events. Use the data to:
   - Tune the default threshold.
   - Decide on REST coverage (see open question).
   - Decide on extending to PDFs and videos.

## Risks

| Risk | Mitigation |
|---|---|
| Our wrapper breaks a non-standard third-party uploader that depends on `wp.mediaUtils.uploadMedia` identity | Wrap is idempotent and feature-detected; falls through on exception; killswitch flips it off without a deploy |
| Threshold too low → editorial friction | Threshold is filterable per site; ships disabled-by-default for one release |
| Threshold too high → no protection | Telemetry quantifies actual upload size distribution after v1 ships; raise threshold based on data |
| REST share of editorial uploads is non-trivial | Documented open question; data from v1 telemetry informs v2 scope |
| Future Gutenberg refactor changes `wp.mediaUtils.uploadMedia` signature | Wrapper feature-detects; new spec case ensures CI catches signature drift |

## Files touched

**New:**
- `large-media-upload-warning.php`
- `large-media-upload-warning/class-large-media-upload-warning.php`
- `large-media-upload-warning/js/plupload-warning.js`
- `large-media-upload-warning/js/gutenberg-warning.js`
- `large-media-upload-warning/js/shared-confirm.js`
- `large-media-upload-warning/README.md`
- `__tests__/e2e/specs/large_media_warning.spec.ts`
- `__tests__/e2e/lib/pages/large-media-warning-modal.ts`
- `__tests__/e2e/test_media/image_small.jpg` (~100 KB)
- `tests/test-large-media-upload-warning.php` (PHPUnit for the PHP class: threshold resolution, MIME allowlist, telemetry payload shape)

**Modified:**
- `001-core.php` or equivalent loader to `require_once` the new root entry file.
- `dev-env-plugin.php` (existing e2e bootstrap mu-plugin in this repo): add a `vip_large_media_warning_threshold_bytes` filter callback that returns `524288` when the e2e environment is active. If `dev-env-plugin.php` turns out to be wrong load context, fall back to introducing a small dedicated `__tests__/e2e/bin/mu-plugins/large-media-warning-e2e.php` and loading it via the e2e setup script — confirm load context as the first step of implementation.
- Existing e2e specs that reference `image_01.jpg` / `image_02.jpg` for incidental media — swap to `image_small.jpg`. Implementation plan must enumerate these specs explicitly via grep before code changes. The non-incidental users are tests that specifically validate the upload pipeline; those stay on `image_01.jpg` and exercise the warning's confirm path.

**Not touched:**
- Any upstream/submodule directory listed in AGENTS.md.
- `class-vip-filesystem.php`, `class-vip-filesystem-local-stream-wrapper.php` (no changes to the stream wrapper itself).
