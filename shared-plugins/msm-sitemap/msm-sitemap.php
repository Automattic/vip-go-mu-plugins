<?php
/*
Plugin Name: Metro Sitemap
Description: Comprehensive sitemaps for your WordPress site. Joint collaboration between Metro.co.uk, MAKE, Alley Interactive, and WordPress.com VIP.
Author: Artur Synowiec, Paul Kevan, and others
Version: 0.1
Stable tag: 0.1
License: GPLv2
*/

if ( defined( 'WP_CLI' ) && true === WP_CLI )
	require dirname( __FILE__ ) . '/includes/wp-cli.php';

class Metro_Sitemap {

	const DEFAULT_POSTS_PER_SITEMAP_PAGE = 500;

	const SITEMAP_CPT = 'msm_sitemap';

	/**
	 * Register actions for our hook
	 */
	public static function setup() {
		define( 'MSM_INTERVAL_PER_GENERATION_EVENT', 60 ); // how far apart should full cron generation events be spaced

		add_filter( 'cron_schedules', array( __CLASS__, 'sitemap_15_min_cron_interval' ) );

		// A cron schedule for creating/updating sitemap posts based on updated content since the last run
		add_action( 'init', array( __CLASS__, 'sitemap_init' ) );
		add_action( 'admin_init', array( __CLASS__, 'sitemap_init_cron' ) );
		add_action( 'redirect_canonical', array( __CLASS__, 'disable_canonical_redirects_for_sitemap_xml' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'create_post_type' ) );
		add_filter( 'template_include', array( __CLASS__, 'load_sitemap_template' ) );

		// By default, we use wp-cron to help generate the full sitemap.
		// However, this will let us override it, if necessary, like on WP.com
		if ( true === apply_filters( 'msm_sitemap_use_cron_builder', true ) ) {
			require dirname( __FILE__ ) . '/includes/msm-sitemap-builder-cron.php';
			MSM_Sitemap_Builder_Cron::setup();
		}
	}

	/**
	 * Register 15 minute cron interval for latest articles
	 * @param array[] $schedules
	 * @return array[] modified schedules
	 */
	public static function sitemap_15_min_cron_interval( $schedules ) {
		$schedules[ 'ms-sitemap-15-min-cron-interval' ] = array(
			'interval' => 900,
			'display' => __( 'Every 15 minutes', 'metro-sitemaps' ),
			);
		return $schedules;
	}

