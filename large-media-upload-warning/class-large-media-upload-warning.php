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
