<?php
/**
 * Endpoints: Metadata endpoint abstract class
 *
 * @package Parsely
 * @since   3.2.0
 */

declare(strict_types=1);

namespace Parsely\Endpoints;

use Parsely\Parsely;
use Parsely\UI\Metadata_Renderer;

/**
 * Metadata endpoint classes are expected to implement the remaining functions
 * of the class.
 *
 * @since 3.2.0
 */
abstract class Metadata_Endpoint {
	protected const FIELD_NAME = 'parsely';

	/**
	 * Instance of Parsely class.
	 *
	 * @var Parsely
	 */
	protected $parsely;

	/**
	 * Constructor.
	 *
	 * @param Parsely $parsely Instance of Parsely class.
	 */
	public function __construct( Parsely $parsely ) {
		$this->parsely = $parsely;
	}

	/**
	 * Starts up the class and enqueues necessary actions.
	 *
	 * @since 3.2.0
	 */
	abstract public function run(): void;

	/**
	 * Registers the metadata fields on the appropriate resource types.
	 *
	 * @since 3.2.0
	 */
	abstract public function register_meta(): void;

	/**
	 * Returns the metadata in string format.
	 *
	 * @since 3.2.0
	 *
	 * @param string $meta_type `json_ld` or `repeated_metas`.
	 * @return string The metadata as HTML code.
	 */
	public function get_rendered_meta( string $meta_type ): string {
		$metadata_renderer = new Metadata_Renderer( $this->parsely );

		ob_start();
		$metadata_renderer->render_metadata( $meta_type );
		$out = ob_get_clean();

		if ( false === $out ) {
			return '';
		}

		return trim( $out );
	}
}
