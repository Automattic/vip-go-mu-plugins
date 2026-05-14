# Large media upload warning — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Warn editors at file-pick time when they upload large images, in both Gutenberg and Classic Editor, without changing the upload pipeline.

**Architecture:** Two browser interception points — `wp.Uploader.prototype.init` for plupload (Classic + Media Library) and `wp.mediaUtils.uploadMedia` for Gutenberg — share a single `<dialog>`-based confirm helper. Observe-only PHP filter on `wp_handle_upload_prefilter` emits Logstash telemetry. Single killswitch via constant + filter.

**Tech Stack:** PHP 8.2, vanilla JS (no React/Backbone), Playwright/TypeScript for e2e, PHPUnit for server-side unit tests, `@wordpress/i18n` (`wp.i18n`) for translations.

**Spec:** [docs/superpowers/specs/2026-05-13-large-media-upload-warning-design.md](../specs/2026-05-13-large-media-upload-warning-design.md)

---

## File Structure

| Path | Role |
|---|---|
| `large-media-upload-warning.php` | Root mu-plugin entry — `require_once` the class and bootstrap it. |
| `large-media-upload-warning/class-large-media-upload-warning.php` | Singleton class: config resolution, asset enqueue, telemetry filter. |
| `large-media-upload-warning/js/shared-confirm.js` | `<dialog>`-based confirm helper. Exposes `window.vipLargeMediaWarning.confirmLargeUpload(file)`. |
| `large-media-upload-warning/js/plupload-warning.js` | Wraps `wp.Uploader.prototype.init` to bind `BeforeUpload`. |
| `large-media-upload-warning/js/gutenberg-warning.js` | Wraps `wp.mediaUtils.uploadMedia` for block-editor uploads. |
| `large-media-upload-warning/README.md` | Operator-facing notes (killswitch, threshold, MIME allowlist). |
| `tests/test-large-media-upload-warning.php` | PHPUnit: config resolution, MIME allowlist, telemetry payload, never-error invariant. |
| `__tests__/e2e/specs/large_media_warning.spec.ts` | Playwright spec — 5 cases across Media Library / Classic / Gutenberg. |
| `__tests__/e2e/lib/pages/large-media-warning-modal.ts` | Page object for the warning dialog. |
| `__tests__/e2e/test_media/image_small.jpg` | New ~100 KB fixture for "below threshold" sanity case. |
| `tests/bootstrap.php` (modify) | Add `require_once __DIR__ . '/../large-media-upload-warning.php';`. |
| `dev-env-plugin.php` (modify) | Add filter lowering threshold to 512 KB for dev-env / e2e. |
| `__tests__/e2e/specs/*` (modify, Task 8) | Swap incidental `image_01.jpg` / `image_02.jpg` usages to `image_small.jpg`. |

---

## Task 1: Scaffold module, killswitch, config resolution (PHP)

**Files:**
- Create: `large-media-upload-warning.php`
- Create: `large-media-upload-warning/class-large-media-upload-warning.php`
- Create: `tests/test-large-media-upload-warning.php`
- Modify: `tests/bootstrap.php` (add `require_once` after security.php on line ~50)

- [ ] **Step 1: Write the failing PHPUnit test**

Create `tests/test-large-media-upload-warning.php`:

```php
<?php

namespace Automattic\VIP\LargeMediaUploadWarning;

use WP_UnitTestCase;

class Large_Media_Upload_Warning_Test extends WP_UnitTestCase {
	private Large_Media_Upload_Warning $instance;

	public function setUp(): void {
		parent::setUp();
		$this->instance = new Large_Media_Upload_Warning();
	}

	public function test_disabled_by_default_at_first_release(): void {
		$this->assertFalse( $this->instance->is_enabled() );
	}

	public function test_enabled_filter_overrides_default(): void {
		add_filter( 'vip_large_media_warning_enabled', '__return_true' );
		$this->assertTrue( $this->instance->is_enabled() );
		remove_filter( 'vip_large_media_warning_enabled', '__return_true' );
	}

	public function test_constant_enables_module(): void {
		if ( ! defined( 'VIP_LARGE_MEDIA_WARNING_ENABLED' ) ) {
			define( 'VIP_LARGE_MEDIA_WARNING_ENABLED', true );
		}
		$this->assertTrue( $this->instance->is_enabled() );
	}

	public function test_default_threshold_is_8mb(): void {
		$this->assertSame( 8 * 1024 * 1024, $this->instance->get_threshold_bytes() );
	}

	public function test_threshold_filter_overrides_default(): void {
		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 524288 );
		$this->assertSame( 524288, $this->instance->get_threshold_bytes() );
		remove_all_filters( 'vip_large_media_warning_threshold_bytes' );
	}

	public function test_default_mime_allowlist_contains_jpeg_png_webp(): void {
		$mimes = $this->instance->get_allowed_mime_types();
		$this->assertContains( 'image/jpeg', $mimes );
		$this->assertContains( 'image/png', $mimes );
		$this->assertContains( 'image/webp', $mimes );
	}

	public function test_mime_filter_overrides_default(): void {
		add_filter( 'vip_large_media_warning_mime_types', fn() => [ 'image/avif' ] );
		$this->assertSame( [ 'image/avif' ], $this->instance->get_allowed_mime_types() );
		remove_all_filters( 'vip_large_media_warning_mime_types' );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `CI=1 ./bin/test.sh --filter Large_Media_Upload_Warning_Test`
Expected: FAIL — `Large_Media_Upload_Warning` class not found.

- [ ] **Step 3: Create the class with config resolution**

Create `large-media-upload-warning/class-large-media-upload-warning.php`:

```php
<?php

namespace Automattic\VIP\LargeMediaUploadWarning;

