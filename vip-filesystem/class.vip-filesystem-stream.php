<?php
/**
 * Created by PhpStorm.
 * User: hanifn
 * Date: 7/11/18
 * Time: 10:36 AM
 */

namespace Automattic\VIP\Filesystem;

use Automattic\VIP\Files\API_Client;
use function Automattic\VIP\Files\new_api_client;

class Vip_Filesystem_Stream {

	/**
	 * Default protocol
	 */
	const DEFAULT_PROTOCOL = 'vip';

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
	 * @access  protected
	 * @var     API_Client  VIP Files API Client
	 */
	protected $client;

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
	 * Vip_Filesystem_Stream constructor.
	 *
	 * @param API_Client $client
	 * @param string $protocol
	 */
	public function __construct( API_Client $client, $protocol = null ) {
		if ( is_null( $client ) ) {
			$this->client = new_api_client();
		} else {
			$this->client = $client;
		}

		$this->protocol = $protocol ?: self::DEFAULT_PROTOCOL;
	}

	/**
	 *  Register the Stream.
	 *
	 * Will unregister stream first if it's already registered
	 *
	 * @since   1.0.0
	 * @access  public
	 *
	 * @return  bool    TRUE if success, FALSE if failure
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
	 * @return  bool    True on success or FALSE on failure
	 */
	public function stream_open( $path, $mode, $options, &$opened_path ) {
		$result = $this->client->get_file( $path );

		print_r( 'Opening file: ' . $path );
		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			// TODO: Should log this error
			print_r( 'Error opening stream: '. $path );
			return false;
		}

		// Converts file contents into stream resource
		$result = $this->string_to_resource( $result );

		// Get meta data
		$meta = stream_get_meta_data( $result );
		$this->seekable = $meta['seekable'];
		$this->uri = $meta['uri'];

		$this->file = $result;
		$this->path = $path;

		return true;
	}

	/**
	 * Close a file
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public function stream_close() {
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
		$string = fread( $this->file, $count );
		if ( false === $string ) {
			// TODO: Throw or log an error here
			return '';
		}

		return $string;
	}

	/**
	 * Flush to a file
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public function stream_flush() {

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
		if ( ! $this->seekable ) {
			// File not seekable
			// TODO: Log an error?
			return FALSE;
		}

		$result = fseek( $this->file, $offset, $whence );

		if ( -1 === $result ) {
			// Seek failed
			// TODO: log error
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Write to a file
	 *
	 * @since   1.0.0
	 * @accesss public
	 *
	 * @param   string      $data   The data to be written
	 *
	 * @return  int|bool    Number of bytes written or FALSE on error
	 */
	public function stream_write( $data ) {
		$length = fwrite( $this->file, $data );

		if ( FALSE === $length ) {
			// TODO: Log this error
			return FALSE;
		}

		$result = $this->client
			->upload_file( $this->uri, $this->path );
		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			// TODO: Log this error
			print_r( 'Error uploading file: '. $this->path );
			return FALSE;
		}

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
		$result = $this->client->delete_file( $path );

		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			// TODO: Log this error
			print_r( 'Error deleting file: '. $path );
			return FALSE;
		}

		$this->file = null;

		return TRUE;
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
	 * @param   string  $path
	 * @param   int     $flags
	 *
	 * @return  array   The file statistics
	 */
	public function url_stat( $path, $flags) {
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
		 */
		if ( ! $extension ) {
			return array (
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
		}

		$result = $this->client->get_file( $path );
		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			// TODO: Log this error
			print_r( 'Error on url stat: '. $path );
			return [];
		}

		$tmp_handler = $this->string_to_resource( $result );

		return fstat( $tmp_handler );
	}

	/**
	 * Write file to a temporary resource handler
	 *
	 * @since   1.0.0
	 * @access  protected
	 *
	 * @param   string          $data   The file content to be written
	 *
	 * @return  bool|resource   Returns resource or FALSE on write error
	 */
	protected function string_to_resource( $data ) {
		// Create a temporary file in `tmp` directory using `tmpfile()`
		$tmp_handler = tmpfile();
		if (! fwrite( $tmp_handler, $data ) ) {
			return FALSE;
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
		$result = fclose( $this->file );

		if ( $result ) {
			$this->file = null;
			$this->path = null;
		}

		return $result;
	}
}