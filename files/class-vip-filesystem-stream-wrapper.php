<?php

namespace Automattic\VIP\Files;

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error

class VIP_Filesystem_Stream_Wrapper {

	/**
	 * Default protocol
	 */
	const DEFAULT_PROTOCOL = 'vip';

	/**
	 * Allowed fopen modes
	 *
	 * We are ignoring `b`, `t` and `+` modes as they do not affect how this
	 * Stream Wrapper works.
	 * Not supporting `c` and `e` modes as these are rarely used and adds complexity
	 * to support.
	 */
	const ALLOWED_MODES = [ 'r', 'w', 'a', 'x' ];

	/**
	 * The Stream context. Set by PHP
	 *
	 * @since   1.0.0
	 * @access  public
	 * @var     resource|nul    Stream context
	 */
	public $context;

	/**
	 * The VIP Files API Client
	 *
	 * @since   1.0.0
	 * @access  public
	 * @var     API_Client  VIP Files API Client
	 */
	public $client;

	/**
	 * The fopen mode for current file
	 *
	 * @since   1.0.0
	 * @access  public
	 * @var     string      The fopen mode
	 */
	protected $mode;

	/**
	 * The file resource fetched through the VIP Files API
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @var     resource    The file resource
	 */
	protected $file;

	/**
	 * The path to the opened file
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @var     string      Opened path
	 */
	protected $path;

	/**
	 * The temp file URI
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @var     string      The file URI
	 */
	protected $uri;

	/**
	 * Is file seekable
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @var     bool        Is seekable
	 */
	protected $seekable;

	/**
	 * Protocol for the stream to register to
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var string  The defined protocol.
	 */
	private $protocol;

	/**
	 * Debug mode flag
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     bool    Is debug mode on
	 */
	private $debug_mode;

	/**
	 * Flush empty file flag
	 *
	 * Flag to determine if an empty file should be flushed to the 
	 * Filesystem.
	 *
	 * @since   1.0.0
	 * @access  private
	 * @var     bool    Should flush empty file
	 */
	private $should_flush_empty;

	/**
	 * Vip_Filesystem_Stream constructor.
	 *
	 * @param API_Client $client
	 * @param string $protocol
	 */
	public function __construct( API_Client $client = null, $protocol = null ) {
		if ( is_null( $client ) ) {
			$this->client = new_api_client();
		} else {
			$this->client = $client;
		}

		$this->protocol = $protocol ? $protocol : self::DEFAULT_PROTOCOL;

		$this->debug_mode = false;
		if ( defined( 'VIP_FILESYSTEM_STREAM_WRAPPER_DEBUG' )
			&& true === VIP_FILESYSTEM_STREAM_WRAPPER_DEBUG ) {
			$this->debug_mode = true;
		}

		// Mark new empty file as flushable
		$this->should_flush_empty = true;
	}

	/**
	 *  Register the Stream.
	 *
	 * Will unregister stream first if it's already registered
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return  bool    true if success, false if failure
	 */
	public function register() {
		if ( in_array( $this->protocol, stream_get_wrappers(), true ) ) {
			stream_wrapper_unregister( $this->protocol );
		}

		return stream_wrapper_register(
		$this->protocol, get_called_class(), STREAM_IS_URL );
	}

	/**
	 * Opens a file
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   string $path URL that was passed to the original function
	 * @param   string $mode Type of access. See `fopen` docs
	 * @param   $options
	 * @param   string $opened_path
	 *
	 * @return  bool    True on success or false on failure
	 */
	public function stream_open( $path, $mode, $options, &$opened_path ) {
		$this->debug( sprintf( 'stream_open => %s + %s + %s', $path, $mode, $options ) );

		$path = $this->trim_path( $path );
		// Also ignore '+' modes since the handlers are all read+write anyway
		$mode = rtrim( $mode, 'bt+' );

		if ( ! $this->validate( $path, $mode ) ) {
			return false;
		}

		try {
			$result = $this->client->get_file( $path );

			if ( is_wp_error( $result ) ) {
				if ( 'file-not-found' !== $result->get_error_code() ) {
					trigger_error(
						sprintf( 'stream_open/get_file failed for %s with error: %s #vip-go-streams', esc_html( $path ), esc_html( $result->get_error_message() ) ),
						E_USER_WARNING
					);

					return false;
				}

				// File doesn't exist on File service so create new file
				$file = $this->string_to_resource( '', $mode );
			} else {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
				$file = fopen( $result, $mode );
			}

			// Get meta data
			$meta           = stream_get_meta_data( $file );
			$this->seekable = $meta['seekable'];
			$this->uri      = $meta['uri'];

			$this->file = $file;
			$this->path = $path;
			$this->mode = $mode;

			// Cache file stats so that calls to url_stat will work
			$stats = fstat( $file );
			$this->client->cache_file_stats( $path, [
				'size'  => $stats['size'],
				'mtime' => $stats['mtime'],
			] );

			return true;
		} catch ( \Exception $e ) {
			trigger_error(
				sprintf( 'stream_open failed for %s with error: %s #vip-go-streams', esc_html( $path ), esc_html( $e->getMessage() ) ),
				E_USER_WARNING
			);

			return false;
		}
	}

