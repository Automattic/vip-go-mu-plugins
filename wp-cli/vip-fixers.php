<?php

class VIP_Go_OneTimeFixers_Command extends WPCOM_VIP_CLI_Command {
	public function enforce_govip_ssl( $args, $assoc_args ) {
		$results = [];

		if ( is_multisite() ) {
			$site_ids = get_sites( [
				'number' => 99999,
				'fields' => 'ids',
			] );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( $site_id );

				$old_home_url = home_url();

				$updated = $this->enforce_govip_ssl_for_current_site();

				$results[ $old_home_url ] = [
					// Get from database to avoid set_url_scheme and filters
					'home' => $this->get_raw_option_value( 'home' ),
					'siteurl' => $this->get_raw_option_value( 'siteurl' ),
					'updated' => $updated,
				];

				refresh_blog_details();
				restore_current_blog();
			}

			// Flush the cache one more time just to be safe
			wp_cache_flush();
		} else {
			$old_home_url = home_url();

			$updated = $this->enforce_govip_ssl_for_current_site();

			$results[ $old_home_url ] = [
				// Get from database to avoid set_url_scheme and filters
				'home' => $this->get_raw_option_value( 'home' ),
				'siteurl' => $this->get_raw_option_value( 'siteurl' ),
				'updated' => $updated,
			];
		}

		foreach ( $results as $old_home_url => $result ) {
			$home = $result['home'];
			$siteurl = $result['siteurl'];
			list( $updated_home, $updated_siteurl ) = $result['updated'];

			$message = sprintf(
				'Finished for %s; home updated? %s (%s) | siteurl updated? %s (%s)',
				$old_home_url,
				var_export( $updated_home, true ),
				$home,
				var_export( $updated_siteurl, true ),
				$siteurl
			);

			WP_CLI::log( '#vip-go-https-cleanup: ' . $message );

			wpcom_vip_irc( '#vip-go-https-cleanup', $message );
		}
	}

	private function enforce_govip_ssl_for_current_site() {
		$updated_home = $this->enforce_govip_ssl_for_option_url( 'home' );
		$updated_siteurl = $this->enforce_govip_ssl_for_option_url( 'siteurl' );

		if ( $updated_home || $updated_siteurl ) {
			wp_cache_flush();
		}

		return [ $updated_home, $updated_siteurl ];
	}

	private function enforce_govip_ssl_for_option_url( $option ) {
		// Get from database to avoid set_url_scheme and filters
		$url = $this->get_raw_option_value( $option );

		$parsed_url = parse_url( $url );

		if ( ! wp_endswith( $parsed_url['host'], '.go-vip.co' ) ) {
			return false;
		}

		if ( 'http' !== $parsed_url['scheme'] ) {
			return false;
		}

		$new_url = preg_replace( '/^http:/i', 'https:', $url );
		return update_option( $option, $new_url );
	}

	private function get_raw_option_value( $option ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s", $option ) );
	}
}

WP_CLI::add_command( 'vip fixers', 'VIP_Go_OneTimeFixers_Command' );
