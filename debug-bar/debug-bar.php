<?php
/*
 Plugin Name: Debug Bar
 Plugin URI: https://wordpress.org/plugins/debug-bar/
 Description: Adds a debug menu to the admin bar that shows query, cache, and other helpful debugging information.
 Author: wordpressdotorg
 Version: 1.1.2
 Author URI: https://wordpress.org/
 Text Domain: debug-bar
 */

/***
 * Debug Functions
 *
 * When logged in as a super admin, these functions will run to provide
 * debugging information when specific super admin menu items are selected.
 *
 * They are not used when a regular user is logged in.
 */
class Debug_Bar {
	public $panels = array();

	function __construct() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_action( 'admin_init', array( $this, 'init_ajax' ) );
		}
		add_action( 'admin_bar_init', array( $this, 'init' ) );

		// Stop the removal of important assets in AMP dev mode
		add_filter( 'amp_dev_mode_element_xpaths', array( $this, 'amp_dev_mode_element_xpaths' ) );
	}

	function Debug_Bar() {
		_deprecated_constructor( __METHOD__, '0.8.3', __CLASS__ );
		self::__construct();
	}

	// Runs immediately after constructor
	function early_init() {
		$path = plugin_dir_path( __FILE__ );
		$recs = array( 'panel', 'wp-http' );
		foreach ( $recs as $rec ) {
			require_once "$path/panels/class-debug-bar-$rec.php";
		}

		$panel = new Debug_Bar_WP_Http();
		$this->panels[] = $panel;
		$panel->early_init();
	}

	function init() {
		if ( ! $this->enable_debug_bar() ) {
			return;
		}

		load_plugin_textdomain( 'debug-bar' );

		add_action( 'wp_before_admin_bar_render',   array( $this, 'wp_before_admin_bar_render' ), 1000000 );
		add_action( 'admin_footer',                 array( $this, 'render' ), 1000 );
		add_action( 'wp_footer',                    array( $this, 'render' ), 1000 );
		add_action( 'wp_head',                      array( $this, 'ensure_ajaxurl' ), 1 );
		add_filter( 'body_class',                   array( $this, 'body_class' ) );
		add_filter( 'admin_body_class', array( $this, 'body_class' ) );

		$this->requirements();
		$this->enqueue();
		$this->init_panels();
	}

	/**
	 * Are we on the wp-login.php page?
	 *
	 * We can get here while logged in and break the page as the admin bar
	 * is not shown and other things the js relies on are not available.
	 *
	 * @return bool
	 */
	function is_wp_login() {
		return 'wp-login.php' == basename( $_SERVER['SCRIPT_NAME'] );
	}

	/**
	 * Should the debug bar functionality be enabled?
	 *
	 * @since 0.9
	 *
	 * @param bool $ajax Whether this is an ajax call or not. Defaults to false.
	 * @return bool
	 */
	function enable_debug_bar( $ajax = false ) {
		$enable = false;

		if ( $ajax && is_super_admin() ) {
			$enable = true;
		} elseif ( ! $ajax && ( is_super_admin() && is_admin_bar_showing() && ! $this->is_wp_login() ) ) {
			$enable = true;
		}

		/**
		 * Allows for overruling of whether the debug bar functionality will be enabled.
		 *
		 * @since 0.9
		 *
		 * @param bool $enable Whether the debug bar will be enabled or not.
		 */
		return apply_filters( 'debug_bar_enable', $enable );
	}

	function init_ajax() {
		if ( ! $this->enable_debug_bar( true ) ) {
			return;
		}

		$this->requirements();
		$this->init_panels();
	}

	function requirements() {
		$path = plugin_dir_path( __FILE__ );
		$recs = array( 'panel', 'php', 'queries', 'request', 'wp-query', 'object-cache', 'deprecated', 'js' );

		foreach ( $recs as $rec ) {
			require_once "$path/panels/class-debug-bar-$rec.php";
		}
	}

	function enqueue() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';

		$style_dependencies = array();
		$script_dependencies = array( 'jquery' );
		if ( $this->is_amp() ) {
			// Add admin-bar dependencies so AMP dev mode will stop removing debug-bar assets
			// https://weston.ruter.net/2019/09/24/integrating-with-amp-dev-mode-in-wordpress/
			$style_dependencies[] = 'admin-bar';
			$script_dependencies[] = 'admin-bar';
		}

		$css_filename = "css/debug-bar$suffix.css";
		wp_enqueue_style(
			'debug-bar',
			plugins_url( $css_filename, __FILE__ ),
			$style_dependencies,
			filemtime( __DIR__ . '/' . $css_filename )
		);

		$js_filename = "js/debug-bar$suffix.js";
		wp_enqueue_script(
			'debug-bar',
			plugins_url( $js_filename, __FILE__ ),
			$script_dependencies,
			filemtime( __DIR__ . '/' . $js_filename ),
			true
		);

		do_action( 'debug_bar_enqueue_scripts' );
	}

	function init_panels() {
		$classes = array(
			'Debug_Bar_PHP',
			'Debug_Bar_Queries',
			'Debug_Bar_WP_Query',
			'Debug_Bar_Deprecated',
			'Debug_Bar_Request',
			'Debug_Bar_Object_Cache',
			'Debug_Bar_JS',
		);

		foreach ( $classes as $class ) {
			$this->panels[] = new $class;
		}

		$this->panels = apply_filters( 'debug_bar_panels', $this->panels );
	}

	function ensure_ajaxurl() { ?>
		<script type="text/javascript">
			//<![CDATA[
			var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
			//]]>
		</script>
		<?php
	}

	// memory_get_peak_usage is PHP >= 5.2.0 only
	function safe_memory_get_peak_usage() {
		return function_exists( 'memory_get_peak_usage' ) ? memory_get_peak_usage() : memory_get_usage();
	}

	function wp_before_admin_bar_render() {
		global $wp_admin_bar;

		$classes = apply_filters( 'debug_bar_classes', array() );
		$classes = implode( " ", $classes );

		/* Add the main siteadmin menu item */
		$wp_admin_bar->add_menu( array(
			'id'     => 'debug-bar',
			'parent' => 'top-secondary',
			'title'  => apply_filters( 'debug_bar_title', __( 'Debug', 'debug-bar' ) ),
			'meta'   => array( 'class' => $classes ),
		) );

		foreach ( $this->panels as $panel_key => $panel ) {
			$panel->prerender();
			if ( ! $panel->is_visible() ) {
				continue;
			}

			$panel_class = get_class( $panel );

			$wp_admin_bar->add_menu( array(
				'parent' => 'debug-bar',
				'id'     => "debug-bar-$panel_class",
				'title'  => $panel->title(),
				'href'   => '#debug-menu-target-' . esc_attr( $panel_class ),
				'meta'   => array(
					'rel' => '#debug-menu-link-' . esc_attr( $panel_class ),
				),
			) );
		}
	}

	function body_class( $classes ) {
		if ( is_array( $classes ) ) {
			$classes[] = 'debug-bar-maximized';
		} else {
			$classes .= ' debug-bar-maximized ';
		}

		if ( isset( $_GET['debug-bar'] ) ) {
			if ( is_array( $classes ) ) {
				$classes[] = 'debug-bar-visible';
			} else {
				$classes .= ' debug-bar-visible ';
			}
		}

		return $classes;
	}

	/*
	 * AMP Compatibility
	 * Based primarily on approach described in https://weston.ruter.net/2019/09/24/integrating-with-amp-dev-mode-in-wordpress/
	 */
	function is_amp() {
		return function_exists( 'is_amp_endpoint' ) && is_amp_endpoint();
	}

	function amp_dev_mode_element_xpaths( $xpaths ) {
		// Add data-ampdevmode to jQuery script tag so it will be left intact
		$xpaths[] = '//script[ contains( @src, "wp-includes/js/jquery/jquery.js" ) ]';

		// Add data-ampdevmode to object cache scripts used by the debug bar
		$xpaths[] = '//*[@id = "object-cache-stats"]//script';

		// Add data-ampdevmode to debug-menu-links because AMP is removing inline onclick handlers
		$xpaths[] = '//*[@class = "debug-menu-links"]//a';

		return $xpaths;
	}

	function render() {
		global $wpdb;

		if ( empty( $this->panels ) ) {
			return;
		}

		foreach ( $this->panels as $panel_key => $panel ) {
			$panel->prerender();
			if ( ! $panel->is_visible() ) {
				unset( $this->panels[ $panel_key ] );
			}
		}

		?>
		<div id='querylist'>

			<div id="debug-bar-actions">
				<span class="maximize">+</span>
				<span class="restore">&ndash;</span>
				<span class="close">&times;</span>
			</div>

			<div id='debug-bar-info'>
				<div id="debug-status">
					<?php //@todo: Add a links to information about WP_DEBUG, PHP version, MySQL version, and Peak Memory.
					$statuses   = array();
					$statuses[] = array(
						'site',
						php_uname( 'n' ),
						/* translators: %d is the site id number in a multi-site setting. */
						sprintf( __( '#%d', 'debug-bar' ), get_current_blog_id() ),
					);
					$statuses[] = array(
						'php',
						__( 'PHP', 'debug-bar' ),
						phpversion(),
					);
					$db_title   = empty( $wpdb->is_mysql ) ? __( 'DB', 'debug-bar' ) : __( 'MySQL', 'debug-bar' );
					$statuses[] = array(
						'db',
						$db_title,
						$wpdb->db_version(),
					);
					$statuses[] = array(
						'memory',
						__( 'Memory Usage', 'debug-bar' ),
						/* translators: %s is a formatted number representing the memory usage. */
						sprintf( __( '%s bytes', 'debug-bar' ), number_format_i18n( $this->safe_memory_get_peak_usage() ) ),
					);

					if ( ! WP_DEBUG ) {
						$statuses[] = array(
							'warning',
							__( 'Please Enable', 'debug-bar' ),
							'WP_DEBUG',
						);
					}

					$statuses = apply_filters( 'debug_bar_statuses', $statuses );

					foreach ( $statuses as $status ):
						list( $slug, $title, $data ) = $status;

						?>
						<div id='debug-status-<?php echo esc_attr( $slug ); ?>' class='debug-status'>
						<div class='debug-status-title'><?php echo $title; ?></div>
						<?php if ( ! empty( $data ) ): ?>
						<div class='debug-status-data'><?php echo $data; ?></div>
					<?php endif; ?>
						</div><?php
					endforeach;
					?>
				</div>
			</div>

			<div id='debug-bar-menu'>
				<ul id="debug-menu-links">

					<?php
					$current = ' current';
					foreach ( $this->panels as $panel ) :
						$class = get_class( $panel );
						?>
						<li><a
								id="debug-menu-link-<?php echo esc_attr( $class ); ?>"
								class="debug-menu-link<?php echo $current; ?>"
								href="#debug-menu-target-<?php echo esc_attr( $class ); ?>">
								<?php
								// Not escaping html here, so panels can use html in the title.
								echo $panel->title();
								?>
							</a></li>
						<?php
						$current = '';
					endforeach; ?>

				</ul>
			</div>

			<div id="debug-menu-targets"><?php
				$current = ' style="display: block"';
				foreach ( $this->panels as $panel ) :
					$class = get_class( $panel ); ?>

					<div id="debug-menu-target-<?php echo $class; ?>" class="debug-menu-target" <?php echo $current; ?>>
						<?php $panel->render(); ?>
					</div>

					<?php
					$current = '';
				endforeach;
				?>
			</div>

			<?php do_action( 'debug_bar' ); ?>
		</div>
		<?php
	}
}

$GLOBALS['debug_bar'] = new Debug_Bar();
$GLOBALS['debug_bar']->early_init();