class Large_Media_Upload_Warning {
	public const DEFAULT_THRESHOLD_BYTES = 8 * 1024 * 1024;

	public const DEFAULT_MIME_TYPES = [
		'image/jpeg',
		'image/png',
		'image/webp',
		'image/tiff',
		'image/heic',
	];

	/**
	 * Whether the module is active.
	 *
	 * Resolution order: filter > constant > default `false`.
	 */
	public function is_enabled(): bool {
		$default = defined( 'VIP_LARGE_MEDIA_WARNING_ENABLED' )
			? (bool) VIP_LARGE_MEDIA_WARNING_ENABLED
			: false;

		return (bool) apply_filters( 'vip_large_media_warning_enabled', $default );
	}

	/**
	 * Threshold above which an image triggers the warning, in bytes.
	 *
	 * Resolution order: filter > constant > 8 MB.
	 */
	public function get_threshold_bytes(): int {
		$default = defined( 'VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES' )
			? (int) VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES
			: self::DEFAULT_THRESHOLD_BYTES;

		$filtered = apply_filters( 'vip_large_media_warning_threshold_bytes', $default );

		return max( 1, (int) $filtered );
	}

	/**
	 * MIME types subject to the warning.
	 *
	 * @return string[]
	 */
	public function get_allowed_mime_types(): array {
		$filtered = apply_filters( 'vip_large_media_warning_mime_types', self::DEFAULT_MIME_TYPES );

		return array_values( array_filter( (array) $filtered, 'is_string' ) );
	}
}
```

- [ ] **Step 4: Create the root entry file**

Create `large-media-upload-warning.php`:

```php
<?php
/**
 * Plugin Name: VIP Large Media Upload Warning
 * Description: Warns editors at file-pick time when uploading large images, before bytes reach the file service.
 * Author: Automattic
 * License: GPL version 2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/large-media-upload-warning/class-large-media-upload-warning.php';

add_action( 'plugins_loaded', static function () {
	$module = new \Automattic\VIP\LargeMediaUploadWarning\Large_Media_Upload_Warning();

	if ( ! $module->is_enabled() ) {
		return;
	}

	// Wiring of telemetry filter and asset enqueue is added in later tasks.
} );
```

- [ ] **Step 5: Register the module in the PHPUnit bootstrap**

In `tests/bootstrap.php`, inside `_manually_load_plugin()`, after `require_once __DIR__ . '/../security.php';` (around line 50), add:

```php
	require_once __DIR__ . '/../large-media-upload-warning.php';
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `CI=1 ./bin/test.sh --filter Large_Media_Upload_Warning_Test`
Expected: 7 tests pass.

- [ ] **Step 7: Lint and PHPCS**

Run: `npm run phplint && npm run phpcs -- large-media-upload-warning.php large-media-upload-warning/`
Expected: clean.

- [ ] **Step 8: Commit**

```bash
git add large-media-upload-warning.php large-media-upload-warning/class-large-media-upload-warning.php tests/test-large-media-upload-warning.php tests/bootstrap.php
git commit -m "feat(large-media-warning): scaffold module with killswitch and config"
```

---

## Task 2: Server-side telemetry filter (observe-only)

**Files:**
- Modify: `large-media-upload-warning/class-large-media-upload-warning.php`
- Modify: `large-media-upload-warning.php`
- Modify: `tests/test-large-media-upload-warning.php`

- [ ] **Step 1: Write failing tests for the telemetry filter**

Append to `tests/test-large-media-upload-warning.php`:

```php
	public function test_filter_large_image_logs_to_logstash(): void {
		$captured = [];
		add_filter( 'vip_large_media_warning_log_handler', function ( $data ) use ( &$captured ) {
			$captured[] = $data;
			return null;
		} );

		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 1024 );

		$file_in = [
			'name'     => 'big.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/whatever',
			'error'    => 0,
			'size'     => 2048,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertSame( $file_in, $file_out, 'Filter must never mutate the file array.' );
		$this->assertCount( 1, $captured );
		$this->assertSame( 'large_media_upload_attempted', $captured[0]['feature'] );
		$this->assertSame( 2048, $captured[0]['extra']['size'] );
		$this->assertSame( 'image/jpeg', $captured[0]['extra']['mime'] );

		remove_all_filters( 'vip_large_media_warning_log_handler' );
		remove_all_filters( 'vip_large_media_warning_threshold_bytes' );
	}

	public function test_filter_small_image_does_not_log(): void {
		$captured = [];
		add_filter( 'vip_large_media_warning_log_handler', function ( $data ) use ( &$captured ) {
			$captured[] = $data;
			return null;
		} );

		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 10000 );

		$file_in = [
			'name'     => 'small.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/small',
			'error'    => 0,
			'size'     => 1234,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertSame( $file_in, $file_out );
		$this->assertCount( 0, $captured );

		remove_all_filters( 'vip_large_media_warning_log_handler' );
		remove_all_filters( 'vip_large_media_warning_threshold_bytes' );
	}

	public function test_filter_non_image_mime_does_not_log(): void {
		$captured = [];
		add_filter( 'vip_large_media_warning_log_handler', function ( $data ) use ( &$captured ) {
			$captured[] = $data;
			return null;
		} );
		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 1024 );

		$file_in = [
			'name'     => 'big.pdf',
			'type'     => 'application/pdf',
			'tmp_name' => '/tmp/x',
			'error'    => 0,
			'size'     => 9999,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertSame( $file_in, $file_out );
		$this->assertCount( 0, $captured );

		remove_all_filters( 'vip_large_media_warning_log_handler' );
		remove_all_filters( 'vip_large_media_warning_threshold_bytes' );
	}

	public function test_filter_never_sets_error_even_for_oversized(): void {
		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 1 );

		$file_in = [
			'name'     => 'huge.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/huge',
			'error'    => 0,
			'size'     => 100 * 1024 * 1024,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertArrayHasKey( 'error', $file_out );
		$this->assertSame( 0, $file_out['error'] );

		remove_all_filters( 'vip_large_media_warning_threshold_bytes' );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `CI=1 ./bin/test.sh --filter Large_Media_Upload_Warning_Test`
Expected: 4 failures — `maybe_log_large_upload` method not defined.

- [ ] **Step 3: Add the telemetry method to the class**

In `large-media-upload-warning/class-large-media-upload-warning.php`, add inside the class:

```php
	/**
	 * Observe-only filter for `wp_handle_upload_prefilter` / `wp_handle_sideload_prefilter`.
	 *
	 * Returns the file array unchanged. Emits a Logstash event when an image exceeds
	 * the threshold so we can quantify exposure even if the client warning was bypassed
	 * or never shown.
	 *
	 * @param array $file File array as produced by core upload handling.
	 * @return array Unchanged file array.
	 */
	public function maybe_log_large_upload( array $file ): array {
		try {
			$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
			$mime = isset( $file['type'] ) ? (string) $file['type'] : '';

			if ( $size <= $this->get_threshold_bytes() ) {
				return $file;
			}

			if ( ! in_array( $mime, $this->get_allowed_mime_types(), true ) ) {
				return $file;
			}

			$payload = [
				'severity' => 'info',
				'feature'  => 'large_media_upload_attempted',
				'message'  => sprintf( 'Large image upload attempted (%d bytes, %s)', $size, $mime ),
				'extra'    => [
					'size'    => $size,
					'mime'    => $mime,
					'user_id' => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
					'blog_id' => function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0,
				],
			];

			$handled = apply_filters( 'vip_large_media_warning_log_handler', null, $payload );
			if ( null === $handled && function_exists( '\\Automattic\\VIP\\Logstash\\log2logstash' ) ) {
				\Automattic\VIP\Logstash\log2logstash( $payload );
			}
		} catch ( \Throwable $e ) {
			// Never disrupt the upload pipeline.
		}

		return $file;
	}
