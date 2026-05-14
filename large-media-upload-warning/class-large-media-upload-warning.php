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

			$handled = apply_filters( 'vip_large_media_warning_log_handler', null, $payload );
			if ( null === $handled && function_exists( '\\Automattic\\VIP\\Logstash\\log2logstash' ) ) {
				\Automattic\VIP\Logstash\log2logstash( $payload );
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Telemetry must never disrupt the upload pipeline.
			// Intentionally swallow: this filter is observe-only.
			unset( $e );
		}

		return $file;
	}
}
