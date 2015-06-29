<?php
/**
 * When users go to write their posts, they'll see a new section for A/B testing headlines. 
 * This section will include inputs for users to write alternate headlines and a button to create the experiment.
 * We also use several hidden input fields to store data about the project and experiment.
 * These are used in edit.js to send AJAX requests to the Optimizely API.
 */
/**
 * Return the full permalink of the current post.
 * @return string
 */
function get_full_permalink() {
	$permalinkArray = get_sample_permalink( $post->ID );
	$permalinkTemplate = array_values( $permalinkArray );
	$permalinkSlug = array_values( $permalinkArray );
	
	return str_replace( '%postname%', $permalinkSlug[1], $permalinkTemplate[0] );
}
/**
 * Add the meta box for title variations.
 */
function optimizely_title_variations_add() {
	// Only add the module if the current post type is one the user selected in the admin tab
	if ( optimizely_is_post_type_enabled( get_post_type() ) ) {
		add_meta_box( 'optimizely-headlines', 'A/B Test Headlines', 'optimizely_title_variations_render', get_post_type(), 'side', 'high' );
	}
    
}
add_action( 'add_meta_boxes', 'optimizely_title_variations_add' );
/**
 * Render the meta box to add title variations.
 * @param WP_Post $post
 */
function optimizely_title_variations_render( $post ) {
	// Check if we can create experiments
	if ( ! optimizely_can_create_experiments() ) {
		?>
		<p><?php sprintf(
			'%s <a href="%s">%s</a>',
			esc_html__( 'Please configure your API credentials in the', 'optimizely' ),
			esc_url( menu_page_url( 'optimizely-config', false ) ),
			esc_html__( 'Optimizely settings page', 'optimizely' )
		); ?>.</p>
		<?php
		return;
	}
	$titles = array();
	$contents = '';
	$num_variations = get_option( 'optimizely_num_variations', OPTIMIZELY_NUM_VARIATIONS );
	for ( $i = 1; $i <= $num_variations; $i++ ) {
		$meta_key = optimizely_meta_key( $i );
		$titles[ $i ] = get_post_meta( $post->ID, $meta_key, true );
		echo '<p>';
		echo sprintf(
			'<label for="%s">%s #%u</label><br>',
			esc_attr( $meta_key ),
			esc_html__( 'Variation', 'optimizely' ),
			absint( $i )
		);
		echo sprintf(
			'<input type="text" name="%s" id="%s" class="optimizely_variation" placeholder="%s %u" value="%s">',
			esc_attr( $meta_key ),
			esc_attr( $meta_key ),
			esc_html__( 'Title', 'optimizely' ),
			absint( $i ),
			esc_attr( $titles[ $i ] )
		);
		echo '</p>';
	}
	?>

	<input type="hidden" id="optimizely_token" value="<?php echo esc_attr( get_option( 'optimizely_token' ) )?>" />
	<input type="hidden" id="optimizely_project_id" value="<?php echo esc_attr( get_option('optimizely_project_id') ) ?>" />
	<input type="hidden" id="optimizely_experiment_id" name="optimizely_experiment_id" value="<?php echo esc_attr( get_post_meta( $post->ID, 'optimizely_experiment_id', true ) ) ?>" />
	<input type="hidden" id="optimizely_experiment_status" name="optimizely_experiment_status" value="<?php echo esc_attr( get_post_meta( $post->ID, 'optimizely_experiment_status', true ) ) ?>" />
	<input type="hidden" id="optimizely_experiment_url" name="optimizely_experiment_url" value="<?php echo esc_url( get_full_permalink() ) ?>" />
	<input type="hidden" id="optimizely_url_targeting" name="optimizely_url_targeting" value="<?php echo esc_attr( get_option( 'optimizely_url_targeting' ) ) ?>" />
	<input type="hidden" id="optimizely_url_targeting_type" name="optimizely_url_targeting_type" value="<?php echo esc_attr( get_option( 'optimizely_url_targeting_type' ) ) ?>" />
	<input type="hidden" id="optimizely_activation_mode" name="optimizely_activation_mode" value="<?php echo esc_attr( get_option( 'optimizely_activation_mode' ) ) ?>" />
	<textarea id="optimizely_variation_template" style="display: none"><?php echo esc_attr( get_option( 'optimizely_variation_template' ) ) ?></textarea>
	<textarea id="optimizely_conditional_activation_code" style="display: none"><?php echo esc_attr( get_option( 'optimizely_conditional_activation_code' ) ) ?></textarea>

	<?php if(get_post_status($post->ID) == 'publish'): ?>
		<div id="optimizely_not_created">
			<a id="optimizely_create" class="button-primary"><?php esc_html_e( 'Create Experiment', 'optimizely' ) ?></a>
		</div>
		<div id="optimizely_created">
			<a id="optimizely_toggle_running" class="button-primary"><?php esc_html_e( 'Start Experiment', 'optimizely' ) ?></a>	
			<p></p>
			<a id="optimizely_view" class="button" target="_blank"><?php esc_html_e( 'View on Optimizely', 'optimizely' ) ?></a>
			<p><?php esc_html_e( 'Status', 'optimizely' ) ?>: <b id="optimizely_experiment_status_text"><?php echo esc_html( get_post_meta( $post->ID, 'optimizely_experiment_status', true ) ) ?></b>
			<br />
			<?php esc_html_e( 'Results', 'optimizely' ) ?>: <a href="<?php echo esc_url( menu_page_url( 'optimizely-config', false ) ) ?>" id="optimizely_results" target="_blank"><?php esc_html_e( 'View Results', 'optimizely' ) ?></a></p>
		</div>
	<?php else:	?>
		<p><?php esc_html_e( 'You must first publish this post before creating the experiment on Optimizely', 'optimizely' ) ?></p>
	<?php endif; ?>

	<?php
}
/**
 * Save the title variations.
 * @param int $post_id
 */
