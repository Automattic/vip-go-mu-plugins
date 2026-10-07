<?php

namespace Automattic\VIP\Files;

use function Automattic\Test\Utils\get_static_property_as_public;

require_once __DIR__ . '/../../files/class-vip-filesystem-local-stream-wrapper.php';

/**
 * Registers the vip:// stream wrapper with a test API client.
 *
 * After each test, the wrapper registration, default client and local file routes are restored
 * to what they were before the first register_vip_stream_wrapper() call.
 */
trait VIP_Stream_Wrapper_Fixture {
	/** @var array|null State saved by the first register_vip_stream_wrapper() call of a test. */
	private $vip_stream_wrapper_state = null;

	private function register_vip_stream_wrapper( API_Client $client ): VIP_Filesystem_Local_Stream_Wrapper {
		if ( null === $this->vip_stream_wrapper_state ) {
			$routes = [];
			foreach ( [ 'local_files_map', 'local_file_patterns', 'local_file_names' ] as $name ) {
				$routes[ $name ] = get_static_property_as_public( VIP_Filesystem_Local_Stream_Wrapper::class, $name )->getValue();
			}

			$this->vip_stream_wrapper_state = [
				'routes'         => $routes,
				'default_client' => VIP_Filesystem_Local_Stream_Wrapper::$default_client,
				'registered'     => in_array( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL, stream_get_wrappers(), true ),
			];
		}

		$stream_wrapper = new VIP_Filesystem_Local_Stream_Wrapper( $client );
		$stream_wrapper->register();

		// Instances created by PHP for vip:// operations use the default client.
		VIP_Filesystem_Local_Stream_Wrapper::$default_client = $client;

		return $stream_wrapper;
	}

	/**
	 * @after
	 */
	public function restore_vip_stream_wrapper(): void {
		if ( null === $this->vip_stream_wrapper_state ) {
			return;
		}

		foreach ( $this->vip_stream_wrapper_state['routes'] as $name => $value ) {
			get_static_property_as_public( VIP_Filesystem_Local_Stream_Wrapper::class, $name )->setValue( null, $value );
		}

		VIP_Filesystem_Local_Stream_Wrapper::$default_client = $this->vip_stream_wrapper_state['default_client'];

		$registered = in_array( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL, stream_get_wrappers(), true );
		if ( $registered && ! $this->vip_stream_wrapper_state['registered'] ) {
			stream_wrapper_unregister( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL );
		} elseif ( ! $registered && $this->vip_stream_wrapper_state['registered'] ) {
			stream_wrapper_register( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL, VIP_Filesystem_Local_Stream_Wrapper::class, STREAM_IS_URL );
		}

		$this->vip_stream_wrapper_state = null;
	}
}