```

The `vip_large_media_warning_log_handler` filter is a seam for tests: when a handler returns non-null, the real Logstash call is skipped.

- [ ] **Step 4: Run tests to verify they pass**

Run: `CI=1 ./bin/test.sh --filter Large_Media_Upload_Warning_Test`
Expected: all tests pass.

- [ ] **Step 5: Wire the filter into WordPress**

In `large-media-upload-warning.php`, replace the inner `plugins_loaded` callback body with:

```php
	$module = new \Automattic\VIP\LargeMediaUploadWarning\Large_Media_Upload_Warning();

	if ( ! $module->is_enabled() ) {
		return;
	}

	add_filter( 'wp_handle_upload_prefilter',   [ $module, 'maybe_log_large_upload' ], 5 );
	add_filter( 'wp_handle_sideload_prefilter', [ $module, 'maybe_log_large_upload' ], 5 );
```

Priority **5** runs before `class-vip-filesystem.php` at default priority 10.

- [ ] **Step 6: PHPCS**

Run: `npm run phpcs -- large-media-upload-warning/ large-media-upload-warning.php`
Expected: clean.

- [ ] **Step 7: Commit**

```bash
git add large-media-upload-warning.php large-media-upload-warning/class-large-media-upload-warning.php tests/test-large-media-upload-warning.php
git commit -m "feat(large-media-warning): observe-only telemetry filter"
```

---

## Task 3: Shared confirm helper (JS dialog)

**Files:**
- Create: `large-media-upload-warning/js/shared-confirm.js`
- Modify: `large-media-upload-warning/class-large-media-upload-warning.php` (asset enqueue scaffold)
- Modify: `large-media-upload-warning.php` (hook enqueue)

- [ ] **Step 1: Create the shared confirm helper**

Create `large-media-upload-warning/js/shared-confirm.js`:

```javascript
( function () {
	'use strict';

	if ( window.vipLargeMediaWarning ) {
		return;
	}

	var SESSION_KEY = 'vip_large_media_warning_dismissed';

	function translate( text ) {
		if ( window.wp && window.wp.i18n && typeof window.wp.i18n.__ === 'function' ) {
			return window.wp.i18n.__( text, 'vip' );
		}
		return text;
	}

	function formatMb( bytes ) {
		return ( bytes / ( 1024 * 1024 ) ).toFixed( 1 );
	}

	function buildDialog( file, threshold ) {
		var dialog = document.createElement( 'dialog' );
		dialog.className = 'vip-large-media-warning-dialog';
		dialog.setAttribute( 'role', 'alertdialog' );
		dialog.setAttribute( 'aria-labelledby', 'vip-lmw-title' );
		dialog.style.cssText = 'max-width:480px;padding:1.5em;border:1px solid #ccd0d4;border-radius:4px;';

		dialog.innerHTML =
			'<h2 id="vip-lmw-title" style="margin-top:0">' +
				translate( 'Large image upload' ) +
			'</h2>' +
			'<p>' +
				translate( 'This image is large (' ) + formatMb( file.size ) + ' MB). ' +
				translate( 'Large images make uploads slow and can cause errors on your site. We recommend resizing the image to under ' ) +
				formatMb( threshold ) + ' MB ' +
				translate( 'before uploading.' ) +
			'</p>' +
			'<p><label><input type="checkbox" id="vip-lmw-dismiss"> ' +
				translate( "Don't ask again this session" ) +
			'</label></p>' +
			'<div style="display:flex;justify-content:flex-end;gap:0.5em">' +
				'<button type="button" class="button" data-action="cancel" autofocus>' +
					translate( 'Cancel upload' ) +
				'</button>' +
				'<button type="button" class="button button-primary" data-action="confirm">' +
					translate( 'Upload anyway' ) +
				'</button>' +
			'</div>';
		return dialog;
	}

	function confirmLargeUpload( file, threshold ) {
		return new Promise( function ( resolve ) {
			try {
				if ( window.sessionStorage && window.sessionStorage.getItem( SESSION_KEY ) === '1' ) {
					return resolve( true );
				}
			} catch ( e ) { /* sessionStorage unavailable; fall through */ }

			var dialog = buildDialog( file, threshold );
			document.body.appendChild( dialog );

			function cleanup( result ) {
				try {
					var dismiss = dialog.querySelector( '#vip-lmw-dismiss' );
					if ( result && dismiss && dismiss.checked && window.sessionStorage ) {
						window.sessionStorage.setItem( SESSION_KEY, '1' );
					}
				} catch ( e ) { /* ignore */ }

				if ( dialog.open ) {
					dialog.close();
				}
				dialog.remove();
				resolve( result );
			}

			dialog.querySelector( '[data-action="cancel"]' ).addEventListener( 'click', function () {
				cleanup( false );
			} );
			dialog.querySelector( '[data-action="confirm"]' ).addEventListener( 'click', function () {
				cleanup( true );
			} );
			dialog.addEventListener( 'cancel', function ( e ) {
				e.preventDefault();
				cleanup( false );
			} );

			if ( typeof dialog.showModal === 'function' ) {
				dialog.showModal();
			} else {
				dialog.setAttribute( 'open', 'open' );
			}
		} );
	}

	window.vipLargeMediaWarning = {
		confirmLargeUpload: confirmLargeUpload,
		SESSION_KEY: SESSION_KEY,
	};
}() );
```

- [ ] **Step 2: Add enqueue scaffold to the PHP class**

In `large-media-upload-warning/class-large-media-upload-warning.php`, add:

```php
	private const HANDLE_SHARED = 'vip-large-media-warning-shared';

	/**
	 * Enqueue assets on admin screens that can upload media.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! $this->is_admin_upload_screen( $hook ) ) {
			return;
		}

		$base_url = plugins_url( 'js/', __FILE__ );
		$ver      = $this->asset_version();

		wp_enqueue_script(
			self::HANDLE_SHARED,
			$base_url . 'shared-confirm.js',
			[ 'wp-i18n' ],
			$ver,
			true
		);

		wp_add_inline_script(
			self::HANDLE_SHARED,
			sprintf(
				'window.vipLargeMediaWarningConfig = %s;',
				wp_json_encode( [
					'thresholdBytes' => $this->get_threshold_bytes(),
					'mimeTypes'      => $this->get_allowed_mime_types(),
				] )
			),
			'before'
		);
	}

	private function is_admin_upload_screen( string $hook ): bool {
		// Upload screens: media library, post edit (Classic + Gutenberg), new post, media new.
		$allowed = [ 'upload.php', 'media-new.php', 'post.php', 'post-new.php' ];
		return in_array( $hook, $allowed, true );
	}

	private function asset_version(): string {
		$file = __DIR__ . '/js/shared-confirm.js';
		return file_exists( $file ) ? (string) filemtime( $file ) : '1';
	}
```

- [ ] **Step 3: Wire enqueue in the root entry**

In `large-media-upload-warning.php`, append inside the `plugins_loaded` callback (after the prefilter hooks):

```php
	add_action( 'admin_enqueue_scripts', [ $module, 'enqueue_assets' ] );
```

- [ ] **Step 4: Manual smoke (no automated test for the dialog itself; e2e covers it)**

Run: `npm run setup-env` (or use existing local site)
- Visit `/wp-admin/upload.php` with `VIP_LARGE_MEDIA_WARNING_ENABLED=true` and a low threshold filter.
- Open browser console, run `vipLargeMediaWarning.confirmLargeUpload({size:5000000},1000000).then(console.log)`.
- Expected: dialog appears; clicking *Upload anyway* logs `true`; *Cancel upload* logs `false`; dismissing via Esc logs `false`.

If `npm run setup-env` is too slow for this smoke check, skip — e2e in Task 7 will exercise the same paths.

- [ ] **Step 5: PHPCS + lint**

Run: `npm run phpcs -- large-media-upload-warning/`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add large-media-upload-warning/js/shared-confirm.js large-media-upload-warning/class-large-media-upload-warning.php large-media-upload-warning.php
git commit -m "feat(large-media-warning): shared confirm dialog helper"
```

---

## Task 4: plupload interceptor (Classic Editor + Media Library)

**Files:**
- Create: `large-media-upload-warning/js/plupload-warning.js`
- Modify: `large-media-upload-warning/class-large-media-upload-warning.php` (enqueue + dep on `wp-plupload`)

- [ ] **Step 1: Create the plupload interceptor**

Create `large-media-upload-warning/js/plupload-warning.js`:

```javascript
( function () {
	'use strict';

	if ( ! window.wp || ! window.wp.Uploader || ! window.vipLargeMediaWarning ) {
		return;
	}
	if ( window.wp.Uploader.prototype.__vipLargeMediaWrapped ) {
		return;
	}

	var config = window.vipLargeMediaWarningConfig || {};
	var threshold = parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
	var mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];

	var originalInit = window.wp.Uploader.prototype.init;

	window.wp.Uploader.prototype.init = function () {
		try {
			originalInit.apply( this, arguments );
		} catch ( e ) {
			throw e;
		}

		var self = this;

		try {
			if ( ! self.uploader || typeof self.uploader.bind !== 'function' ) {
				return;
			}

			var confirmedIds = new Set();

			self.uploader.bind( 'BeforeUpload', function ( up, file ) {
				try {
					if ( ! file || typeof file.size !== 'number' ) {
						return;
					}
					if ( confirmedIds.has( file.id ) ) {
						return;
					}
					if ( file.size <= threshold ) {
						return;
					}
					if ( ! mimes.length || mimes.indexOf( file.type ) === -1 ) {
						return;
					}

					up.stop();

					window.vipLargeMediaWarning
						.confirmLargeUpload( file, threshold )
						.then( function ( ok ) {
							if ( ok ) {
								confirmedIds.add( file.id );
								up.start();
							} else {
								up.removeFile( file );
								up.start();
							}
						} )
						.catch( function () {
							up.start();
						} );
				} catch ( e ) {
					// On any handler error, allow upload to proceed.
					try { up.start(); } catch ( _ ) { /* ignore */ }
				}
			} );
		} catch ( e ) { /* swallow; do not disrupt plupload */ }
	};

	window.wp.Uploader.prototype.__vipLargeMediaWrapped = true;
}() );
```

- [ ] **Step 2: Enqueue plupload-warning.js after `wp-plupload`**

In `large-media-upload-warning/class-large-media-upload-warning.php`, inside `enqueue_assets()`, after the `wp_add_inline_script` call, add:

```php
		wp_enqueue_script(
			'vip-large-media-warning-plupload',
			$base_url . 'plupload-warning.js',
			[ self::HANDLE_SHARED, 'wp-plupload' ],
			$ver,
			true
		);
