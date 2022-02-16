<?php

use Automattic\VIP\Utils\Alerts;

/**
 * Wrapper for getting related posts. The feature Related_posts must be active.
 * 
 * @param $post_id Post ID
 * @param $return Posts per page to return. Defaults to 5.
 * 
 * @return array|bool
 */
function vip_es_get_related_posts( $post_id, $return = 5 ) {
	return ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id, $return );
}

/**
 * Wrapper for backfilling an EP option if it doesn't exist on a per-site basis, but exists on a network one.
 * 
 * @param $value array|bool Pass in per-site option value.
 * @param $option string Pass in per-site option.
 * 
 * @return $value array|bool Option value to return.
 */
function vip_maybe_backfill_ep_option( $value, $option ) {
	if ( empty( $value ) ) {
		$site_option_value = get_site_option( $option );
		if ( ! empty( $site_option ) ) {
			$option_added = add_option( $option, $site_option_value );

			$blog_id  = get_current_blog_id();
			$home_url = home_url();
			if ( $option_added ) {
				\Automattic\VIP\Logstash\log2logstash(
					array(
						'severity' => 'info',
						'feature'  => 'search_ep_option',
						'message'  => "Successfully added {$option} option to subsite.",
						'extra'    => [
							'homeurl' => $home_url,
							'blog_id' => $blog_id,
							'option'  => wp_json_encode( $site_option ),
						],
					)
				);
			} else {
				Alerts::chat( '#vip-go-es-alerts', "Unsuccessfully added option {$option} to subsite {$blog_id}: {$home_url}" );
			}

			return $site_option_value;
		}
	}

	return $value;
}
