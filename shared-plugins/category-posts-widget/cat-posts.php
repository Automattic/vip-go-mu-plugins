<?php
/*
Plugin Name: Category Posts Widget
Plugin URI: http://jameslao.com/
Description: Adds a widget that can display a specified number of posts from a single category. Can also set how many widgets to show.
Author: James Lao	
Version: 1.3.2-WPCOM
Author URI: http://jameslao.com/2008/04/18/category-posts-widget-13/

07/08/2008 - Modified by Automattic to add cacheing
*/

// Displays widget on blag
// $widget_args: number
//    number: which of the several widgets of this type do we mean
function jl_cat_posts_widget( $args, $widget_args = 1 ) {
	extract( $args, EXTR_SKIP );
	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );
	
	// Data should be stored as array:  array( number => data for that instance of the widget, ... )
	$options = get_option('widget_cat_posts');
	if ( !isset($options[$number]) )
		return;

	$options[$number] = apply_filters( 'jl_cat_posts_widget_options', $options[$number], $args );

	$cat_id = empty($options[$number]['cat']) ? 1 : $options[$number]['cat'];
	$title_link = (bool) $options[$number]['title_link'];
	$excerpt = (bool) $options[$number]['excerpt'];
	$num = $options[$number]['num']; // Number of posts to show.
	
	// If not title, use the name of the category.
	if( empty($options[$number]['title']) ) {
		$category_info = get_category($cat_id);
		$title = $category_info->name;
	} else {
		$title = $options[$number]['title'];
	}
	$cache_key = 'jl_cat_posts_widget-' . $cat_id;
	$jl_cat_posts_widget = wp_cache_get($cache_key, 'widget');
	if($jl_cat_posts_widget == false) {
		// Get array of post info.
		$cat_posts = get_posts('numberposts='.$num.'&category='.$cat_id);
	
		$jl_cat_posts_widget = $before_widget;
		$jl_cat_posts_widget .= $before_title;

		if( $title_link ) {
			$jl_cat_posts_widget .= '<a href="' . get_category_link($cat_id) . '">' . $title . '</a>';
		} else {
			$jl_cat_posts_widget .= $title;
		}
	
		$jl_cat_posts_widget .= $after_title;
		$jl_cat_posts_widget .= '<ul>';
		foreach($cat_posts as $post) {
			setup_postdata($post);
			$jl_cat_posts_widget .= '<li class="cat-posts-item-' . $post->ID . '"><a href="' . get_permalink($post) . '">' . $post->post_title . '</a>';
			if( $excerpt ) {
				$jl_cat_posts_widget .= '<br />';
				$jl_cat_posts_widget .= apply_filters('the_excerpt', get_the_excerpt());
			}
			$jl_cat_posts_widget .= '</li>';
		}
		$jl_cat_posts_widget .= '</ul>';
		$jl_cat_posts_widget .= $after_widget;
	
		wp_cache_set($cache_key, $jl_cat_posts_widget, 'widget');
	} 
	echo $jl_cat_posts_widget;
}

function flush_jl_cat_posts_widget( $post_id ) {
	$categories = wp_get_post_categories( $post_id );
	foreach( $categories as $cat_id ) {
		wp_cache_delete('jl_cat_posts_widget-' . $cat_id, 'widget');
	}
}
	
add_action('save_post', 'flush_jl_cat_posts_widget');
add_action( 'update_option_widget_cat_posts', 'flush_jl_cat_posts_widget' );
add_action('deleted_post', 'flush_jl_cat_posts_widget');