function optimizely_title_variations_save( $post_id ) {
	// Ensure the current user can save posts
	if ( ! optimizely_is_post_type_enabled( get_post_type( $post_id ) ) ) {
		return;
	}
	// Save the variations
	$num_variations = get_option( 'optimizely_num_variations', OPTIMIZELY_NUM_VARIATIONS );
	for ( $i = 1; $i <= $num_variations; $i++ ) {
		$meta_key = optimizely_meta_key( $i );
		if ( isset( $_POST[ $meta_key ] ) ) {
			// Save titles
			$new_title = sanitize_text_field( $_POST[ $meta_key ] );
			update_post_meta( $post_id, $meta_key, $new_title );
		}
	}
	if ( isset( $_POST['optimizely_experiment_id'] ) ) {	
		update_post_meta( $post_id, 'optimizely_experiment_id', sanitize_text_field( $_POST['optimizely_experiment_id'] ) );
		update_post_meta( $post_id, 'optimizely_experiment_status', sanitize_text_field( $_POST['optimizely_experiment_status'] ) );
	}
}
add_action( 'save_post', 'optimizely_title_variations_save' );
/**
 * Update experiment meta on an AJAX request.
 * @param int $post_id
 */
function optimizely_update_experiment_meta() {
	if ( isset( $_POST['post_id'] ) ) {
		optimizely_title_variations_save( absint( $_POST['post_id'] ) );
	}
	
	exit;
}
add_action( 'wp_ajax_update_experiment_meta', 'optimizely_update_experiment_meta' );
/**
 * Update the post title on an AJAX request for the winner of the test.
 * @param int $post_id
 */
function optimizely_update_post_title() {
	if ( isset( $_POST['post_id'] ) && isset( $_POST['title'] ) ) {
		$post_id = absint( $_POST['post_id'] );
		$winning_var_title = sanitize_text_field( $_POST['title'] );
		wp_update_post( array(
			'ID' => $post_id,
			'post_title' => $winning_var_title
		) );
	}
	
	exit;
}
add_action( 'wp_ajax_update_post_title', 'optimizely_update_post_title' );
/**
 * Check if this is a post type that uses Optimizely.
 * @param string $post_type
 * @return boolean
 */
function optimizely_is_post_type_enabled( $post_type ) {
	$selected_post_types = explode( ',', get_option( 'optimizely_post_types' ) );
	if ( ! empty( $selected_post_types ) && in_array( $post_type, $selected_post_types ) ) {
		return true;
	} else {
		return false;
	}
}
/**
 * Return the meta key format used for all post title variations.
 * @param int $i
 * @return string
 */
function optimizely_meta_key( $i ) {
	return 'post_title' . absint( $i );
}
?>