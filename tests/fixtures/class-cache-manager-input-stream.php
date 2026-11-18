<?php

/**
 * Replace only the incoming request body during an AJAX dispatch.
 */
class VIP_Cache_Manager_Input_Stream {
	public $context;
	public static $body = '';
	private $offset     = 0;

	/**
	 * Accept the request input stream.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Stream wrapper interface.
	public function stream_open( $path, $mode, $options, &$opened_path ): bool {
		return 'php://input' === $path;
	}

	/**
	 * Read the supplied request bytes.
	 */
	public function stream_read( $count ): string {
		$bytes         = substr( self::$body, $this->offset, $count );
		$this->offset += strlen( $bytes );
		return $bytes;
	}

	/**
	 * Report when the request has been consumed.
	 */
	public function stream_eof(): bool {
		return $this->offset >= strlen( self::$body );
	}

	/**
	 * Supply the metadata requested by file_get_contents.
	 */
	public function stream_stat(): array {
		return [];
	}
}
