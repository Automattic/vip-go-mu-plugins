<?php

add_filter( 'msm_sitemap_use_cron_builder', '__return_false', 9999 ); // On WP.com we're going to use the jobs system

if ( function_exists( 'queue_async_job' ) ) {
	add_action( 'msm_update_sitemap_for_year_month_date', 'msm_wpcom_schedule_sitemap_update_for_year_month_date', 10, 2 );
	add_action( 'msm_insert_sitemap_post', 'msm_sitemap_wpcom_queue_cache_invalidation', 10, 4 );
	add_action( 'msm_delete_sitemap_post', 'msm_sitemap_wpcom_queue_cache_invalidation', 10, 4 );
	add_action( 'msm_update_sitemap_post', 'msm_sitemap_wpcom_queue_cache_invalidation', 10, 4 );
}

function msm_wpcom_schedule_sitemap_update_for_year_month_date( $date, $time ) {
	$data = (object) array( 'date' => $date );
	queue_async_job( $data, 'vip_async_generate_sitemap' );
}

/**
 * Queue action to invalidate nginx cache if on WPCOM
 * @param int $sitemap_id
 * @param string $year
 * @param string $month
 * @param string $day
 */
function msm_sitemap_wpcom_queue_cache_invalidation( $sitemap_id, $year, $month, $day ) {
	$sitemap_url = home_url( '/sitemap.xml' );

	$sitemap_urls = array(
		$sitemap_url,
		add_query_arg( array( 'yyyy' => $year, 'mm' => $month, 'dd' => $day ), $sitemap_url ),
	);

	queue_async_job( array( 'output_cache' => array( 'url' => $sitemap_urls ) ), 'wpcom_invalidate_output_cache_job', -16 );
}