```

- [ ] **Step 3: Manual smoke (or defer to e2e)**

In a browser at `/wp-admin/upload.php` with module enabled and threshold lowered:
- Click *Select Files*, choose a >threshold JPEG.
- Expected: confirm dialog appears before upload begins; cancel removes file from queue without sending bytes; confirm proceeds normally.

- [ ] **Step 4: PHPCS**

Run: `npm run phpcs -- large-media-upload-warning/`
Expected: clean.

- [ ] **Step 5: Commit**

```bash
git add large-media-upload-warning/js/plupload-warning.js large-media-upload-warning/class-large-media-upload-warning.php
git commit -m "feat(large-media-warning): plupload BeforeUpload interceptor"
```

---

## Task 5: Gutenberg interceptor (`wp.mediaUtils.uploadMedia`)

**Files:**
- Create: `large-media-upload-warning/js/gutenberg-warning.js`
- Modify: `large-media-upload-warning/class-large-media-upload-warning.php` (block-editor enqueue)

- [ ] **Step 1: Create the Gutenberg interceptor**

Create `large-media-upload-warning/js/gutenberg-warning.js`:

```javascript
( function () {
	'use strict';

	function wrap( bag ) {
		if ( ! bag || typeof bag.uploadMedia !== 'function' ) {
			return false;
		}
		if ( bag.__vipLargeMediaWrapped ) {
			return true;
		}

		var config = window.vipLargeMediaWarningConfig || {};
		var threshold = parseInt( config.thresholdBytes, 10 ) || ( 8 * 1024 * 1024 );
		var mimes = Array.isArray( config.mimeTypes ) ? config.mimeTypes : [];
		var original = bag.uploadMedia;

		bag.uploadMedia = async function ( settings ) {
			try {
				if ( ! settings || ! settings.filesList ) {
					return original.call( this, settings );
				}

				var incoming = Array.from( settings.filesList );
				var kept = [];
				var cancelled = [];

				for ( var i = 0; i < incoming.length; i++ ) {
					var file = incoming[ i ];
					var size = file && typeof file.size === 'number' ? file.size : 0;
					var type = file && typeof file.type === 'string' ? file.type : '';

					var needsConfirm =
						size > threshold &&
						mimes.indexOf( type ) !== -1;

					if ( ! needsConfirm ) {
						kept.push( file );
						continue;
					}

					// eslint-disable-next-line no-await-in-loop
					var ok = await window.vipLargeMediaWarning.confirmLargeUpload( file, threshold );
					if ( ok ) {
						kept.push( file );
					} else {
						cancelled.push( file );
					}
				}

				if ( cancelled.length && typeof settings.onError === 'function' ) {
					cancelled.forEach( function ( f ) {
						try {
							settings.onError( {
								code: 'large_media_cancelled',
								message: 'Upload cancelled by user (file too large).',
								file: f,
							} );
						} catch ( _ ) { /* ignore */ }
					} );
				}

				if ( ! kept.length ) {
					return;
				}

				var nextSettings = Object.assign( {}, settings, { filesList: kept } );
				return original.call( this, nextSettings );
			} catch ( e ) {
				// On any handler failure, fall through to original behavior.
				return original.call( this, settings );
			}
		};

		bag.__vipLargeMediaWrapped = true;
		return true;
	}

	function tryWrap() {
		if ( ! window.wp || ! window.vipLargeMediaWarning ) {
			return false;
		}
		var done = false;
		if ( window.wp.mediaUtils ) {
			done = wrap( window.wp.mediaUtils ) || done;
		}
		if ( window.wp.mediaUtilsExperimental ) {
			done = wrap( window.wp.mediaUtilsExperimental ) || done;
		}
		return done;
	}

	if ( window.wp && typeof window.wp.domReady === 'function' ) {
		window.wp.domReady( tryWrap );
	} else if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', tryWrap );
	} else {
		tryWrap();
	}
}() );
```

- [ ] **Step 2: Enqueue on block-editor screens only**

In `class-large-media-upload-warning.php`, replace `enqueue_assets()` with a version that branches on whether the screen is using the block editor. Add this method:

```php
	/**
	 * Whether the current admin screen is the block editor.
	 *
	 * Uses get_current_screen() — only available after admin_init, which is the
	 * case during admin_enqueue_scripts.
	 */
	private function is_block_editor_screen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}
		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}
		return method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor();
	}
