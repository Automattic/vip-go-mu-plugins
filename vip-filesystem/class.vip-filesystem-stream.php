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
	public function __construct( API_Client $client = null, $protocol = null ) {
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
		$path = $this->trim_path( $path );

		if ( $this->client->is_file( $path ) ) {
			$result = $this->client->get_file( $path );

			if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
				trigger_error( $result->get_error_message(), E_USER_WARNING );
				return false;
			}

			// Converts file contents into stream resource
			$file = $this->string_to_resource( $result );
		} else {
			// File doesn't exist on File service so create new file
			$file = $this->string_to_resource( '' );
		}


		// Get meta data
		$meta = stream_get_meta_data( $file );
		$this->seekable = $meta['seekable'];
		$this->uri = $meta['uri'];

		$this->file = $file;
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
			trigger_error( 'Error reading from file: ' . $this->path,
				E_USER_WARNING );
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
		return fflush( $this->file );
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
			trigger_error( 'File not seekable: ' . $this->path,
				E_USER_WARNING );
			return FALSE;
		}

		$result = fseek( $this->file, $offset, $whence );

		if ( -1 === $result ) {
			// Seek failed
			trigger_error( 'Error seeking on file: ' . $this->path,
				E_USER_WARNING );
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
			trigger_error( 'Error writing to file: ' . $this->path,
				E_USER_WARNING );
			return FALSE;
		}

		// Upload to file service
		$result = $this->client
			->upload_file( $this->uri, $this->path );
		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			trigger_error( $result->get_error_message(), E_USER_WARNING );
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
		$path = $this->trim_path( $path );
		$result = $this->client->delete_file( $path );

		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			trigger_error( $result->get_error_message(), E_USER_WARNING );
			return FALSE;
		}

		$this->close_handler();

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
	 * @param   string      $path
	 * @param   int         $flags
	 *
	 * @return  array|bool  The file statistics or false if failed
	 */
	public function url_stat( $path, $flags ) {
		$path = $this->trim_path( $path );

		// Default stats
		$stats = array (
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
		 */
		if ( ! $extension ) {
			return $stats;
		}

		$info = array();
		$result = $this->client->is_file( $path, $info );
		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			trigger_error( $result->get_error_message(), E_USER_WARNING );
			return false;
		}
		if ( ! $result ) {
			// File not found
			return false;
		}

		// Here we should parse the meta data into the statistics array
		// and then combine with data from `is_file` API
		// see: http://php.net/manual/en/function.stat.php
		$stats[2] = $stats['mode'] = 33206; // read+write permissions
		$stats[7] = $stats['size'] = (int) $info['size'];
		$stats[8] = $stats['atime'] = (int) $info['mtime'];
		$stats[9] = $stats['mtime'] = (int) $info['mtime'];
		$stats[10] = $stats['ctime'] = (int) $info['mtime'];

		return $stats;
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
		$path_from = $this->trim_path( $path_from );
		$path_to = $this->trim_path( $path_to );

		// Get original file first
		// Note: Subooptimal. Should figure out a way to do this without downloading the file as this could
		//       get really inefficient with large files
		$result = $this->client->get_file( $path_from );
		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			trigger_error( $result->get_error_message(), E_USER_WARNING );
			return FALSE;
		}

		// Convert to actual file to upload to new path
		$file = $this->string_to_resource( $result );
		$meta = stream_get_meta_data( $file );
		$filePath = $meta['uri'];

		// Upload to file service
		$result = $this->client->upload_file( $filePath, $path_to );
		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			trigger_error( $result->get_error_message(), E_USER_WARNING );
			return FALSE;
		}

		// Delete old file
		$result = $this->client->delete_file( $path_from );
		if ( is_wp_error( $result ) || $result instanceof \WP_Error ) {
			trigger_error( $result->get_error_message(), E_USER_WARNING );
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Called in response to mkdir()
	 * Currently, it will always return true as directories are automatically created on the Filesystem API
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
		return TRUE;
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
		return false;
	}

	/**
	 * Write file to a temporary resource handler
	 *
	 * @since   1.0.0
	 * @access  protected
	 *
	 * @param   string     $data   The file content to be written
	 *
	 * @return  resource   Returns resource or FALSE on write error
	 */
	protected function string_to_resource( $data ) {
		// Create a temporary file
		$tmp_handler = tmpfile();
		if (false === fwrite( $tmp_handler, $data ) ) {
			trigger_error( "Error creating temporary resource for data:\n" . $data,
				E_USER_ERROR );
		}
		// Need to rewind file pointer as fwrite moves it to EOF
		rewind( $tmp_handler );

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
		if (! $this->file ) {
			return true;
		}

		$result = fclose( $this->file );

		if ( $result ) {
			$this->file = null;
			$this->path = null;
			$this->uri  = null;
		}

		return $result;
	}

	/**
	 * Converted the protocol file path into something the File Service
	 * API can use
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
}