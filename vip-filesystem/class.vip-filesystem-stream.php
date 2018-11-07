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

		if ( $result instanceof \WP_Error ) {
			// TODO: Should log this error
			return false;
		}

		// Converts file contents into stream resource

		$this->file = $result;

		return true;
	}

	/**
	 * Close a file
	 *
	 * @since   1.0.0
	 * @access  public
	 */
	public function stream_close() {
		$this->file = null;
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
}