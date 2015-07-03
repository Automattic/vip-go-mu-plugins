<?php
/**
 * ACM custom widget to choose ad tags to display in widget areas.
 *
 * @since v0.2
 */

class ACM_Ad_Zones extends WP_Widget {

	// Process the new widget
	function ACM_Ad_Zones() {
		$widget_ops = array(
			'classname' => 'acm_ad_zones',
			'description' => __( 'Display an Ad Code Manger ad zone within a widget area', 'ad-code-manager' ),
		);
		$this->WP_Widget( 'ACM_Ad_Zones', __( 'Ad Code Manager Ad Zone', 'ad-code-manager' ), $widget_ops );
	}

	// Build the widget settings form
	function form( $instance ) {
		$defaults = array(
			'title' => '',
			'ad_zone' => '',
		);
		$instance = wp_parse_args( (array) $instance, $defaults );
		$title = $instance['title'];
		$zone = $instance['ad_zone'];
		global $ad_code_manager;
?>
			<p><label>Title: <input class="widefat" name="<?php echo $this->get_field_name( 'title' ); ?>"  type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>

			<p>
			<?php if ( !empty( $ad_code_manager->ad_codes ) ): ?>
			<label for="<?php echo $this->get_field_id( 'ad_zone' ); ?>">Choose Ad Zone</label>
			<select id="<?php echo $this->get_field_id( 'ad_zone' ); ?>" name="<?php echo $this->get_field_name( 'ad_zone' ); ?>">
				<?php
		foreach ( $ad_code_manager->ad_codes as $key => $value ) {
?>
					<option value="<?php echo $key; ?>" <?php selected( $key, $zone ); ?>><?php echo $key; ?></option>
					<?php
		}
?>
			</select>
			<?php else: ?>
			<?php $create_url = add_query_arg( 'page', $ad_code_manager->plugin_slug, admin_url( 'tools.php' ) ); ?>
			<span class="description"><?php echo sprintf( __( "No ad codes have been added yet. <a href='%s'>Please create one</a>.", 'ad-code-manager' ), $create_url ); ?></span>
			<?php endif; ?>
			</p>

		<?php
	}

	// Save the widget settings
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['ad_zone'] = sanitize_text_field( $new_instance['ad_zone'] );
		return $instance;
	}

	// Display the widget
	function widget( $args, $instance ) {
		extract( $args );
		echo $before_widget;
		$title = apply_filters( 'widget_title', $instance['title'] );

		if ( !empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}
		do_action( 'acm_tag', $instance['ad_zone'] );
		echo $after_widget;
	}
}
