<?php
/**
 * General utilities class.
 *
 * @package query-monitor
 */

if ( ! class_exists( 'QM_Util' ) ) {
class QM_Util {

	protected static $file_components = array();
	protected static $file_dirs       = array();
	protected static $abspath         = null;
	protected static $contentpath     = null;
	protected static $sort_field      = null;

	private function __construct() {}

	public static function convert_hr_to_bytes( $size ) {

		# Annoyingly, wp_convert_hr_to_bytes() is defined in a file that's only
		# loaded in the admin area, so we'll use our own version.
		# See also http://core.trac.wordpress.org/ticket/17725

		$bytes = (float) $size;

		if ( $bytes ) {
			$last = strtolower( substr( $size, -1 ) );
			$pos = strpos( ' kmg', $last, 1 );
			if ( $pos ) {
				$bytes *= pow( 1024, $pos );
			}
			$bytes = round( $bytes );
		}

		return $bytes;

	}

	public static function standard_dir( $dir, $path_replace = null ) {

		$dir = self::normalize_path( $dir );

		if ( is_string( $path_replace ) ) {
			if ( ! self::$abspath ) {
				self::$abspath     = self::normalize_path( ABSPATH );
				self::$contentpath = self::normalize_path( dirname( WP_CONTENT_DIR ) . '/' );
			}
			$dir = str_replace( array(
				self::$abspath,
				self::$contentpath,
			), $path_replace, $dir );
		}

		return $dir;

	}

	public static function normalize_path( $path ) {
		if ( function_exists( 'wp_normalize_path' ) ) {
			$path = wp_normalize_path( $path );
		} else {
			$path = str_replace( '\\', '/', $path );
			$path = str_replace( '//', '/', $path );
		}

		return $path;
	}

	public static function get_file_dirs() {
		if ( empty( self::$file_dirs ) ) {
			self::$file_dirs['plugin']     = self::standard_dir( WP_PLUGIN_DIR );
			self::$file_dirs['mu-vendor']  = self::standard_dir( WPMU_PLUGIN_DIR . '/vendor' );
			self::$file_dirs['go-plugin']  = self::standard_dir( WPMU_PLUGIN_DIR . '/shared-plugins' );
			self::$file_dirs['mu-plugin']  = self::standard_dir( WPMU_PLUGIN_DIR );
			self::$file_dirs['vip-plugin'] = self::standard_dir( get_theme_root() . '/vip/plugins' );

			if ( defined( 'WPCOM_VIP_CLIENT_MU_PLUGIN_DIR' ) ) {
				self::$file_dirs['vip-client-mu-plugin'] = self::standard_dir( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR );
			}

			self::$file_dirs['theme']      = null;
			self::$file_dirs['stylesheet'] = self::standard_dir( get_stylesheet_directory() );
			self::$file_dirs['template']   = self::standard_dir( get_template_directory() );
			self::$file_dirs['other']      = self::standard_dir( WP_CONTENT_DIR );
			self::$file_dirs['core']       = self::standard_dir( ABSPATH );
			self::$file_dirs['unknown']    = null;
		}
		return self::$file_dirs;
	}

	public static function get_file_component( $file ) {

		# @TODO turn this into a class (eg QM_File_Component)

		$file = self::standard_dir( $file );

		if ( isset( self::$file_components[ $file ] ) ) {
			return self::$file_components[ $file ];
		}

		foreach ( self::get_file_dirs() as $type => $dir ) {
			// this slash makes paths such as plugins-mu match mu-plugin not plugin
			if ( $dir && ( 0 === strpos( $file, trailingslashit( $dir ) ) ) ) {
				break;
			}
		}

		$context = $type;

		switch ( $type ) {
			case 'plugin':
			case 'mu-plugin':
			case 'mu-vendor':
				$plug = str_replace( '/vendor/', '/', $file );
				$plug = plugin_basename( $plug );
				if ( strpos( $plug, '/' ) ) {
					$plug = explode( '/', $plug );
					$plug = reset( $plug );
				} else {
					$plug = basename( $plug );
				}
				if ( 'plugin' !== $type ) {
					/* translators: %s: Plugin name */
					$name = sprintf( __( 'MU Plugin: %s', 'query-monitor' ), $plug );
				} else {
					/* translators: %s: Plugin name */
					$name = sprintf( __( 'Plugin: %s', 'query-monitor' ), $plug );
				}
				$context = $plug;
				break;
			case 'go-plugin':
			case 'vip-plugin':
			case 'vip-client-mu-plugin':
				$plug = str_replace( self::$file_dirs[ $type ], '', $file );
				$plug = trim( $plug, '/' );
				if ( strpos( $plug, '/' ) ) {
					$plug = explode( '/', $plug );
					$plug = reset( $plug );
				} else {
					$plug = basename( $plug );
				}
				if ( 'vip-client-mu-plugin' === $type ) {
					/* translators: %s: Plugin name */
					$name = sprintf( __( 'VIP Client MU Plugin: %s', 'query-monitor' ), $plug );
				} else {
					/* translators: %s: Plugin name */
					$name = sprintf( __( 'VIP Plugin: %s', 'query-monitor' ), $plug );
				}
				$context = $plug;
				break;
			case 'stylesheet':
				if ( is_child_theme() ) {
					$name = __( 'Child Theme', 'query-monitor' );
				} else {
					$name = __( 'Theme', 'query-monitor' );
				}
				$type = 'theme';
				break;
			case 'template':
				$name = __( 'Parent Theme', 'query-monitor' );
				$type = 'theme';
				break;
			case 'other':
				// Anything else that's within the content directory should appear as
				// `wp-content/{dir}` or `wp-content/{file}`
				$name    = self::standard_dir( $file );
				$name    = str_replace( dirname( self::$file_dirs['other'] ), '', $name );
				$parts   = explode( '/', trim( $name, '/' ) );
				$name    = $parts[0] . '/' . $parts[1];
				$context = $file;
				break;
			case 'core':
				$name = __( 'Core', 'query-monitor' );
				break;
			case 'unknown':
			default:
				$name = __( 'Unknown', 'query-monitor' );
				break;
		}

		return self::$file_components[ $file ] = (object) compact( 'type', 'name', 'context' );

	}

	public static function populate_callback( array $callback ) {

		if ( is_string( $callback['function'] ) and ( false !== strpos( $callback['function'], '::' ) ) ) {
			$callback['function'] = explode( '::', $callback['function'] );
		}

		try {

			if ( is_array( $callback['function'] ) ) {
				if ( is_object( $callback['function'][0] ) ) {
					$class  = get_class( $callback['function'][0] );
					$access = '->';
				} else {
					$class  = $callback['function'][0];
					$access = '::';
				}

				$callback['name'] = QM_Util::shorten_fqn( $class . $access . $callback['function'][1] ) . '()';
				$ref = new ReflectionMethod( $class, $callback['function'][1] );
			} elseif ( is_object( $callback['function'] ) ) {
				if ( is_a( $callback['function'], 'Closure' ) ) {
					$ref  = new ReflectionFunction( $callback['function'] );
					$file = QM_Util::standard_dir( $ref->getFileName(), '' );
					if ( 0 === strpos( $file, '/' ) ) {
						$file = basename( $ref->getFileName() );
					}
					/* translators: 1: Line number, 2: File name */
					$callback['name'] = sprintf( __( 'Closure on line %1$d of %2$s', 'query-monitor' ), $ref->getStartLine(), $file );
				} else {
					// the object should have a __invoke() method
					$class = get_class( $callback['function'] );
					$callback['name'] = QM_Util::shorten_fqn( $class ) . '->__invoke()';
					$ref = new ReflectionMethod( $class, '__invoke' );
				}
			} else {
				$callback['name'] = QM_Util::shorten_fqn( $callback['function'] ) . '()';
				$ref = new ReflectionFunction( $callback['function'] );
			}

			$callback['file'] = $ref->getFileName();
			$callback['line'] = $ref->getStartLine();

			// https://github.com/facebook/hhvm/issues/5856
			$name = trim( $ref->getName() );

			if ( '__lambda_func' === $name || 0 === strpos( $name, 'lambda_' ) ) {
				if ( preg_match( '|(?P<file>.*)\((?P<line>[0-9]+)\)|', $callback['file'], $matches ) ) {
					$callback['file'] = $matches['file'];
					$callback['line'] = $matches['line'];
					$file = trim( QM_Util::standard_dir( $callback['file'], '' ), '/' );
					/* translators: 1: Line number, 2: File name */
					$callback['name'] = sprintf( __( 'Anonymous function on line %1$d of %2$s', 'query-monitor' ), $callback['line'], $file );
				} else {
					// https://github.com/facebook/hhvm/issues/5807
					unset( $callback['line'], $callback['file'] );
					$callback['name'] = $name . '()';
					$callback['error'] = new WP_Error( 'unknown_lambda', __( 'Unable to determine source of lambda function', 'query-monitor' ) );
				}
			}

			if ( ! empty( $callback['file'] ) ) {
				$callback['component'] = self::get_file_component( $callback['file'] );
			} else {
				$callback['component'] = (object) array(
					'type'    => 'php',
					'name'    => 'PHP',
					'context' => '',
				);
			}
		} catch ( ReflectionException $e ) {

			$callback['error'] = new WP_Error( 'reflection_exception', $e->getMessage() );

		}

		return $callback;

	}

	public static function is_ajax() {
		if ( defined( 'DOING_AJAX' ) and DOING_AJAX ) {
			return true;
		}
		return false;
	}

	public static function is_async() {
		if ( self::is_ajax() ) {
			return true;
		}
		if ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 'xmlhttprequest' === strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) ) { // @codingStandardsIgnoreLine
			return true;
		}
		return false;
	}

	public static function get_admins() {
		if ( is_multisite() ) {
			return false;
		} else {
			return get_role( 'administrator' );
		}
	}

	public static function is_multi_network() {
		global $wpdb;

		if ( function_exists( 'is_multi_network' ) ) {
			return is_multi_network();
		}

		if ( ! is_multisite() ) {
			return false;
		}

		// @codingStandardsIgnoreStart
		$num_sites = $wpdb->get_var( "
			SELECT COUNT(*)
			FROM {$wpdb->site}
		" );
		// @codingStandardsIgnoreEnd

		return ( $num_sites > 1 );
	}

	public static function get_client_version( $client ) {

		$client = intval( $client );

		$hello = $client % 10000;

		$major = intval( floor( $client / 10000 ) );
		$minor = intval( floor( $hello / 100 ) );
		$patch = intval( $hello % 100 );

		return compact( 'major', 'minor', 'patch' );

	}

	public static function get_query_type( $sql ) {
		$sql = $type = trim( $sql );

		if ( 0 === strpos( $sql, '/*' ) ) {
			// Strip out leading comments such as `/*NO_SELECT_FOUND_ROWS*/` before calculating the query type
			$type = preg_replace( '|^/\*[^\*/]+\*/|', '', $sql );
		}

		$type = preg_split( '/\b/', trim( $type ), 2, PREG_SPLIT_NO_EMPTY );
		$type = strtoupper( $type[0] );

		return $type;
	}

	public static function display_variable( $value ) {
		if ( is_string( $value ) ) {
			return $value;
		} elseif ( is_bool( $value ) ) {
			return ( $value ) ? 'true' : 'false';
		} elseif ( is_scalar( $value ) ) {
			return $value;
		} elseif ( is_object( $value ) ) {
			$class = get_class( $value );

			switch ( true ) {

				case ( $value instanceof WP_Post ):
				case ( $value instanceof WP_User ):
					return sprintf( '%s (ID: %s)', $class, $value->ID );
					break;

				case ( $value instanceof WP_Term ):
					return sprintf( '%s (term_id: %s)', $class, $value->term_id );
					break;

				case ( $value instanceof WP_Comment ):
					return sprintf( '%s (comment_ID: %s)', $class, $value->comment_ID );
					break;

				case ( $value instanceof WP_Error ):
					return sprintf( '%s (%s)', $class, $value->get_error_code() );
					break;

				case ( $value instanceof WP_Role ):
				case ( $value instanceof WP_Post_Type ):
				case ( $value instanceof WP_Taxonomy ):
					return sprintf( '%s (%s)', $class, $value->name );
					break;

				case ( $value instanceof WP_Network ):
					return sprintf( '%s (id: %s)', $class, $value->id );
					break;

				case ( $value instanceof WP_Site ):
					return sprintf( '%s (blog_id: %s)', $class, $value->blog_id );
					break;

				case ( $value instanceof WP_Theme ):
					return sprintf( '%s (%s)', $class, $value->get_stylesheet() );
					break;

				default:
					return $class;
					break;

			}
		} else {
			return gettype( $value );
		}
	}

	/**
	 * Shortens a fully qualified name to reduce the length of the names of long namespaced symbols.
	 *
	 * This initialises portions that do not form the first or last portion of the name. For example:
	 *
	 *     Inpsyde\Wonolog\HookListener\HookListenersRegistry->hook_callback()
	 *
	 * becomes:
	 *
	 *     Inpsyde\W\H\HookListenersRegistry->hook_callback()
	 *
	 * @param string $fqn A fully qualified name.
	 * @return string A shortened version of the name.
	 */
	public static function shorten_fqn( $fqn ) {
		return preg_replace_callback( '#\\\\[a-zA-Z0-9_\\\\]{4,}\\\\#', function( array $matches ) {
			preg_match_all( '#\\\\([a-zA-Z0-9_])#', $matches[0], $m );
			return '\\' . implode( '\\', $m[1] ) . '\\';
		}, $fqn );
	}

	public static function sort( array &$array, $field ) {
		self::$sort_field = $field;
		usort( $array, array( __CLASS__, '_sort' ) );
	}

	public static function rsort( array &$array, $field ) {
		self::$sort_field = $field;
		usort( $array, array( __CLASS__, '_rsort' ) );
	}

	private static function _rsort( $a, $b ) {
		$field = self::$sort_field;

		if ( $a[ $field ] === $b[ $field ] ) {
			return 0;
		} else {
			return ( $a[ $field ] > $b[ $field ] ) ? -1 : 1;
		}
	}

	private static function _sort( $a, $b ) {
		$field = self::$sort_field;

		if ( $a[ $field ] === $b[ $field ] ) {
			return 0;
		} else {
			return ( $a[ $field ] > $b[ $field ] ) ? 1 : -1;
		}
	}

}
}