	/**
	 * Close a file
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public function stream_close() {
		$this->debug( sprintf( 'stream_close => %s + %s', $this->path, $this->uri ) );

		// Don't attempt to flush new file when in read mode
		if ( $this->should_flush_empty && 'r' !== $this->mode ) {
			$this->stream_flush();
		}

		return $this->close_handler( $this->file );
	}

	/**
	 * Check for end of file
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return bool
	 */
	public function stream_eof() {
		$this->debug( sprintf( 'stream_eof => %s + %s', $this->path, $this->uri ) );

		return feof( $this->file );
	}

	/**
	 * Read the contents of the file
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   $count  Number of bytes to read
	 *
	 * @return  string  The file contents
	 */
	public function stream_read( $count ) {
		$this->debug( sprintf( 'stream_read => %s + %s + %s', $count, $this->path, $this->uri ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread
		$string = fread( $this->file, $count );
		if ( false === $string ) {
			trigger_error(
				sprintf( 'Error reading from file: %s #vip-go-streams', esc_html( $this->path ) ),
				E_USER_WARNING
			);
			return '';
		}

		return $string;
	}

	/**
	 * Flush to a file
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return  bool    True on success. False on failure
	 */
	public function stream_flush() {
		$this->debug( sprintf( 'stream_flush =>  %s + %s', $this->path, $this->uri ) );

		if ( ! $this->file ) {
			return false;
		}

		if ( 'r' === $this->mode ) {
			// No writes in 'read' mode
			trigger_error(
				sprintf( 'stream_flush failed for %s with error: No writes allowed in "read" mode #vip-go-streams', esc_html( $this->path ) ),
				E_USER_WARNING
			);

			return false;
		}

		try {
			// Upload to file service
			$result = $this->client
				->upload_file( $this->uri, $this->path );
			if ( is_wp_error( $result ) ) {
				trigger_error(
					sprintf( 'stream_flush failed for %s with error: %s #vip-go-streams', esc_html( $this->path ), esc_html( $result->get_error_message() ) ),
					E_USER_WARNING
				);

				$this->should_flush_empty = false;

				return false;
			}

			return fflush( $this->file );
		} catch ( \Exception $e ) {
			trigger_error(
				sprintf( 'stream_flush failed for %s with error: %s #vip-go-streams', esc_html( $this->path ), esc_html( $e->getMessage() ) ),
				E_USER_WARNING
			);

			return false;
		}
	}

	/**
	 * Seek a pointer position on a file
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   int   $offset
	 * @param   int   $whence
	 *
	 * @return  bool  True if position was updated, False if not
	 */
	public function stream_seek( $offset, $whence ) {
		$this->debug( sprintf( 'stream_seak =>  %s + %s + %s + %s', $offset, $whence, $this->path, $this->uri ) );

		if ( ! $this->seekable ) {
			// File not seekable
			trigger_error(
				sprintf( 'File not seekable: %s #vip-go-streams', esc_html( $this->path ) ),
				E_USER_WARNING
			);
			return false;
		}

		$result = fseek( $this->file, $offset, $whence );

		if ( -1 === $result ) {
			// Seek failed
			trigger_error(
				sprintf( 'Error seeking on file: %s #vip-go-streams', esc_html( $this->path ) ),
				E_USER_WARNING
			);
			return false;
		}

		return true;
	}

	/**
	 * Write to a file
	 *
	 * @since   1.0.0
	 * @accesss public
	 *
	 * @param   string      $data   The data to be written
	 *
	 * @return  int|bool    Number of bytes written or false on error
	 */
	public function stream_write( $data ) {
		$this->debug( sprintf( 'stream_write =>  %s + %s', $this->path, $this->uri ) );

		if ( 'r' === $this->mode ) {
			// No writes in 'read' mode
			trigger_error(
				sprintf( 'stream_write failed for %s with error: No writes allowed in "read" mode #vip-go-streams', esc_html( $this->path ) ),
				E_USER_WARNING
			);

			return false;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		$length = fwrite( $this->file, $data );

		if ( false === $length ) {
			trigger_error(
				sprintf( 'Error writing to file: %s #vip-go-stream', esc_html( $this->path ) ),
				E_USER_WARNING
			);
			return false;
		}

		$this->should_flush_empty = false;

		return $length;
	}

	/**
	 * Delete a file
	 *
	 * @since   1.0.0
	 * @access  public
	 * @param   string  $path
	 *
	 * @return  bool    True if success. False on failure
	 */
	public function unlink( $path ) {
		$this->debug( sprintf( 'unlink =>  %s', $path ), true );

		$path = $this->trim_path( $path );

		try {
			$result = $this->client->delete_file( $path );

			if ( is_wp_error( $result ) ) {
				trigger_error(
					sprintf( 'unlink failed for %s with error: %s #vip-go-streams', esc_html( $path ), esc_html( $result->get_error_message() ) ),
					E_USER_WARNING
				);

				return false;
			}

			$this->close_handler();

			return true;
		} catch ( \Exception $e ) {
			trigger_error(
				sprintf( 'unlink failed for %s with error: %s #vip-go-streams', esc_html( $path ), esc_html( $e->getMessage() ) ),
				E_USER_WARNING
			);

			return false;
		}
	}

	/**
	 * Get file stats
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return  array   The file statistics
	 */
	public function stream_stat() {
		$this->debug( sprintf( 'stream_stat =>  %s + %s', $this->path, $this->uri ) );

		return fstat( $this->file );
	}

	/**
	 * Get file stats by path
	 *
	 * Use by functions like is_dir, file_exists etc.
	 * See: http://php.net/manual/en/streamwrapper.url-stat.php
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   string      $path
	 * @param   int         $flags
	 *
	 * @return  array|bool  The file statistics or false if failed
	 */
	public function url_stat( $path, $flags ) {
		$this->debug( sprintf( 'url_stat =>  %s + %s', $path, $flags ) );

		$path = $this->trim_path( $path );

		// Default stats
		$stats = array(
			0         => 0,
			'dev'     => 0,
			1         => 0,
			'ino'     => 0,
			2         => 16895,
			'mode'    => 16895,
			3         => 0,
			'nlink'   => 0,
			4         => 0,
			'uid'     => 0,
			5         => 0,
			'gid'     => 0,
			6         => -1,
			'rdev'    => -1,
			7         => 0,
			'size'    => 0,
			8         => 0,
			'atime'   => 0,
			9         => 0,
			'mtime'   => 0,
			10        => 0,
			'ctime'   => 0,
			11        => -1,
			'blksize' => -1,
			12        => -1,
			'blocks'  => -1,
		);

		$extension = pathinfo( $path, PATHINFO_EXTENSION );
		/**
		 * If the file is actually just a path to a directory
		 * then return it as always existing. This is to work
		 * around wp_upload_dir doing file_exists checks on
		 * the uploads directory on every page load.
		 *
		 * Added by Joe Hoyle
		 *
		 * Hanif's note: Copied from humanmade's S3 plugin
		 *              https://github.com/humanmade/S3-Uploads
		 */
		if ( ! $extension ) {
			return $stats;
		}

		try {
			$info   = array();
			$result = $this->client->is_file( $path, $info );
			if ( is_wp_error( $result ) ) {
				trigger_error(
					sprintf( 'url_stat failed for %s with error: %s #vip-go-streams', esc_html( $path ), esc_html( $result->get_error_message() ) ),
					E_USER_WARNING
				);

				return false;
			}
			if ( ! $result ) {
				// File not found
				return false;
			}

			// Here we should parse the meta data into the statistics array
			// and then combine with data from `is_file` API
			// see: http://php.net/manual/en/function.stat.php
			$stats['mode']  = 33206; // read+write permissions
			$stats['size']  = (int) $info['size'];
			$stats['atime'] = (int) $info['mtime'];
			$stats['mtime'] = (int) $info['mtime'];
			$stats['ctime'] = (int) $info['mtime'];
			$stats[2]       = $stats['mode'];
			$stats[7]       = $stats['size'];
			$stats[8]       = $stats['atime'];
			$stats[9]       = $stats['mtime'];
			$stats[10]      = $stats['ctime'];

			return $stats;
		} catch ( \Exception $e ) {
			trigger_error(
				sprintf( 'url_stat failed for %s with error: %s #vip-go-streams', esc_html( $path ), esc_html( $e->getMessage() ) ),
				E_USER_WARNING
			);

			return false;
		}
	}

	/**
	 * This method is called in response to fseek() to determine the current position.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return  bool|int    Returns current position or false on failure
	 */
	public function stream_tell() {
		$this->debug( sprintf( 'stream_tell =>  %s + %s', $this->path, $this->uri ) );

		return $this->file ? ftell( $this->file ) : false;
	}

	/**
	 * Called in response to rename() to rename a file or directory.
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   string  $path_from  Path to file to rename
	 * @param   string  $path_to    New path to the file
	 *
	 * @return  bool    True on successful rename
	 */
	public function rename( $path_from, $path_to ) {
		$this->debug( sprintf( 'rename =>  %s + %s', $path_from, $path_to ) );

		if ( $path_from === $path_to ) {
			// from and to path are identical so do nothing
			return true;
		}

		$path_from = $this->trim_path( $path_from );
		$path_to   = $this->trim_path( $path_to );

		try {
			// Get original file first
			// Note: Subooptimal. Should figure out a way to do this without downloading the file as this could
			//       get really inefficient with large files
			$result = $this->client->get_file( $path_from );
			if ( is_wp_error( $result ) ) {
				trigger_error(
					sprintf( 'rename/get_file/from failed for %s with error: %s #vip-go-streams', esc_html( $path_from ), esc_html( $result->get_error_message() ) ),
					E_USER_WARNING
				);

				return false;
			}

			// Convert to actual file to upload to new path
			$file      = fopen( $result, 'r' );          // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
			$meta      = stream_get_meta_data( $file );
			$file_path = $meta['uri'];

			// Upload to file service
			$result = $this->client->upload_file( $file_path, $path_to );
			if ( is_wp_error( $result ) ) {
				trigger_error(
					sprintf( 'rename/upload_file/to failed for %s with error: %s #vip-go-streams', esc_html( $file_path ), esc_html( $result->get_error_message() ) ),
					E_USER_WARNING
				);

				return false;
			}

			// Delete old file
			$result = $this->client->delete_file( $path_from );
			if ( is_wp_error( $result ) ) {
				trigger_error(
					sprintf( 'rename/delete_file/from failed for %s with error: %s #vip-go-streams', esc_html( $path_from ), esc_html( $result->get_error_message() ) ),
					E_USER_WARNING
				);

				return false;
			}

			return true;
		} catch ( \Exception $e ) {
			trigger_error(
				sprintf( 'rename/delete_file/from failed for %s with error: %s #vip-go-streams', esc_html( $path_from ), esc_html( $e->getMessage() ) ),
				E_USER_WARNING
			);

			return false;
		}
	}

	/**
	 * Called in response to mkdir()
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   string  $path
	 * @param   int     $mode
	 * @param   in      $options
	 *
	 * @return  bool
	 */
	public function mkdir( $path, $mode, $options ) {
		$this->debug( sprintf( 'mkdir =>  %s + %s + %s', $path, $mode, $options ) );

		// Currently, it will always return true as directories are automatically created on the Filesystem API
		return true;
	}

	/**
	 * Set metadata on a stream
	 *
	 * @link http://php.net/manual/en/streamwrapper.stream-metadata.php
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   string  $path
	 * @param   int     $option
	 * @param   mixed   $value
	 *
	 * @return  bool
	 */
	public function stream_metadata( $path, $option, $value ) {
		$this->debug( sprintf( 'stream_metadata =>  %s + %s + %s', $path, $option, $value ) );

		return false;
	}

	/**
	 * Called in response to stream_select()
	 *
	 * @link http://php.net/manual/en/streamwrapper.stream-castt.php
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @param   int             $cast_as
	 *
	 * @return  resource|bool
	 */
	public function stream_cast( $cast_as ) {
		$this->debug( sprintf( 'stream_cast =>  %s + %s + %s', $cast_as, $this->path, $this->uri ) );

		if ( ! is_null( $this->file ) ) {
			return $this->file;
		}

		return false;
	}

	/**
	 * Write file to a temporary resource handler
	 *
	 * @since   1.0.0
	 * @access  protected
	 *
	 * @param   string     $data   The file content to be written
	 * @param   string     $mode   The fopen mode
	 *
	 * @return  resource   Returns resource or false on write error
	 */
	protected function string_to_resource( $data, $mode ) {
		// Create a temporary file
		$tmp_handler = tmpfile();
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite
		if ( false === fwrite( $tmp_handler, $data ) ) {
			trigger_error( 'Error creating temporary resource #vip-go-streams', E_USER_ERROR );
		}

		switch ( $mode ) {
			case 'a':
				// Make sure pointer is at end of file for appends
				fseek( $tmp_handler, 0, SEEK_END );
				break;
			default:
				// Need to rewind file pointer as fwrite moves it to EOF
				rewind( $tmp_handler );
		}

		return $tmp_handler;
	}

	/**
	 * Closes the open file handler
	 *
	 * @since   1.0.0
	 * @access  protected
	 *
	 * @return  bool        True on success. False on failure.
	 */
	protected function close_handler() {
		if ( ! $this->file ) {
			return true;
		}

		$result = fclose( $this->file );

		if ( $result ) {
			$this->file = null;
			$this->path = null;
			$this->uri  = null;
			$this->mode = null;
		}

		return $result;
	}

	/**
	 * Converted the protocol file path into something the File Service
	 * API client can use
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @param   string      $path       Original protocol path
	 *
	 * @return  string      Modified path
	 */
	protected function trim_path( $path ) {
		return ltrim( $path, 'vip:/\\' );
	}

	/**
	* Validates the provided stream arguments for fopen.
	*
	* @since   1.0.0
	* @access  private
	* @param   string    $path   Path to file
	* @param   string    $mode   fopen mode
	*
	* @return  bool
	*/
	public function validate( $path, $mode ) {
		if ( ! in_array( $mode, self::ALLOWED_MODES, true ) ) {
			trigger_error( esc_html( "Mode not supported: { $mode }. Use one 'r', 'w', 'a', or 'x'." ) );

			return false;
		}

		// When using mode "x" validate if the file exists before attempting
		// to read
		if ( 'x' === $mode ) {
			try {
				$info   = array();
				$result = $this->client->is_file( $path, $info );
				if ( is_wp_error( $result ) ) {
					trigger_error(
						sprintf(
							'fopen mode validation failed for mode %s on path %s with error: %s #vip-go-streams',
							esc_html( $mode ),
							esc_html( $path ),
							esc_html( $result->get_error_message() )
						),
						E_USER_WARNING
					);

					return false;
				}

				if ( $result ) {
					// File already exists
					trigger_error(
						sprintf( 'File %s already exists. Cannot use mode %s', esc_html( $path ), esc_html( $mode ) )
					);

					return false;
				}

				return true;
			} catch ( \Exception $e ) {
				trigger_error(
					sprintf(
						'fopen mode validation failed for mode %s on path %s with error: %s #vip-go-streams',
						esc_html( $mode ),
						esc_html( $path ),
						esc_html( $e->getMessage() )
					),
					E_USER_WARNING
				);

				return false;
			}
		}

		return true;
	}

	/**
	 * Log debug message
	 *
	 * @since   1.0.0
	 * @access  protected
	 * @param   string    $message  Debug message to be logged
	 * @param   bool      $force Whether to force debug
	 */
	protected function debug( $message, $force = false ) {
		if ( ! ( $this->debug_mode || $force ) ) {
			return;
		}

		$trace = $this->backtrace_fmt();

		\Automattic\VIP\Logstash\log2logstash(
			[
				'severity' => 'info',
				'feature'  => 'stream_wrapper_audit_' . $trace[1]['function'],
				'message'  => "File op {$trace[1]['function']}: " . $message,
				'extra'    => [
					'trace' => $trace,
				],
			]
		);
	}

	/**
	 * Format the debug backtrace to be a bit more readable .
	 *
	 * @return array
	 */
	private function backtrace_fmt() {
		$trace = debug_backtrace( 0, 30 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		// Discard current frame.
		unset( $trace[0] );
		foreach ( $trace as &$frame ) {
			if ( isset( $frame['file'] ) ) {
				$frame['file'] = str_replace( ABSPATH, '', $frame['file'] ) . ':' . $frame['line'];
			}
			unset( $frame['line'] );
		}

		return array_values( $trace );
	}
}
