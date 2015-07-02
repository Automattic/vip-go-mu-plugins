<?php

// TODO: reduce some of the duplication between the CLI commands and the main class

WP_CLI::add_command( 'msm-sitemap', 'Metro_Sitemap_CLI' );

class Metro_Sitemap_CLI extends WP_CLI_Command {
	/**
	 * @var string Type of command triggered so we can keep track of killswitch cleanup.
	 */
	private $command = '';

	/**
	 * @var bool Flag whether or not execution should be stopped.
	 */
	private $halt = false;

	/**
	 * Generate full sitemap for site
	 *
	 * @subcommand generate-sitemap
	 */
	function generate_sitemap( $args, $assoc_args ) {
		$this->command = 'all';

		$all_years_with_posts = Metro_Sitemap::check_year_has_posts();

		$sitemap_args = array();
		foreach ( $all_years_with_posts as $year ) {
			if ( $this->halt_execution() ) {
				delete_option( 'msm_stop_processing' );
				break;
			}

			$sitemap_args['year'] = $year;
			$this->generate_sitemap_for_year( array(), $sitemap_args );
		}
	}

	/**
	 * Generate sitemap for a given year
	 *
	 * @subcommand generate-sitemap-for-year
	 */
	function generate_sitemap_for_year( $args, $assoc_args ) {
		if ( empty( $this->command ) )
			$this->command = 'year';

		$assoc_args = wp_parse_args( $assoc_args, array(
			'year' => false,
		) );

		$year = intval( $assoc_args['year'] );

		$valid = $this->validate_year( $year );
		if ( is_wp_error( $valid ) )
			WP_CLI::error( $valid->get_error_message() );

		WP_CLI::line( sprintf( 'Generating sitemap for %s', $year ) );

		$max_month = 12;
		if ( date( 'Y' ) == $year ) {
			$max_month = date( 'n' );
		}

		$months = range( 1, $max_month );

		foreach ( $months as $month ) {
			if ( $this->halt_execution() ) {
				if ( 'year' === $this->command )
					delete_option( 'msm_stop_processing' );

				break;
			}

			$assoc_args['month'] = $month;
			$this->generate_sitemap_for_year_month( $args, $assoc_args );
		}
	}

	/**
	 * @subcommand generate-sitemap-for-year-month
	 */
	function generate_sitemap_for_year_month( $args, $assoc_args ) {
		if ( empty( $this->command ) )
			$this->command = 'month';

		$assoc_args = wp_parse_args( $assoc_args, array(
			'year' => false,
			'month' => false,
		) );

		$year = intval( $assoc_args['year'] );
		$month = intval( $assoc_args['month'] );

		$valid = $this->validate_year_month( $year, $month );
		if ( is_wp_error( $valid ) )
			WP_CLI::error( $valid->get_error_message() );

		WP_CLI::line( sprintf( 'Generating sitemap for %s-%s', $year, $month ) );

		// Calculate actual number of days in the month since we don't have cal_days_in_month available
		$max_days = 31;

		if ( date( 'Y' ) == $year && date( 'n' ) == $month ) {
			$max_days = date( 'j' );
		}

		$days = range( 1, $max_days );

		foreach ( $days as $day ) {
			if ( $this->halt_execution() ) {
				if ( 'month' === $this->command )
					delete_option( 'msm_stop_processing' );

				break;
			}

			$assoc_args['day'] = $day;
			$this->generate_sitemap_for_year_month_day( $args, $assoc_args );
		}
	}


	/**
	 * @subcommand generate-sitemap-for-year-month-day
	 */
	function generate_sitemap_for_year_month_day( $args, $assoc_args ) {
		if ( empty( $this->command ) )
			$this->command = 'day';

		$assoc_args = wp_parse_args( $assoc_args, array(
			'year' => false,
			'month' => false,
			'day' => false,
		) );

		$year = intval( $assoc_args['year'] );
		$month = intval( $assoc_args['month'] );
		$day = intval( $assoc_args['day'] );
		
		$valid = $this->validate_year_month_day( $year, $month, $day );
		if ( is_wp_error( $valid ) )
			WP_CLI::error( $valid->get_error_message() );

		WP_CLI::line( sprintf( 'Generating sitemap for %s-%s-%s', $year, $month, $day ) );

		$date_stamp = Metro_Sitemap::get_date_stamp( $year, $month, $day );
		if ( Metro_Sitemap::date_range_has_posts( $date_stamp, $date_stamp ) ) {
			Metro_Sitemap::generate_sitemap_for_date( $date_stamp ); // TODO: simplify; this function should accept the year, month, day and translate accordingly
		}
	}

	private function validate_year( $year ) {
		if ( $year > date( 'Y' ) )
			return new WP_Error( 'msm-invalid-year', __( 'Please specify a valid year', 'metro-sitemap' ) );

		return true;
	}

	private function validate_year_month( $year, $month ) {
		$valid_year = $this->validate_year( $year );
		if ( is_wp_error( $valid_year ) )
			return $valid_year;

		if ( $month < 1 || $month > 12 )
			return new WP_Error( 'msm-invalid-month', __( 'Please specify a valid month', 'metro-sitemap' ) );

		return true;
	}

	private function validate_year_month_day( $year, $month, $day ) {
		$valid_year_month = $this->validate_year_month( $year, $month );
		if ( is_wp_error( $valid_year_month ) )
			return $valid_year_month;

		$date = strtotime( sprintf( '%d-%d-%d', $year, $month, $day ) );
		if ( false === $date )
			return new WP_Error( 'msm-invalid-day', __( 'Please specify a valid day', 'metro-sitemap' ) );

		return true;
	}


	/**
	 * @subcommand recount-indexed-posts
	 */
	public function recount_indexed_posts() {

		$all_sitemaps = get_posts(
			array(
				'post_type' => Metro_Sitemap::SITEMAP_CPT,
				'post_status' => 'publish',
				'fields' => 'ids',
				'suppress_filters' => false,
				'posts_per_page' => -1,
			)
		);

		$total_count = 0;
		$sitemap_count = 0;

		foreach ( $all_sitemaps as $sitemap_id ) {

			$xml_data = get_post_meta( $sitemap_id, 'msm_sitemap_xml', true );

			$xml = simplexml_load_string( $xml_data );
			$count = count( $xml->url );
			update_post_meta( $sitemap_id, 'msm_indexed_url_count', $count );

			$total_count += $count;
			$sitemap_count += 1;
		}

		update_option( 'msm_sitemap_indexed_url_count', $total_count );
		WP_CLI::line( sprintf( 'Total posts found: %s', $total_count ) );
		WP_CLI::line( sprintf( 'Number of sitemaps found: %s', $sitemap_count ) );

	}

	/**
	 * Check if the user has flagged to bail on sitemap generation.
	 *
	 * Once `$this->halt` is set, we take advantage of PHP's boolean operator to stop querying the option in hopes of
	 * limiting database interaction.
	 *
	 * @return bool
	 */
	private function halt_execution() {
		if ( $this->halt || get_option( 'msm_stop_processing' ) ) {
			// Allow user to bail out of the current process, doesn't remove where the job got up to
			delete_option( 'msm_sitemap_create_in_progress' );
			$this->halt = true;
			return true;
		}

		return false;
	}
}