// Displays form for a particular instance of the widget.  Also updates the data after a POST submit
// $widget_args: number
//    number: which of the several widgets of this type do we mean
function jl_cat_posts_control( $widget_args = 1 ) {
	global $wp_registered_widgets;
	static $updated = false; // Whether or not we have already updated the data after a POST submit

	if ( is_numeric($widget_args) )
		$widget_args = array( 'number' => $widget_args );
	$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
	extract( $widget_args, EXTR_SKIP );

	// Data should be stored as array:  array( number => data for that instance of the widget, ... )
	$options = get_option('widget_cat_posts');
	if ( !is_array($options) )
		$options = array();

	// We need to update the data
	if ( !$updated && !empty($_POST['sidebar']) ) {
		// Tells us what sidebar to put the data in
		$sidebar = (string) $_POST['sidebar'];

		$sidebars_widgets = wp_get_sidebars_widgets();
		if ( isset($sidebars_widgets[$sidebar]) )
			$this_sidebar =& $sidebars_widgets[$sidebar];
		else
			$this_sidebar = array();

		foreach ( $this_sidebar as $_widget_id ) {
			// Remove all widgets of this type from the sidebar.  We'll add the new data in a second.  This makes sure we don't get any duplicate data
			// since widget ids aren't necessarily persistent across multiple updates
			if ( 'jl_cat_posts_widget' == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
				$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
				if ( !in_array( "cat-posts-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed. "many-$widget_number" is "{id_base}-{widget_number}
					unset($options[$widget_number]);
			}
		}
		
		foreach ( (array) $_POST['cat-posts'] as $widget_number => $cat_posts_instance ) {
			// compile data from $widget_many_instance
			$title = wp_specialchars( $cat_posts_instance['title'] );
			$options[$widget_number] = array( 'title' => $title, 'cat' => (int) $cat_posts_instance['cat'], 'num' => (int) $cat_posts_instance['num'], 'title_link' => (bool) $cat_posts_instance['title_link'], 'excerpt' => (bool) $cat_posts_instance['excerpt'] );
		}
		
		update_option('widget_cat_posts', $options);
		
		$updated = true; // So that we don't go through this more than once
	}
	
	
	// Here we echo out the form
	if ( -1 == $number ) { // We echo out a template for a form which can be converted to a specific form later via JS
		$title = '';
		$cat = '';
		$num = 5;
		$link = false;
		$excerpt = false;
		$number = '%i%';
	} else {
		$title = attribute_escape($options[$number]['title']);
		$cat = (int) $options[$number]['cat'];
		$num = (int) $options[$number]['num'];
		$link = (bool) $options[$number]['title_link'];
		$excerpt = (bool) $options[$number]['excerpt'];
	}

	// The form has inputs with names like widget-many[$number][something] so that all data for that instance of
	// the widget are stored in one $_POST variable: $_POST['widget-many'][$number]
?>
		<p>
			<label for="cat-posts-title-<?php echo $number; ?>">
				<?php _e( 'Title:' ); ?>
				<input class="widefat" id="cat-posts-title-<?php echo $number; ?>" name="cat-posts[<?php echo $number; ?>][title]" type="text" value="<?php echo $title; ?>" />
			</label>
		</p>
		
		<p>
			<label>
				<?php _e( 'Category:' ); ?><br />
				<?php wp_dropdown_categories( array( 'name' => 'cat-posts[' . $number . '][cat]', 'selected' => $cat ) ); ?>
			</label>
		</p>
		
		<p>
			<label for="cat-posts-number-<?php echo $number; ?>">
				<?php _e('Number of posts to show:'); ?>
				<input style="width: 25px; text-align: center;" id="cat-posts-number-<?php echo $number; ?>" name="cat-posts[<?php echo $number; ?>][num]" type="text" value="<?php echo $num; ?>" />
			</label>
		</p>
		
		<p>
			<label for="cat-posts-link-<?php echo $number; ?>">
				<input type="checkbox" class="checkbox" id="cat-posts-link-<?php echo $number; ?>" name="cat-posts[<?php echo $number; ?>][title_link]"<?php checked( (bool) $link, true ); ?> />
				<?php _e( 'Make widget title link' ); ?>
			</label>
		</p>
		
		<p>
			<label for="cat-posts-excerpt-<?php echo $number; ?>">
				<input type="checkbox" class="checkbox" id="cat-posts-excerpt-<?php echo $number; ?>" name="cat-posts[<?php echo $number; ?>][excerpt]"<?php checked( (bool) $excerpt, true ); ?> />
				<?php _e( 'Show post excerpt' ); ?>
			</label>
		</p>
		
		<!--<input type="hidden" name="cat-posts[<?php echo $number; ?>][submit]" value="1" />-->
<?php

}

// Registers each instance of our widget on startup
function jl_cat_posts_register() {
	if ( !$options = get_option('widget_cat_posts') )
		$options = array();

	$widget_ops = array('classname' => 'cat_posts', 'description' => __('Widget that shows posts from a specific category.'));
	$control_ops = array('id_base' => 'cat-posts');
	$name = __('Category Posts');

	$registered = false;
	foreach ( array_keys($options) as $o ) {
		// Old widgets can have null values for some reason
		if ( !isset($options[$o]['cat']) ) // we used 'something' above in our exampple.  Replace with with whatever your real data are.
			continue;

		// $id should look like {$id_base}-{$o}
		$id = "cat-posts-$o"; // Never never never translate an id
		$registered = true;
		wp_register_sidebar_widget( $id, $name, 'jl_cat_posts_widget', $widget_ops, array( 'number' => $o ) );
		wp_register_widget_control( $id, $name, 'jl_cat_posts_control', $control_ops, array( 'number' => $o ) );
	}

	// If there are none, we register the widget's existance with a generic template
	if ( !$registered ) {
		wp_register_sidebar_widget( 'cat-posts-1', $name, 'jl_cat_posts_widget', $widget_ops, array( 'number' => -1 ) );
		wp_register_widget_control( 'cat-posts-1', $name, 'jl_cat_posts_control', $control_ops, array( 'number' => -1 ) );
	}
}

// This is important
add_action( 'widgets_init', 'jl_cat_posts_register' );

?>