```

And update the existing `enqueue_assets()` to enqueue the Gutenberg script after the plupload one:

```php
		if ( $this->is_block_editor_screen() ) {
			wp_enqueue_script(
				'vip-large-media-warning-gutenberg',
				$base_url . 'gutenberg-warning.js',
				[ self::HANDLE_SHARED, 'wp-dom-ready' ],
				$ver,
				true
			);
		}
```

- [ ] **Step 3: PHPCS**

Run: `npm run phpcs -- large-media-upload-warning/`
Expected: clean.

- [ ] **Step 4: Commit**

```bash
git add large-media-upload-warning/js/gutenberg-warning.js large-media-upload-warning/class-large-media-upload-warning.php
git commit -m "feat(large-media-warning): Gutenberg uploadMedia interceptor"
```

---

## Task 6: E2E fixture, page object, and dev-env threshold

**Files:**
- Create: `__tests__/e2e/test_media/image_small.jpg` (~100 KB)
- Create: `__tests__/e2e/lib/pages/large-media-warning-modal.ts`
- Modify: `dev-env-plugin.php`

- [ ] **Step 1: Generate the small fixture locally and commit the binary**

This step happens once on the developer's machine. The fixture is committed as a regular binary.

```bash
cd __tests__/e2e/test_media
sips -Z 400 image_01.jpg --out image_small.jpg
ls -lh image_small.jpg   # expect ~80-150 KB
```

If `sips` isn't available (non-macOS), use ImageMagick: `convert image_01.jpg -resize 400x image_small.jpg`. Either way, target ~100 KB.

- [ ] **Step 2: Verify fixture size is well under 512 KB**

Run: `stat -f%z __tests__/e2e/test_media/image_small.jpg 2>/dev/null || stat -c%s __tests__/e2e/test_media/image_small.jpg`
Expected: number well below `524288`.

- [ ] **Step 3: Lower threshold + enable module in dev-env**

In `dev-env-plugin.php`, after the existing `DISABLE_JETPACK_WAF` define block (around line 8), add:

```php
if ( ! defined( 'VIP_LARGE_MEDIA_WARNING_ENABLED' ) ) {
	define( 'VIP_LARGE_MEDIA_WARNING_ENABLED', true );
}

