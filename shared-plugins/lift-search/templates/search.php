<?php
$to_from = 0;
if ( $wp_query->current_post > -1 ) {
	if ( !($offset = $wp_query->get( 'offset' )) ) {
		$page = absint( $wp_query->get( 'paged' ) );
		if ( !$page )
			$page = 1;

		$offset = ($page - 1) * $wp_query->get( 'posts_per_page' );
	}

	$to_from = ($offset + 1) . " - " . ( $offset + $wp_query->post_count );
}
?>
<?php get_header(); ?>
<div class="lift-search">
	<?php lift_search_form(); ?>
	<div class="lift-filter-list">
		<h2>Results <?php echo esc_html( $to_from ); ?> of <?php echo esc_html( $wp_query->found_posts ); ?></h2>
	</div>
	<?php lift_loop(); ?>
</div> <!-- end lift search -->
<?php get_footer(); ?>