	/**
	 * Register endpoint for sitemap and other hooks
	 */
	public static function sitemap_init() {
		define( 'WPCOM_SKIP_DEFAULT_SITEMAP', true );
		add_rewrite_tag( '%sitemap%', 'true' ); // allow 'sitemap=true' parameter
		add_rewrite_rule( '^sitemap.xml$','index.php?sitemap=true','top' );

		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'metro_sitemap_menu' ) );
		add_action( 'msm_cron_update_sitemap', array( __CLASS__, 'update_sitemap_from_modified_posts' ) );
		add_action( 'wp_ajax_msm-sitemap-get-sitemap-counts', array( __CLASS__, 'ajax_get_sitemap_counts' ) );
	}

	/**
	 * Register admin menu for sitemap
	 */
	public static function metro_sitemap_menu() {
		$page_hook = add_management_page( __( 'Sitemap', 'metro-sitemaps' ), __( 'Sitemap', 'metro-sitemaps' ), 'manage_options', 'metro-sitemap', array( __CLASS__, 'render_sitemap_options_page' ) );
		add_action( 'admin_print_scripts-' . $page_hook, array( __CLASS__, 'add_admin_scripts' ) );
	}

	public static function add_admin_scripts() {
		wp_enqueue_script( 'flot', plugins_url( '/js/flot/jquery.flot.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'msm-sitemap-admin', plugins_url( '/js/msm-sitemap-admin.js', __FILE__ ), array( 'jquery', 'flot' ) );
		wp_enqueue_script( 'flot-time', plugins_url( '/js/flot/jquery.flot.time.js', __FILE__ ), array( 'jquery', 'flot' ) );

		wp_enqueue_style( 'msm-sitemap-css', plugins_url( 'css/style.css', __FILE__ ) );
		wp_enqueue_style( 'noticons', '//s0.wordpress.com/i/noticons/noticons.css' );
	}

	public static function ajax_get_sitemap_counts() {
		check_admin_referer( 'msm-sitemap-action' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'metro-sitemaps' ) );
		}

		$n = 10;
		if ( isset( $_REQUEST['num_days'] ) ) {
			$n = intval( $_REQUEST['num_days'] );
		}

		$data = array(
			'total_indexed_urls'   => number_format( Metro_Sitemap::get_total_indexed_url_count() ),
			'total_sitemaps'	   => number_format( Metro_Sitemap::count_sitemaps() ),
			'sitemap_indexed_urls' => self::get_recent_sitemap_url_counts( $n ),
		);

		wp_send_json( $data );
	}

	/**
	 * Render admin options page
	 */
	public static function render_sitemap_options_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'metro-sitemaps' ) );
		}

		// Array of possible user actions
		$actions = apply_filters( 'msm_sitemap_actions', array() );

		// Start outputting html
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( 'Sitemap', 'metro-sitemaps' ) . '</h2>';

		if ( ! self::is_blog_public() ) {
			self::show_action_message( __( 'Oops! Sitemaps are not supported on private blogs. Please make your blog public and try again.', 'metro-sitemaps' ), 'error' );
			echo '</div>';
			return;
		}

		if ( isset( $_POST['action'] ) ) {
			check_admin_referer( 'msm-sitemap-action' );
			foreach ( $actions as $slug => $action ) {
				if ( $action['text'] !== $_POST['action'] )	continue;

				do_action( 'msm_sitemap_action-' . $slug );
				break;
			}
		}

		// All the settings we need to read to display the page
		$sitemap_create_in_progress = get_option( 'msm_sitemap_create_in_progress' ) === true;
		$sitemap_update_last_run = get_option( 'msm_sitemap_update_last_run' );

		// Determine sitemap status text
		$sitemap_create_status = apply_filters(
			'msm_sitemap_create_status',
			$sitemap_create_in_progress ? __( 'Running', 'metro-sitemaps' ) : __( 'Not Running', 'metro-sitemaps' )
		);
		
		?>
		<div class="stats-container">
			<div class="stats-box"><strong id="sitemap-count"><?php echo number_format( Metro_Sitemap::count_sitemaps() ); ?></strong><?php _e( 'Sitemaps', 'metro-sitemaps' ); ?></div>
			<div class="stats-box"><strong id="sitemap-indexed-url-count"><?php echo number_format( Metro_Sitemap::get_total_indexed_url_count() ); ?></strong><?php _e( 'Indexed URLs', 'metro-sitemaps' ); ?></div>
			<div class="stats-footer"><span><span class="noticon noticon-time"></span><?php _e( 'Updated', 'metro-sitemaps' ); ?> <strong><?php echo human_time_diff( $sitemap_update_last_run ); ?> <?php _e( 'ago', 'metro-sitemaps' ) ?></strong></span></div>
		</div>

		<h3><?php _e( 'Latest Sitemaps', 'metro-sitemaps' ); ?></h3>
		<div class="stats-container stats-placeholder"></div>
		<div id="stats-graph-summary"><?php printf( __( 'Max: %s on %s. Showing the last %s days.', 'metro-sitemaps' ), '<span id="stats-graph-max"></span>', '<span id="stats-graph-max-date"></span>', '<span id="stats-graph-num-days"></span>' ); ?></div>

		<h3><?php _e( 'Generate', 'metro-sitemaps' ); ?></h3>
		<p><strong><?php _e( 'Sitemap Create Status:', 'metro-sitemaps' ) ?></strong> <?php echo esc_html( $sitemap_create_status ); ?></p>
		<form action="<?php echo menu_page_url( 'metro-sitemap', false ) ?>" method="post" style="float: left;">
			<?php wp_nonce_field( 'msm-sitemap-action' ); ?>
			<?php foreach ( $actions as $action ):
				if ( ! $action['enabled'] ) continue; ?>
				<input type="submit" name="action" class="button-secondary" value="<?php echo esc_attr( $action['text'] ); ?>">
			<?php endforeach; ?>
		</form>
		</div>
		<div id="tooltip"><strong class="content"></strong> <?php _e( 'indexed urls', 'metro-sitemaps' ); ?></div>
		<?php
	}

	/**
	 * Displays a notice, error or warning to the user
	 * @param str $message The message to show to the user
	 */
	public static function show_action_message( $message, $level = 'notice' ) {
		$class = 'updated';
		if ( $level === 'warning' )
			$class = 'update-nag';
		elseif ( $level === 'error' )
			$class = 'error';

		echo '<div class="' . esc_attr( $class ) . ' msm-sitemap-message"><p>' . wp_kses( $message, wp_kses_allowed_html( 'post' ) ) . '</p></div>';
	}
		
	/**
	 * Counts the number of sitemaps that have been generated.
	 * 
	 * @return int The number of sitemaps that have been generated
	 */
	public static function count_sitemaps() {
		$count = wp_count_posts( Metro_Sitemap::SITEMAP_CPT );
		return (int) $count->publish;
	}
	
	/**
	 * Gets the current number of URLs indexed by msm-sitemap accross all sitemaps.
	 * 
	 * @return int The number of total number URLs indexed
	 */
	public static function get_total_indexed_url_count() {
		return intval( get_option( 'msm_sitemap_indexed_url_count', 0 ) );
	}

	/**
	 * Returns the $n most recent sitemap indexed url counts.
	 *
	 * @param int $n The number of days of sitemap stats to grab.
	 * @return array An array of sitemap stats
	 */
	public static function get_recent_sitemap_url_counts( $n = 7 ) {
		$stats = array();

		for ( $i = 0; $i < $n; $i++ ) {
			$date = date( get_option( 'date_format', 'Y-m-d' ), strtotime( "-$i days" ) );

			list( $year, $month, $day ) = explode( '-', $date );

			$stats[$date] = self::get_indexed_url_count( $year, $month, $day );
		}

		return $stats;
	}

	public static function is_blog_public() {
		return ( 1 == get_option( 'blog_public' ) );
	}
	
	/**
	 * Gets the number of URLs indexed for the given sitemap.
	 *
	 * @param array $sitemaps The sitemaps to retrieve counts for.
	 */
	public static function get_indexed_url_count( $year, $month, $day ) {
		$sitemap_id = self::get_sitemap_post_id( $year, $month, $day );

		if ( $sitemap_id ) {
			return intval( get_post_meta( $sitemap_id, 'msm_indexed_url_count', true ) );
		}

		return false;
	}
		
	/**
	 * Add entry to the bottom of robots.txt
	 */
	public static function robots_txt( $output, $public ) {

		// Make sure the site isn't private
		if ( '1' == $public ) {
			$output .= '# Sitemap archive' . PHP_EOL;
			$output .= 'Sitemap: ' . home_url( '/sitemap.xml' ) . PHP_EOL . PHP_EOL;
		}
		return $output;

	}

	/**
	 * Add cron jobs required to generate these sitemaps
	 */
	public static function sitemap_init_cron() {
		if ( self::is_blog_public() && ! wp_next_scheduled( 'msm_cron_update_sitemap' ) ) {
			wp_schedule_event( time(), 'ms-sitemap-15-min-cron-interval', 'msm_cron_update_sitemap' );
		}
	}

	/**
	 * Disable canonical redirects for the sitemap file
	 * @see http://codex.wordpress.org/Function_Reference/redirect_canonical
	 * @param string $redirect_url
	 * @param string $requested_url
	 * @return string URL to redirect
	 */
	public static function disable_canonical_redirects_for_sitemap_xml( $redirect_url, $requested_url ) {
		if ( preg_match( '|sitemap\.xml|', $requested_url ) ) {
			return $requested_url;
		}
		return $redirect_url;
	}

	/**
	 * Return range of years for posts in the database
	 * @return int[] valid years
	 */
	public static function get_post_year_range() {
		global $wpdb;

		$oldest_post_date_gmt = $wpdb->get_var( "SELECT post_date FROM $wpdb->posts WHERE post_status = 'publish' ORDER BY post_date ASC LIMIT 1" );
		$oldest_post_year = date( 'Y', strtotime( $oldest_post_date_gmt ) );
		$current_year = date( 'Y' );

		return range( $oldest_post_year, $current_year );
	}

	/**
	 * Get every year that has valid posts in a range
	 * @return int[] years with posts
	 */
	public static function check_year_has_posts() {

		$all_years = self::get_post_year_range();

		$years_with_posts = array();
		foreach ( $all_years as $year ) {
			if ( self::date_range_has_posts( self::get_date_stamp( $year, 1, 1 ), self::get_date_stamp( $year, 12, 31 ) ) ) {
				$years_with_posts[] = $year;
			}
		}
		return $years_with_posts;

	}

	/**
	 * Get properly formatted data stamp from year, month, and day
	 * @param int $year
	 * @param int $month
	 * @param int $day
	 * @return string formatted stamp
	 */
	public static function get_date_stamp( $year, $month, $day ) {
		return sprintf( '%s-%s-%s', $year, str_pad( $month, 2, '0', STR_PAD_LEFT ), str_pad( $day, 2, '0', STR_PAD_LEFT ) );
	}

	/**
	 * Does a current date range have posts?
	 * @param string $start_date
	 * @param string $end_date
	 * @return int|false
	 */
	public static function date_range_has_posts( $start_date, $end_date ) {
		global $wpdb;

		$start_date .= ' 00:00:00';
		$end_date .= ' 23:59:59';

		$post_types_in = self::get_supported_post_types_in();
		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status = 'publish' AND post_date >= %s AND post_date <= %s AND post_type IN ( {$post_types_in} ) LIMIT 1", $start_date, $end_date ) );
	}

	/**
	 * Generate sitemap for a date; this is where XML is rendered.
	 * @param string $sitemap_date
	 */
	public static function generate_sitemap_for_date( $sitemap_date ) {
		global $wpdb;

		$sitemap_time = strtotime( $sitemap_date );
		list( $year, $month, $day ) = explode( '-', $sitemap_date );

		$sitemap_name = $sitemap_date;
		$sitemap_exists = false;

		$sitemap_data = array(
			'post_name' => $sitemap_name,
			'post_title' => $sitemap_name,
			'post_type' => self::SITEMAP_CPT,
			'post_status' => 'publish',
			'post_date' => $sitemap_date,
			);

		$sitemap_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_name = %s LIMIT 1", self::SITEMAP_CPT, $sitemap_name ) );

		if ( $sitemap_id ) {
			$sitemap_exists = true;
		} else {
			$sitemap_id = wp_insert_post( $sitemap_data );
			$sitemap_exists = true;
		}

		$query_args = apply_filters( 'msm_sitemap_query_args', array(
			'year' => $year,
			'monthnum' => $month,
			'day' => $day,
			'order' => 'DESC',
			'post_status' => 'publish',
			'post_type' => self::get_supported_post_types(),	
			'posts_per_page' => apply_filters( 'msm_sitemap_entry_posts_per_page', self::DEFAULT_POSTS_PER_SITEMAP_PAGE ),
			'no_found_rows' => true,
		) );

		$query = new WP_Query( $query_args );
		$post_count = $query->post_count;

		$total_url_count = self::get_total_indexed_url_count();

		// For migration: in case the previous version used an array for this option
		if ( is_array( $total_url_count ) ) {
			$total_url_count = array_sum( $total_url_count );
			update_option( 'msm_sitemap_indexed_url_count', $total_url_count );
		}

		if ( ! $post_count ) {
			// If no entries - delete the whole sitemap post
			if ( $sitemap_exists ) {
				self::delete_sitemap_by_id( $sitemap_id );
			}
			return;
		}

		// SimpleXML doesn't allow us to define namespaces using addAttribute, so we need to specify them in the construction instead.
		$namespaces = apply_filters( 'msm_sitemap_namespace', array(
			'xmlns:xsi' => 'http://www.w3.org/2001/XMLSchema-instance',
			'xmlns' => 'http://www.sitemaps.org/schemas/sitemap/0.9',
			'xmlns:n' => 'http://www.google.com/schemas/sitemap-news/0.9',
			'xmlns:image' => 'http://www.google.com/schemas/sitemap-image/1.1',
			'xsi:schemaLocation' => 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd',
		) );

		$namespace_str = '<?xml version="1.0" encoding="utf-8"?><urlset';
		foreach ( $namespaces as $ns => $value ) {
			$namespace_str .= sprintf( ' %s="%s"', esc_attr( $ns ), esc_attr( $value ) );
		}
		$namespace_str .= '></urlset>';

		// Create XML
		$xml = new SimpleXMLElement( $namespace_str );

		$url_count = 0;
		while ( $query->have_posts() ) {
			$query->the_post();
			
			if ( apply_filters( 'msm_sitemap_skip_post', false ) )
				continue;

			$url = $xml->addChild( 'url' );
			$url->addChild( 'loc', get_permalink() );
			$url->addChild( 'lastmod', get_the_modified_date( 'Y-m-d' ) . 'T' . get_the_modified_date( 'H:i:s' ) . 'Z' );
			$url->addChild( 'changefreq', 'monthly' );
			$url->addChild( 'priority', '0.7' );

			apply_filters( 'msm_sitemap_entry', $url );

			++$url_count;
			// TODO: add images to sitemap via <image:image> tag
		}

				// Save the sitemap
		if ( $sitemap_exists ) {
			// Get the previous post count
			$previous_url_count = intval( get_post_meta( $sitemap_id, 'msm_indexed_url_count', true ) );

			// Update the total post count with the difference
			$total_url_count += $url_count - $previous_url_count;

			update_post_meta( $sitemap_id, 'msm_sitemap_xml', $xml->asXML() );
			update_post_meta( $sitemap_id, 'msm_indexed_url_count', $url_count );
			do_action( 'msm_update_sitemap_post', $sitemap_id, $year, $month, $day );
		} else {
			/* Should no longer hit this */
			$sitemap_id = wp_insert_post( $sitemap_data );
			add_post_meta( $sitemap_id, 'msm_sitemap_xml', $xml->asXML() );
			add_post_meta( $sitemap_id, 'msm_indexed_url_count', $url_count );
			do_action( 'msm_insert_sitemap_post', $sitemap_id, $year, $month, $day );

			// Update the total url count
			$total_url_count += $url_count;
		}

		// Update indexed url counts
		update_option( 'msm_sitemap_indexed_url_count' , $total_url_count );

		wp_reset_postdata();
	}

	public static function delete_sitemap_for_date( $sitemap_date ) {
		list( $year, $month, $day ) = explode( '-', $sitemap_date );
		$sitemap_id = self::get_sitemap_post_id( $year, $month, $day );
		if ( ! $sitemap_id ) {
			return false;
		}
		return self::delete_sitemap_by_id( $sitemap_id );
	}

	public static function delete_sitemap_by_id( $sitemap_id ) {
		$sitemap = get_post( $sitemap_id );
		if ( ! $sitemap ) {
			return false;
		}

		$sitemap_date = date( 'Y-m-d', strtotime( $sitemap->post_date ) );
		list( $year, $month, $day ) = explode( '-', $sitemap_date );

		$total_url_count = self::get_total_indexed_url_count();
		$total_url_count -= intval( get_post_meta( $sitemap_id, 'msm_indexed_url_count', true ) );
		update_option( 'msm_sitemap_indexed_url_count' , $total_url_count );

		wp_delete_post( $sitemap_id, true );
		do_action( 'msm_delete_sitemap_post', $sitemap_id, $year, $month, $day );
	}

	/**
	 * Register our CPT
	 */
	public static function create_post_type() {
		register_post_type(
			self::SITEMAP_CPT,
			array(
				'labels'       => array(
					'name'          => __( 'Sitemaps' ),
					'singular_name' => __( 'Sitemap' ),
					),
				'public'       => false,
				'has_archive'  => false,
				'rewrite'      => false,
				'show_ui'      => true,  // TODO: should probably have some sort of custom UI
				'show_in_menu' => false, // Since we're manually adding a Sitemaps menu, no need to auto-add one through the CPT.
				'supports'     => array(
					'title',
					),
				)
			);
	}

	/**
	 * Get posts modified within the last hour
	 * @return object[] modified posts
	 */
	public static function get_last_modified_posts() {
		global $wpdb;

		$sitemap_last_run = get_option( 'msm_sitemap_update_last_run', false );
		
		$date = date( 'Y-m-d H:i:s', ( current_time( 'timestamp', 1 ) - 3600 ) ); // posts changed within the last hour

		if ( $sitemap_last_run ) {
			$date = date( 'Y-m-d H:i:s', $sitemap_last_run );
		}

		$post_types_in = self::get_supported_post_types_in();

		$modified_posts = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date FROM $wpdb->posts WHERE post_type IN ( {$post_types_in} ) AND post_modified_gmt >= %s ORDER BY post_date LIMIT 1000", $date ) );
		return $modified_posts;
	}

	/**
	 * Get dates for an array of posts
	 * @param object[] $posts
	 * @return string[] unique dates of each post.
	 */
	public static function get_post_dates( $posts ) {
		$dates = array();
		foreach ( $posts as $post ) {
			$dates[] = date( 'Y-m-d', strtotime( $post->post_date ) );
		}
		$dates = array_unique( $dates );

		return $dates;
	}

	/**
	 * Update the sitemap with changes from recently modified posts
	 */
	public static function update_sitemap_from_modified_posts() {
		$time = current_time( 'timestamp', 1 );
		$last_modified_posts = self::get_last_modified_posts();
		$dates = self::get_post_dates( $last_modified_posts );

		foreach ( $dates as $date ) {
			list( $year, $month, $day ) = explode( '-', $date );

			$time += MSM_INTERVAL_PER_GENERATION_EVENT;

			do_action( 'msm_update_sitemap_for_year_month_date', array( $year, $month, $day ), $time );
		}
		update_option( 'msm_sitemap_update_last_run', current_time( 'timestamp', 1 ) );
	}

	/**
	 * Trigger rendering of the actual sitemap
	 */
	public static function load_sitemap_template( $template ) {
		if ( get_query_var( 'sitemap' ) === 'true' ) {
			$template = dirname( __FILE__ ) . '/templates/full-sitemaps.php';
		}
		return $template;
	}


	/**
	 * Build Root sitemap XML - currently all days
	 */
	public static function build_root_sitemap_xml() {

		$xml_prefix = '<?xml version="1.0" encoding="utf-8"?>';
		global $wpdb;
		// Direct query because we just want dates of the sitemap entries and this is much faster than WP_Query
		$sitemaps = $wpdb->get_col( $wpdb->prepare( "SELECT post_date FROM $wpdb->posts WHERE post_type = %s ORDER BY post_date DESC LIMIT 10000", Metro_Sitemap::SITEMAP_CPT ) );

		$xml = new SimpleXMLElement( $xml_prefix . '<sitemapindex xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>' );
		foreach ( $sitemaps as $sitemap_date ) {
			$sitemap_time = strtotime( $sitemap_date );
			$sitemap_url = add_query_arg(
				array(
					'yyyy' => date( 'Y', $sitemap_time ),
					'mm' => date( 'm', $sitemap_time ),
					'dd' => date( 'd', $sitemap_time ),
				),
				home_url( '/sitemap.xml' )
			);

			$sitemap = $xml->addChild( 'sitemap' );
			$sitemap->loc = $sitemap_url; // manually set the child instead of addChild to prevent "unterminated entity reference" warnings due to encoded ampersands http://stackoverflow.com/a/555039/169478
		}
		return $xml->asXML();
	}

	public static function get_sitemap_post_id( $year, $month, $day ) {
		$sitemap_args = array(
			'year' => $year,
			'monthnum' => $month,
			'day' => $day,
			'orderby' => 'ID',
			'order' => 'ASC',
			'posts_per_page' => 1,
			'fields' => 'ids',
			'post_type' => self::SITEMAP_CPT,
			'no_found_rows' => true,
			'update_term_cache' => false,
			'suppress_filters' => false,
		);

		$sitemap_query = get_posts( $sitemap_args );

		if ( ! empty( $sitemap_query ) ) {
			return $sitemap_query[0];
		}

		return false;
	}

	/**
	 * Get XML for individual day
	 */
	public static function build_individual_sitemap_xml( $year, $month, $day ) {
		
		// Get XML for an individual day. Stored as full xml
		$sitemap_id = self::get_sitemap_post_id( $year, $month, $day );

		if ( $sitemap_id ) {
			$sitemap_content = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );
			// Return is now as it should be valid xml!
			return $sitemap_content;
		} else {
			/* There are no posts for this day */
			$xml = new SimpleXMLElement( '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>' );
			return $xml->asXML();
		}
	}

	/**
	 * Build XML for output to clean up the template file
	 */
	public static function build_xml( $request = array() ) {

		$year = $request['year'];
		$month = $request['month'];
		$day = $request['day'];

		if ( false === $year && false === $month && false === $day ) {
			$xml = self::build_root_sitemap_xml();
		} else if ( $year > 0 && $month > 0 && $day > 0 ) {
			$xml = self::build_individual_sitemap_xml( $year, $month, $day );
		} else {
			/* Invalid options sent */
			return false;
		}
		return $xml;
	}

	public static function find_valid_days( $year ) {
		$days = 31;
		if ( $m == 2 ) {
			$days = date( 'L', strtotime( $year . '-01-01' ) ) ? 29 : 28;  // leap year
		} elseif ( $m == 4 || $m == 6 || $m == 9 || $m == 11 ) {
			$days = 30;
		}

		if ( $m == date( 'm' ) ) {
			$days = date( 'd' );
		}

		return $days;
	}

	public static function get_supported_post_types() {
		return apply_filters( 'msm_sitemap_entry_post_type', array( 'post' ) );
	}

	private static function get_supported_post_types_in() {
		global $wpdb;

		$post_types_in = '';
		$post_types = self::get_supported_post_types();
		$post_types_prepared = array();

		foreach ( $post_types as $post_type ) {
			$post_types_prepared[] = $wpdb->prepare( '%s', $post_type );
		}

		return implode( ', ', $post_types_prepared );
	}
}

add_action( 'after_setup_theme', array( 'Metro_Sitemap', 'setup' ) );