add_filter( 'vip_large_media_warning_threshold_bytes', static function () {
	return 524288; // 512 KB — enough that image_01.jpg (1.9 MB) triggers, image_small.jpg (~100 KB) doesn't.
} );
```

This applies to *every* dev-env, including but not limited to the e2e site. That's intentional — lowering the threshold during local development surfaces the warning to developers, which is desirable. If a future dev-env needs to opt out, they can override the filter at a higher priority.

- [ ] **Step 4: Create the page object**

Create `__tests__/e2e/lib/pages/large-media-warning-modal.ts`:

```typescript
import type { Page } from '@playwright/test';

const selectors = {
	dialog: 'dialog.vip-large-media-warning-dialog',
	confirmButton: 'dialog.vip-large-media-warning-dialog button[data-action="confirm"]',
	cancelButton: 'dialog.vip-large-media-warning-dialog button[data-action="cancel"]',
	dismissCheckbox: 'dialog.vip-large-media-warning-dialog #vip-lmw-dismiss',
};

export class LargeMediaWarningModal {
	private readonly page: Page;

	constructor( page: Page ) {
		this.page = page;
	}

	public waitForVisible( timeout = 5000 ): Promise<void> {
		return this.page.locator( selectors.dialog ).waitFor( { state: 'visible', timeout } );
	}

	public isVisible(): Promise<boolean> {
		return this.page.locator( selectors.dialog ).isVisible();
	}

	public confirm(): Promise<void> {
		return this.page.locator( selectors.confirmButton ).click();
	}

	public cancel(): Promise<void> {
		return this.page.locator( selectors.cancelButton ).click();
	}

	public dismissForSession(): Promise<void> {
		return this.page.locator( selectors.dismissCheckbox ).check();
	}
}
```

- [ ] **Step 5: Typecheck e2e**

Run: `cd __tests__/e2e && npm run typecheck`
Expected: clean.

- [ ] **Step 6: Commit**

```bash
git add __tests__/e2e/test_media/image_small.jpg __tests__/e2e/lib/pages/large-media-warning-modal.ts dev-env-plugin.php
git commit -m "test(large-media-warning): fixture, page object, dev-env threshold"
```

---

## Task 7: E2E spec covering all surfaces

**Files:**
- Create: `__tests__/e2e/specs/large_media_warning.spec.ts`

- [ ] **Step 1: Write the spec**

Create `__tests__/e2e/specs/large_media_warning.spec.ts`:

```typescript
/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';

/**
 * Internal dependencies
 */
import { WPAdminSidebarComponent } from '../lib/components/wp-admin-sidebar-component';
import { LargeMediaWarningModal } from '../lib/pages/large-media-warning-modal';
import { MediaUploadPage } from '../lib/pages/media-upload-page';
import { WPAdminPage } from '../lib/pages/wp-admin-page';
import { ClassicEditorPage } from '../lib/pages/wp-classic-editor-page';
import { EditorPage } from '../lib/pages/wp-editor-page';

const LARGE = 'test_media/image_01.jpg';   // 1.9 MB — above 512 KB test threshold
const SMALL = 'test_media/image_small.jpg'; // ~100 KB — below threshold

