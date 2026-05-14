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

	private const HANDLE_SHARED = 'vip-large-media-warning-shared';

	/**
	 * Whether the module is active.
	 *
	 * Resolution order: filter > constant > default `false`.
	 */
	public function is_enabled(): bool {
		$default = defined( 'VIP_LARGE_MEDIA_WARNING_ENABLED' )
			? (bool) constant( 'VIP_LARGE_MEDIA_WARNING_ENABLED' )
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
			? (int) constant( 'VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES' )
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

			$handled = apply_filters( 'pre_vip_large_media_warning_log', null, $payload );
			if ( null === $handled && function_exists( '\\Automattic\\VIP\\Logstash\\log2logstash' ) ) {
				\Automattic\VIP\Logstash\log2logstash( $payload );
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Telemetry must never disrupt the upload pipeline.
			// Intentionally swallow: this filter is observe-only.
			unset( $e );
		}

		return $file;
	}

	/**
	 * Enqueue assets on admin screens that can upload media.
	 *
	 * Two head-loaded scripts:
	 *   - shared-confirm.js (the dialog helper)
	 *   - upload-interceptor.js (capture-phase `change` + `drop` interceptor)
	 *
	 * Loading both in the head guarantees our document-level capture-phase listeners
	 * are registered before any plupload init or React-tree mount runs in the body.
	 * That gives us first dibs on every `change`/`drop` event in the page, which is
	 * what makes the warning reliable across Media Library, Classic Editor's Add
	 * Media, Gutenberg's image-block placeholder, and Gutenberg drag-drop.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( ! $this->is_admin_upload_screen( $hook ) ) {
			return;
		}

		$base_url = plugins_url( 'js/', __FILE__ );

		wp_enqueue_script(
			self::HANDLE_SHARED,
			$base_url . 'shared-confirm.js',
			[ 'wp-i18n' ],
			$this->asset_version( 'shared-confirm.js' ),
			false
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

		wp_enqueue_script(
			'vip-large-media-warning-interceptor',
			$base_url . 'upload-interceptor.js',
			[ self::HANDLE_SHARED ],
			$this->asset_version( 'upload-interceptor.js' ),
			false
		);

		// plupload-tracker: inlined immediately after wp-plupload so we wrap
		// `plupload.Uploader` before WP's inline media JS instantiates one. The
		// XHR cancel path needs the current `{ up, file }` to call
		// `up.removeFile(file)`; without that, plupload's queue is left holding
		// a FAILED file and the modal's upload UI wedges.
		wp_enqueue_script( 'wp-plupload' );
		$tracker_js = $this->get_asset_contents( 'plupload-tracker.js' );
		if ( '' !== $tracker_js ) {
			wp_add_inline_script( 'wp-plupload', $tracker_js, 'after' );
		}
	}

	private function get_asset_contents( string $relative_path ): string {
		$file = __DIR__ . '/js/' . $relative_path;
		if ( ! file_exists( $file ) ) {
			return '';
		}
		// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local file read.
		$contents = file_get_contents( $file );
		return false === $contents ? '' : $contents;
	}

	private function is_admin_upload_screen( string $hook ): bool {
		$allowed = [ 'upload.php', 'media-new.php', 'post.php', 'post-new.php', 'site-editor.php', 'widgets.php' ];
		return in_array( $hook, $allowed, true );
	}

	private function asset_version( string $relative_path = 'shared-confirm.js' ): string {
		$file = __DIR__ . '/js/' . $relative_path;
		return file_exists( $file ) ? (string) filemtime( $file ) : '1';
	}
}
