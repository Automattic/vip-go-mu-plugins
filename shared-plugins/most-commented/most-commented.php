<?php
/*
Plugin Name: Most Commented Widget
Plugin URI: http://wordpress.org/extend/plugins/most-commented/
Description: Widget to display posts/pages with the most comments.
Version: 2.1.1-wpcom (only mod is cache of 10 min instead of 18 sec)
Author: Nick Momrik
Author URI: http://nickmomrik.com/
*/

class Most_Commented_Widget extends WP_Widget {
	var $duration_choices = array();

	function Most_Commented_Widget() {
		parent::WP_Widget( false, $name = 'Most Commented' );
		$this->duration_choices = apply_filters( 'most_commented_duration_choices', array( 1 => __( '1 Day' ), 7 => __( '7 Days' ), 30 => __( '30 Days' ), 365 => __( '365 Days' ), 0 => __( 'All Time' ) ) );
	}

	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$show_pass_post = (bool)$instance['show_pass_post'];
		$duration = intval( $instance['duration'] );
		if ( !isset( $this->duration_choices[ $duration ] ) )
			$duration = 0;
		$num_posts = intval( $instance['num_posts'] );
		if ( $num_posts < 1 )
			$num_posts = 5;
		$post_type = $instance['post_type'];
		if ( !in_array( $post_type, array( 'post', 'page', 'both' ) ) )
			$post_type = 'both';
		if ( array_key_exists( 'echo', $instance ) )
			$echo = $instance['echo'];
		else
			$echo = true;
		if ( array_key_exists( 'before', $instance ) ) {
			$before = $instance['before'];
			$after = $instance['after'];
		} else {
			$before = '<li>';
			$after = '</li>';
		}

		global $wpdb;

		if ( ! $output = wp_cache_get( $widget_id ) ) {
			$request = "SELECT ID, post_title, comment_count FROM $wpdb->posts WHERE comment_count > 0 AND post_status = 'publish'";
			if ( !$show_pass_post )
				$request .= " AND post_password = ''";
			if ( 'both' != $post_type )
				$request .= " AND post_type = '$post_type'";
			if ( $duration > 0 )
				$request .= " AND DATE_SUB(CURDATE(), INTERVAL $duration DAY) < post_date";
			$request .= " ORDER BY comment_count DESC LIMIT $num_posts";

			$posts = $wpdb->get_results( $request );

			if ( $echo ) {
				$output = '';

				if ( !empty( $posts ) ) {
					foreach ( $posts as $post ) {
						$post_title = apply_filters( 'the_title', $post->post_title );

						$output .= $before . '<a href="' . get_permalink( $post->ID ) . '" title="' . esc_attr( $post_title ) . '">' . $post_title . '</a> (' . $post->comment_count .')' . $after;
					}
				} else {
					$output .= $before . 'None found' . $after;
				}

				if ( !array_key_exists( 'not_widget', $instance ) ) {
					if ( $title )
						$title = $before_title . $title . $after_title;

					$output = $before_widget . $title . '<ul>' . $output . '</ul>' . $after_widget;
				}
			} else {
				$output = $posts;
			}

			wp_cache_set( $widget_id, $output, '', 600 );
		}

		if ( $echo )
			echo $output;
		else
			return $output;
	}

	function update( $new_instance, $old_instance ) {
		$new_instance['show_pass_post'] = isset( $new_instance['show_pass_post'] );

		wp_cache_delete( $this->id );

		return $new_instance;
	}

	function form( $instance ) {
		$title = esc_attr( $instance['title'] );
		$show_pass_post = (bool)$instance['show_pass_post'];
		$duration = intval( $instance['duration'] );
		if ( !isset( $this->duration_choices[ $duration ] ) )
			$duration = 0;
		$num_posts = intval( $instance['num_posts'] );
		if ( $num_posts < 1 )
			$num_posts = 5;
		$post_type = $instance['post_type'];
		if ( !in_array( $post_type, array( 'post', 'page', 'both' ) ) )
			$post_type = 'both';
        ?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
		<p><label for="<?php echo $this->get_field_id( 'post_type' ); ?>"><?php _e( 'Display:' ); ?>
		<select id="<?php echo $this->get_field_id( 'post_type' ); ?>" name="<?php echo $this->get_field_name( 'post_type' ); ?>">
		<?php
			$post_type_choices = array( 'post' => __( 'Posts' ), 'page' => __( 'Pages' ), 'both' => __( 'Posts & Pages' ) );
			foreach ( $post_type_choices as $post_type_value => $post_type_text ) {
				echo "<option value='$post_type_value' " . ( $post_type == $post_type_value ? "selected='selected'" : '' ) . ">$post_type_text</option>\n";
			}
		?>
		</select>
		</label></p>
		<p><label for="<?php echo $this->get_field_id( 'num_posts' ); ?>"><?php _e( 'Maximum number of results:' ); ?>
		<select id="<?php echo $this->get_field_id('num_posts'  ); ?>" name="<?php echo $this->get_field_name( 'num_posts' ); ?>">
		<?php
			for ( $i = 1; $i <= 20; ++$i ) {
				echo "<option value='$i' " . ( $num_posts == $i ? "selected='selected'" : '' ) . ">$i</option>\n";
			}
		?>
		</select>
		</label></p>
		<p><label for="<?php echo $this->get_field_id( 'duration' ); ?>"><?php _e( 'Limit to:' ); ?>
		<select id="<?php echo $this->get_field_id( 'duration' ); ?>" name="<?php echo $this->get_field_name( 'duration' ); ?>">
		<?php
			foreach ( $this->duration_choices as $duration_num => $duration_text ) {
				echo "<option value='$duration_num' " . ( $duration == $duration_num ? "selected='selected'" : '' ) . ">$duration_text</option>\n";
			}
		?>
		</select>
		</label></p>
		<p><label for="<?php echo $this->get_field_id( 'show_pass_post' ); ?>"><input id="<?php echo $this->get_field_id( 'show_pass_post' ); ?>" class="checkbox" type="checkbox" name="<?php echo $this->get_field_name( 'show_pass_post' ); ?>"<?php echo checked( $show_pass_post ); ?> /> <?php _e( 'Include password protected posts/pages' ); ?></label></p>
        <?php 
	}

}

add_action( 'widgets_init', create_function( '', 'return register_widget( "Most_Commented_Widget" );' ) );

if ( !function_exists( 'mdv_most_commented' ) ) {
	function mdv_most_commented( $num_posts = 5, $before = '<li>', $after = '</li>', $show_pass_post = false, $duration = 0, $echo = true, $post_type = 'both' ) {
		$options = array(
			'num_posts' => $num_posts,
			'before' => $before,
			'after' => $after,
			'show_pass_post' => $show_pass_post,
			'duration' => $duration,
			'echo' => $echo,
			'post_type' => $post_type,
			'not_widget' => true
			);
		$args = array( 'widget_id' => 'most_commented_widget_' . md5( var_export( $options, true ) ) );
		$most_commented = new Most_Commented_Widget();
		
		if ( $echo )
			$most_commented->widget( $args, $options );
		else
			return $most_commented->widget( $args, $options );
	}
}
