<?php

/**
 * Setup the apcu hot cache
 *
 * exact string match lookups
 * $hc->add_passive( 'graylist-data', 'graylist-data:graylist-blog-1234', 10 );
 *
 * regular expression match lookups
 * $hc->add_passive_rx( 'themes', '/^(theme|headers)-[0-9a-f]{32}$/, 10 );
 *
 * hardcode values
 * $hc->hardcode( 'global', 'global:apcu-test-key', 'works' );
 */
function do_apcu_hot_cache_init( $hc ) {
	// Don't offload to apcu in WP_CLI
	// Every command runs as a new process, so the benefits
	// of apc are very minimal when combined with our regular
	// object cache dropin
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	// Cache the wpcomvip user ID
	$hc->add_passive_rx( 'userlogins', '/userlogins:wpcomvip$/', 300 );

	// Bypass APCu offloading in the REST API for some cases where there could
	// be concerns about stale data after writes
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {

		// Cache subsite information for 10 seconds unless we're in the
		// network admin, where we may be editing this information.
		if ( ! function_exists( 'is_network_admin' ) || ! is_network_admin() ) {
			$hc->add_passive_rx( 'sites', '/sites:\d+$/', 10 );
		}
	}
}

if ( ! class_exists( 'APC_Cache_Interceptor' ) ) :

	global $apc_cache_interceptor;

	/**
	 * Using this functionality has consequences and these
	 * consequences should be understood.
	 *
	 * The most important thing to understand, here, is that
	 * once a key is cached in a machine-local cache the
	 * local cache is instantly stale. You cannot, from this
	 * point forward, expect that this cached value for this
	 * key is ever fresh and up to date.  For example the
	 * value might actually have been deleted on another
	 * machine and you will still have this value until the
	 * local cache expires. As such...
	 *
	 * The first rule of using this mechanism is you must
	 * never local cache a value which cannot be stale.
	 *
	 * The second rule of using this mechanism is that this
	 * cached value must not be used in mutating remote
	 * cache or database values excepting that those values
	 * can accept this particular constituent piece being
	 * stale
	 */
	class APC_Cache_Interceptor {

		private $debugging        = false;
		private $debug_values     = false;
		private $debug_key_misses = false;
		private $debug_key_hits   = false;

		// specific group => key => model mapping
		public $specific_keys = array();

		// group => key regex mapping
		public $regex_keys = array();

		// I heard you like caching so I put a cache in your caches cache
		private $cache = array();

		public $callbacks = array(
			'key_hit'    => array(),
			'key_miss'   => array(),
			'cache_hit'  => array(),
			'cache_miss' => array(),
			'config'     => array(),
		);

		public function hardcode( $group, $key, $value ) {
			$this->add_passive( $group, $key, 86401 );
			$this->run_callbacks( 'config', array( 'hardcode', $group, $key, $value ) );
			$this->cache[ sprintf( '%s:%s', $group, $key ) ] = $value;
		}

		public function add_passive( $group, $key, $ttl ) {
			$this->run_callbacks( 'config', array( 'passive', $group, $key, $ttl ) );
			if ( ! array_key_exists( $group, $this->regex_keys ) ) {
				$this->specific_keys[ $group ] = array();
			}
			$this->specific_keys[ $group ][ $key ] = array(
				'mode' => 'passive',
				'ttl'  => $ttl,
			);
		}

		public function add_passive_rx( $group, $pattern, $ttl ) {
			$this->run_callbacks( 'config', array( 'passive-rx', $group, $pattern, $ttl ) );
			if ( ! array_key_exists( $group, $this->regex_keys ) ) {
				$this->regex_keys[ $group ] = array();
			}
			$this->regex_keys[ $group ][ $pattern ] = array(
				'mode' => 'passive',
				'ttl'  => $ttl,
			);
		}

		/**
		 * Determine whether we want to intercept a key or not.  Almost all
		 * future code should be in here or feeding a variable or constant
		 * read in here.
		 */
		private function do_intercept_key( $key, $group ) {
			if ( false === $this->quick_check_key_intercept( $key, $group ) ) {
				$this->run_callbacks( 'key_miss_quick', array( $key, $group ) );
				return false;
			}

			// Resolve the key/group into the end result key/group.
			// This takes into account blog prefixes, etc...
			$resolved = $this->resolve_cache_key( $key, $group );

			// Look for specific key matches
			if ( isset( $this->specific_keys[ $resolved['group'] ] ) ) {
				if ( array_key_exists( $resolved['key'], $this->specific_keys[ $resolved['group'] ] ) ) {
					$rval = array(
						'resolved' => $resolved,
						'model'    => $this->specific_keys[ $resolved['group'] ][ $resolved['key'] ],
					);
					$this->run_callbacks( 'key_hit', $rval );
					return $rval;
				}
			}

			// Look for regular expression matches
			if ( isset( $this->regex_keys[ $resolved['group'] ] ) ) {
				foreach ( $this->regex_keys[ $resolved['group'] ] as $regex => $model ) {
					if ( 1 === preg_match( $regex, $resolved['key'] ) ) {
						$rval = array(
							'resolved' => $resolved,
							'model'    => $model,
						);
						$this->run_callbacks( 'key_hit', $rval );
						return $rval;
					}
				}
			}

			$this->run_callbacks( 'key_miss', array( 'resolved' => $resolved ) );
			return false;
		}

		protected function quick_check_key_intercept( $key, $group ) {
			if ( '__key__' === $group ) {
				return false; // never handle __key__ which is part of mcremote
			}

			if ( '' === $group ) {
				return ( array_key_exists( 'default', $this->specific_keys ) || array_key_exists( 'default', $this->regex_keys ) );
			}

			if ( is_numeric( $group[0] ) ) {
				return true; // Requires deep check
			}

			return ( array_key_exists( $group, $this->specific_keys ) || array_key_exists( $group, $this->regex_keys ) );
		}

		/**
		 * resolve_cache_key resolves the eventual actual cache key for a key/group.  We need to do
		 * this because wp_object_cache has logic which actually changes the key and even group in
		 * certain circumstances (for example prefixing with a blog_id)
		 */
		private function resolve_cache_key( $key, $group ) {
			global $wp_object_cache;
			$_group = $group;
			$_key   = $wp_object_cache->key( $key, $_group );

			// Strip off the cache salt and flush prefix
			// They make it impossible to actually define
			// a key that will be cached.
			$split = explode( ':', $_key );
			$split = array_slice( $split, 2 );
			$_key  = implode( ':', $split );

			return array(
				'key'   => $_key,
				'group' => $_group,
				'old'   => array(
					'key'   => $key,
					'group' => $group,
				),
			);
		}

		/**
		 * wp_cache_get
		 */
		public function wp_cache_get( $value, $id, $flag, $force ) {
			if ( true === $force ) {
				// Don't intercept if we're explicitly bypassing local caches
				return false;
			}

			$intercept = $this->do_intercept_key( $id, $flag );
			if ( ! $intercept ) {
				return $value;
			}
			switch ( $intercept['model']['mode'] ) {
				case 'passive':
					return $this->passive_wp_cache_get( $intercept, $force );
			}
			return $value;
		}

		private function passive_wp_cache_get( $intercept, $force ) {
			global $wp_object_cache;

			$id   = $intercept['resolved']['old']['key'];
			$flag = $intercept['resolved']['old']['group'];

			// Obtain the resolved cache key
			$temp_flag = $flag;
			$key       = $wp_object_cache->key( $id, $temp_flag );

			// Serve from the thread cache instead of hitting APC if we've
			// dealt with this value before during this execution
			$thread_cache_key = sprintf( '%s:%s', $intercept['resolved']['group'], $intercept['resolved']['key'] );
			if ( isset( $this->cache[ $thread_cache_key ] ) ) {
				$rval = $this->maybe_clone( $this->cache[ $thread_cache_key ] );
				$this->run_callbacks(
					'cache_hit',
					array(
						'cache'     => 'thread',
						'intercept' => $intercept,
						'value'     => $rval,
					)
				);
				return $rval;
			}

			// Attempt to fetch from acpu
			$success = false;
			$rval    = apcu_fetch( $key, $success );

			// On success simply serve that value
			if ( $success ) {
				// Store in the thread cache
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
				$this->cache[ $thread_cache_key ] = unserialize( $rval );

				$rval = $this->maybe_clone( $this->cache[ $thread_cache_key ] );

				$this->run_callbacks(
					'cache_hit',
					array(
						'cache'     => 'apcu',
						'intercept' => $intercept,
						'value'     => $rval,
					)
				);
				return $rval;
			}

			// Attempt to fetch the object from memcached
			$rval = $wp_object_cache->get( $id, $flag, $force );

			if ( false !== $rval ) {
				// Store in the thread cache and store it in apcu
				$this->cache[ $thread_cache_key ] = $this->maybe_clone( $rval );
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
				if ( apcu_store( $key, serialize( $this->cache[ $thread_cache_key ] ), $intercept['model']['ttl'] ) ) {
					$this->run_callbacks(
						'cache_miss',
						array(
							'found'     => true,
							'stored'    => true,
							'intercept' => $intercept,
							'value'     => $rval,
						)
					);
				} else {
					$this->run_callbacks(
						'cache_miss',
						array(
							'found'     => true,
							'stored'    => false,
							'intercept' => $intercept,
							'value'     => $rval,
						)
					);
				}
			} else {
					$this->run_callbacks(
						'cache_miss',
						array(
							'found'     => false,
							'stored'    => false,
							'intercept' => $intercept,
							'value'     => $rval,
						)
					);
			}

			// Finally return the value we got from the object cache
			return $rval;
		}

		private function maybe_clone( $value ) {
			// Concept borrowed from object-cache.php itself
			if ( is_object( $value ) ) {
				return clone $value;
			}
			return $value;
		}

		public function __construct() {
			// Allow for the possibility that this is already defined
			if ( ! defined( 'WP_OBJ_CACHE_HOOKS' ) ) {
				define( 'WP_OBJ_CACHE_HOOKS', true );
			}
			// If we should not be hooking then there's no reason to add overhead to actions and filters
			if ( defined( 'WP_OBJ_CACHE_HOOKS' ) && WP_OBJ_CACHE_HOOKS ) {
				do_action( 'apcu_hot_cache_init', $this );
				add_filter( 'pre_wp_cache_get', array( $this, 'wp_cache_get' ), 1, 4 );
			}
		}

		private function debug() {
			// NOTE: WPCOM_SANDBOXED is defined too late on VIP Go for this to work
			if ( ! defined( 'WPCOM_SANDBOXED' ) || ! WPCOM_SANDBOXED ) {
				return;
			}
			error_log( call_user_func_array( 'sprintf', func_get_args() ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This is for local/sandbox debugging only
		}

		public function add_callback( $cb, $func ) {
			$this->callbacks[ $cb ][] = $func;
		}

		private function run_callbacks( $cb, $args = array() ) {
			if ( ! array_key_exists( $cb, $this->callbacks ) || empty( $this->callbacks[ $cb ] ) ) {
				return;
			}
			foreach ( $this->callbacks[ $cb ] as $callback ) {
				call_user_func_array( $callback, $args );
			}
		}

		public function debug_apc_key_hit( $resolved ) {
			if ( ! $this->debug_key_hits ) {
				return;
			}
			$this->debug(
				'hot-cache key hit: %s/%s',
				$resolved['group'],
				$resolved['key']
			);
		}

		public function debug_apc_key_miss( $resolved ) {
			if ( ! $this->debug_key_misses ) {
				return;
			}
			$this->debug(
				'hot-cache key miss: %s/%s',
				$resolved['group'],
				$resolved['key']
			);
		}

		public function debug_apc_key_miss_quick( $key, $group ) {
			if ( ! $this->debug_key_misses ) {
				return;
			}
			$this->debug(
				'hot-cache key quick miss: %s/%s',
				$group,
				$key
			);
		}

		public function debug_apc_cache_miss( $found, $stored, $key, $value ) {
			if ( ! $this->debug_key_misses ) {
				return;
			}
			$this->debug(
				'hot-cache cache miss for %s/%s found: %s, stored: %s, value: %s',
				$key['resolved']['group'],
				$key['resolved']['key'],
				$found ? 'true' : 'false',
				$stored ? 'true' : 'false',
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				$this->debug_values ? var_export( $value, true ) : '<redacted>'
			);
		}

		public function debug_apc_cache_hit( $type, $key, $value ) {
			$this->debug(
				'hot-cache %s cache hit for %s/%s value: %s',
				$type,
				$key['resolved']['group'],
				$key['resolved']['key'],
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				$this->debug_values ? var_export( $value, true ) : '<redacted>'
			);
		}

		public function debug_apc_config( $type, $group, $key, $ttl ) {
			switch ( $type ) {
				default:
					$this->debug(
						'hot-cache adding %s match on %s/%s for %d',
						$type,
						$group,
						$key,
						$ttl
					);
					break;
				case 'hardcode':
					$this->debug(
						'hot-cache %s match on %s/%s as %s',
						$group,
						$key,
						$ttl,
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
						$this->debug_values ? var_export( $ttl, true ) : '<redacted>'
					);
					break;
			}
		}

		public function enable_debugging( $debug_key_misses = false, $debug_key_hits = false, $debug_values = false ) {
			$this->debug( 'enable_debugging misses: %d, hits: %d, values: %d', $debug_key_misses, $debug_key_hits, $debug_values );
			$this->debug_values     = $debug_values;
			$this->debug_key_misses = $debug_key_misses;
			$this->debug_key_hits   = $debug_key_hits;
			if ( ! $this->debugging ) {
				$this->debugging = true;
				$this->add_callback( 'cache_hit', array( $this, 'debug_apc_cache_hit' ) );
				$this->add_callback( 'cache_miss', array( $this, 'debug_apc_cache_miss' ) );
				$this->add_callback( 'key_miss', array( $this, 'debug_apc_key_miss' ) );
				$this->add_callback( 'key_miss_quick', array( $this, 'debug_apc_key_miss_quick' ) );
				$this->add_callback( 'key_hit', array( $this, 'debug_apc_key_hit' ) );
			}
		}

		public function stats() {
			echo '<style type="text/css">table.apcuhotcache tr td, table.apcuhotcache tr th { margin: 0; border-bottom: 1px solid #ddd; }</style>';
			echo '<br/><h3>APCU Hot Caching Engine is enabled</h3><br/>';
			echo '<h4>Configuration</h4>';
			echo '<table class="apcuhotcache" style="width: 100%">';
			echo '<thead><tr><th>type</th><th>group</th><th>key</th><th>ttl</th></tr></thead>';
			foreach ( $this->specific_keys as $group => $keys ) {
				foreach ( $keys as $key => $details ) {
					printf(
						'<tr><td>exact</td><td>%s</td><td>%s</td><td>%s</td></tr>',
						esc_html( $group ),
						esc_html( $key ),
						( 86401 == $details['ttl'] ) ? 'static' : esc_html( $details['ttl'] )
					);
				}
			}
			foreach ( $this->regex_keys as $group => $keys ) {
				foreach ( $keys as $key => $details ) {
					printf(
						'<tr><td>regex</td><td>%s</td><td>%s<td>%d</td></tr>',
						esc_html( $group ),
						esc_html( $key ),
						intval( $details['ttl'] )
					);
				}
			}
			echo '</table>';
			echo '<br/><h4>Intercepted Values</h4>';
			echo '<table class="apcuhotcache" style="width: 100%">';
			echo '<thead><tr><th>group</th><th>key</th><th>value</th></tr></thead>';
			foreach ( $this->cache as $key => $value ) {
				$bits  = explode( ':', $key );
				$group = array_shift( $bits );
				$key   = implode( ':', $bits );
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $group ),
					esc_html( $key ),
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
					esc_html( var_export( $value, true ) )
				);
			}
			echo '</table>';
		}
	}

	// TODO: define this constant via site meta so we can enable/disable on a per site basis if necessary
	// Allow for disabling in constants.php
	if ( ! defined( 'DO_APC_LOCAL_CACHE_INTERCEPT' ) || true === DO_APC_LOCAL_CACHE_INTERCEPT ) {
		// Also if APC is not installed and working then there's no point in loading anything
		if ( function_exists( 'apcu_add' ) ) {
			if ( function_exists( 'do_apcu_hot_cache_init' ) ) {
				add_action( 'apcu_hot_cache_init', 'do_apcu_hot_cache_init' );
			}
			$apc_cache_interceptor = new APC_Cache_Interceptor();
		}
	}

endif;