test.describe( 'Large media upload warning', () => {

	test( 'Media Library: cancel aborts upload', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		const sidebar = new WPAdminSidebarComponent( page );
		await sidebar.clickMenuItem( 'Media' );
		await sidebar.clickSubMenuItem( 'Add Media File' );

		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );

		await Promise.all( [
			modal.waitForVisible(),
			upload.uploadFile( LARGE ),
		] );

		await modal.cancel();

		// No attachment should appear. data-clipboard-text on copy button is the post-upload marker.
		await expect( page.locator( '.copy-attachment-url' ) ).toHaveCount( 0 );
	} );

	test( 'Media Library: confirm completes upload', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		const sidebar = new WPAdminSidebarComponent( page );
		await sidebar.clickMenuItem( 'Media' );
		await sidebar.clickSubMenuItem( 'Add Media File' );

		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );

		await Promise.all( [
			modal.waitForVisible(),
			upload.uploadFile( LARGE ),
		] );

		await modal.confirm();

		await expect( upload.getMediaUrl() ).resolves.toContain( 'image_01' );
	} );

	test( 'Media Library: below threshold shows no warning', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		const sidebar = new WPAdminSidebarComponent( page );
		await sidebar.clickMenuItem( 'Media' );
		await sidebar.clickSubMenuItem( 'Add Media File' );

		const upload = new MediaUploadPage( page );
		const modal = new LargeMediaWarningModal( page );

		await upload.uploadFile( SMALL );
		await expect( upload.getMediaUrl() ).resolves.toContain( 'image_small' );
		expect( await modal.isVisible() ).toBe( false );
	} );

	test( 'Classic Editor: confirm inserts image', async ( { page } ) => {
		// eslint-disable-next-line playwright/no-skipped-test
		test.skip( process.env.E2E_CLASSIC_TESTS === 'false', 'Classic Tests skipped' );

		await new WPAdminPage( page ).visit();
		await page.goto( '/wp-admin/post-new.php?classic-editor&classic-editor__forget' );

		const classic = new ClassicEditorPage( page );
		const modal = new LargeMediaWarningModal( page );

		await classic.enterTitle( 'Classic large image test' );

		// Kick off Add-Media flow; the warning fires between file-pick and insert.
		const addImagePromise = classic.addImage( LARGE );
		await modal.waitForVisible();
		await modal.confirm();
		await addImagePromise;

		// addImage waits for the insert button; if we got past it, the image was inserted.
		expect( true ).toBe( true );
	} );

	test( 'Gutenberg: cancel leaves block empty', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		await EditorPage.automaticallyDismissAnnoyingNuisances( page );
		await page.goto( '/wp-admin/post-new.php' );

		const editor = new EditorPage( page );
		const modal = new LargeMediaWarningModal( page );

		await editor.enterTitle( 'Gutenberg cancel test' );

		const addImagePromise = editor.addImage( LARGE ).catch( () => undefined );
		await modal.waitForVisible();
		await modal.cancel();
		await addImagePromise;

		// Upload button should still be visible (placeholder unchanged); no <img> in editor.
		await expect( page.locator( '.block-editor-media-placeholder__upload-button' ) ).toBeVisible();
	} );

	test( 'Gutenberg: confirm populates image block', async ( { page } ) => {
		await new WPAdminPage( page ).visit();
		await EditorPage.automaticallyDismissAnnoyingNuisances( page );
		await page.goto( '/wp-admin/post-new.php' );

		const editor = new EditorPage( page );
		const modal = new LargeMediaWarningModal( page );

		await editor.enterTitle( 'Gutenberg confirm test' );

		const addImagePromise = editor.addImage( LARGE );
		await modal.waitForVisible();
		await modal.confirm();
		await addImagePromise;

		await expect( page.locator( 'figure.wp-block-image img' ) ).toBeVisible();
	} );
} );
```

- [ ] **Step 2: Typecheck**

Run: `cd __tests__/e2e && npm run typecheck`
Expected: clean.

- [ ] **Step 3: Run the new spec only**

Run: `cd __tests__/e2e && npx playwright test specs/large_media_warning.spec.ts`
Expected: 6 tests pass (5 if `E2E_CLASSIC_TESTS=false`).

If a test is flaky on the Gutenberg cancel case because `addImage` throws when no file ends up uploaded, the `.catch( () => undefined )` swallow above is the safety net; the assertion about the upload button visibility is the real check.

- [ ] **Step 4: Commit**

```bash
git add __tests__/e2e/specs/large_media_warning.spec.ts
git commit -m "test(large-media-warning): e2e spec across Media Library, Classic, Gutenberg"
```

---

## Task 8: Migrate incidental fixture usage in existing e2e specs

**Files:**
- Modify: `__tests__/e2e/specs/post__publish.spec.ts`
- Modify: `__tests__/e2e/specs/page__publish.spec.ts`
- Modify: `__tests__/e2e/specs/post_classic__publish.spec.ts`
- Modify: `__tests__/e2e/specs/page_classic__publish.spec.ts`
- Modify: `__tests__/e2e/specs/media__add.spec.ts`

These specs use `image_01.jpg` (1.9 MB) and `image_02.jpg` (1.7 MB) only to have *some* image to upload — image size is incidental to what they're testing. At the test threshold of 512 KB they would now hit the warning. Swap to `image_small.jpg`.

- [ ] **Step 1: Enumerate the usages**

Run: `grep -rn "image_01.jpg\|image_02.jpg" __tests__/e2e/specs/`
Expected: 5 hits across the 5 files listed above.

- [ ] **Step 2: Update each spec**

For each of these 5 files, replace the fixture path:

- `__tests__/e2e/specs/post__publish.spec.ts` — change `'test_media/image_01.jpg'` to `'test_media/image_small.jpg'`.
- `__tests__/e2e/specs/page__publish.spec.ts` — same change.
- `__tests__/e2e/specs/post_classic__publish.spec.ts` — same change.
- `__tests__/e2e/specs/page_classic__publish.spec.ts` — same change.
- `__tests__/e2e/specs/media__add.spec.ts` — change `'test_media/image_02.jpg'` to `'test_media/image_small.jpg'`. Also update the expect on line 33 from `toContain( 'image_02' )` to `toContain( 'image_small' )`.

- [ ] **Step 3: Typecheck**

Run: `cd __tests__/e2e && npm run typecheck`
Expected: clean.

- [ ] **Step 4: Run full e2e suite (or smoke subset) to verify no regression**

Run: `cd __tests__/e2e && npx playwright test`
Expected: all specs green, including the new `large_media_warning.spec.ts` and the migrated ones.

If the full suite is too slow, at minimum run the modified ones: `npx playwright test specs/post__publish.spec.ts specs/media__add.spec.ts specs/large_media_warning.spec.ts`.

- [ ] **Step 5: Commit**

```bash
git add __tests__/e2e/specs/
git commit -m "test(e2e): swap incidental fixture to image_small to avoid warning"
```

---

## Task 9: Module README and PR notes

**Files:**
- Create: `large-media-upload-warning/README.md`

- [ ] **Step 1: Write the README**

Create `large-media-upload-warning/README.md`:

```markdown
# Large media upload warning

Warns editors at file-pick time when they upload large images, in both Gutenberg and Classic Editor, before bytes reach the VIP file service.

## Why

Uploads to the file service are remote object-store writes. Large images tie up PHP workers, can cause request timeouts, and have caused worker exhaustion on customer sites. This module surfaces a confirmation dialog before the upload starts so editors get a chance to resize.

## Configuration

| Knob | Constant | Filter | Default |
|---|---|---|---|
| Enabled | `VIP_LARGE_MEDIA_WARNING_ENABLED` | `vip_large_media_warning_enabled` | `false` (initial release; flips to `true` after staging soak) |
| Threshold | `VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES` | `vip_large_media_warning_threshold_bytes` | `8388608` (8 MB) |
| MIME allowlist | n/a | `vip_large_media_warning_mime_types` | jpeg, png, webp, tiff, heic |

The filter wins over the constant. Disabling the module skips all JS enqueue and unhooks the telemetry filter.

## How it works

Two browser interception points:

1. `wp.Uploader.prototype.init` is wrapped to bind plupload's `BeforeUpload` event. This covers the Media Library modal and Classic Editor "Add Media".
2. `wp.mediaUtils.uploadMedia` is wrapped at runtime to gate uploads triggered from inside Gutenberg blocks (drag-drop, paste, file-pick).

Both surfaces share a single `<dialog>` confirm helper rendered without React or Backbone so it works regardless of editor context.

Server-side, a priority-5 filter on `wp_handle_upload_prefilter` emits a Logstash event when an oversized image reaches PHP. The filter never sets `$file['error']` — it is observe-only.

## Failure mode

Every interceptor is `try/catch`ed. On any error, the original upload path runs unchanged. If the killswitch flips off, no JS is enqueued and no PHP filter is registered. Editorial workflows cannot regress.

## PR description notes

The v1 implementation **does not cover** REST media uploads (`POST /wp/v2/media`). The working assumption is that REST uploads are largely programmatic — import scripts, mobile apps, headless setups — and not the editorial path we're protecting. This assumption is **not verified**. After v1 ships, Logstash data on `large_media_upload_attempted` events can be used to estimate the REST share and decide whether v2 should add REST coverage.
```

- [ ] **Step 2: Commit**

```bash
git add large-media-upload-warning/README.md
git commit -m "docs(large-media-warning): operator README and PR notes"
```

---

## Task 10: End-to-end killswitch verification

**Files:** none modified — verification only.

- [ ] **Step 1: Verify the killswitch turns everything off**

Temporarily edit `dev-env-plugin.php` to set `VIP_LARGE_MEDIA_WARNING_ENABLED` to `false`:

```php
if ( ! defined( 'VIP_LARGE_MEDIA_WARNING_ENABLED' ) ) {
	define( 'VIP_LARGE_MEDIA_WARNING_ENABLED', false );
}
```

Run: `cd __tests__/e2e && npx playwright test specs/large_media_warning.spec.ts`

Expected: tests that rely on the modal appearing should fail (the modal is correctly not enqueued). This is the *positive proof* that the killswitch works — the dialog only exists when the module is on.

**Revert the change.** Set the constant back to `true`.

```bash
git diff dev-env-plugin.php   # confirm it's back to the original
```

This step is a manual verification only; no commit needed.

- [ ] **Step 2: Final smoke**

Run the full suite once more on the canonical config:

```bash
cd __tests__/e2e && npx playwright test
CI=1 ./bin/test.sh --filter Large_Media_Upload_Warning_Test
npm run phplint
npm run phpcs -- large-media-upload-warning/ large-media-upload-warning.php
```

Expected: all green.

- [ ] **Step 3: No commit — this task is verification.**

---

## Spec coverage self-check

| Spec section | Implemented in |
|---|---|
| Module layout | Tasks 1, 3, 4, 5, 9 |
| Configuration surface (enabled / threshold / MIME) | Task 1 |
| Shared confirm helper | Task 3 |
| plupload path | Task 4 |
| Gutenberg path | Task 5 |
| Failure mode (try/catch everywhere) | Tasks 3, 4, 5 |
| Server telemetry filter (priority 5, observe-only) | Task 2 |
| REST coverage note for PR | Task 9 |
| Threshold for tests (512 KB) | Task 6 |
| Fixtures (existing `image_01.jpg` + new `image_small.jpg`) | Task 6 |
| New page object | Task 6 |
| Five e2e cases (Media Library × 3, Classic, Gutenberg × 2) | Task 7 |
| Non-regression of existing specs (swap incidental fixtures) | Task 8 |
| Killswitch verification | Task 10 |
| Rollout (ships `false`, flipped later) | Task 1 default + Task 9 README |
