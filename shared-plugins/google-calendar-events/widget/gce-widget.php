<?php
class GCE_Widget extends WP_Widget {
	function GCE_Widget() {
		parent::WP_Widget(
			false,
			$name = __( 'Google Calendar Events', GCE_TEXT_DOMAIN ),
			array( 'description' => __( 'Display a list or calendar grid of events from one or more Google Calendar feeds you have added', GCE_TEXT_DOMAIN ) )
		);
	}

	function widget( $args, $instance ) {
		extract( $args );

		//Output before widget stuff
		echo $before_widget;

		//Get saved feed options
		$options = get_option( GCE_OPTIONS_NAME );

		//Check whether any feeds have been added yet
		if( is_array( $options ) && ! empty( $options ) ) {
			//Output title stuff
			$title = empty( $instance['title'] ) ? '' : apply_filters( 'widget_title', $instance['title'] );

			if ( ! empty( $title ) )
				echo $before_title . $title . $after_title;

			$no_feeds_exist = true;
				$feed_ids = array();

			if ( '' != $instance['id'] ) {
				//Break comma delimited list of feed ids into array
				$feed_ids = explode( ',', str_replace( ' ', '', $instance['id'] ) );

				//Check each id is an integer, if not, remove it from the array
				foreach ( $feed_ids as $key => $feed_id ) {
					if ( 0 == absint( $feed_id ) )
						unset( $feed_ids[$key] );
				}

				//If at least one of the feed ids entered exists, set no_feeds_exist to false
				foreach ( $feed_ids as $feed_id ) {
					if ( isset($options[$feed_id] ) )
						$no_feeds_exist = false;
				}
			} else {
				foreach ( $options as $feed ) {
					$feed_ids[] = $feed['id'];
				}

				$no_feeds_exist = false;
			}

			//Check that at least one valid feed id has been entered
			if ( empty( $feed_ids ) || $no_feeds_exist ) {
				if ( current_user_can( 'manage_options' ) ) {
					_e( 'No valid Feed IDs have been entered for this widget. Please check that you have entered the IDs correctly in the widget settings (Appearance > Widgets), and that the Feeds have not been deleted.', GCE_TEXT_DOMAIN );
				} else {
					$options = get_option( GCE_GENERAL_OPTIONS_NAME );
					echo $options['error'];
				}
			} else {
				//Turns feed_ids back into string or feed ids delimited by '-' ('1-2-3-4' for example)
				$feed_ids = implode( '-', $feed_ids );

				$title_text = ( $instance['display_title'] ) ? $instance['display_title_text'] : null;
				$max_events = ( isset( $instance['max_events'] ) ) ? $instance['max_events'] : 0;
				$sort_order = ( isset( $instance['order'] ) ) ? $instance['order'] : 'asc';

				//Output correct widget content based on display type chosen
				switch ( $instance['display_type'] ) {
					case 'grid':
						echo '<div class="gce-widget-grid" id="' . $args['widget_id'] . '-container">';
						//Output main widget content as grid (no AJAX)
						gce_widget_content_grid( $feed_ids, $title_text, $max_events, $args['widget_id'] . '-container' );
						echo '</div>';
						break;
					case 'ajax':
						echo '<div class="gce-widget-grid" id="' . $args['widget_id'] . '-container">';
						//Output main widget content as grid (with AJAX)
						gce_widget_content_grid( $feed_ids, $title_text, $max_events, $args['widget_id'] . '-container', true );
						echo '</div>';
						break;
					case 'list':
						echo '<div class="gce-widget-list" id="' . $args['widget_id'] . '-container">';
						//Output main widget content as list
						gce_widget_content_list( $feed_ids, $title_text, $max_events, $sort_order );
						echo '</div>';
						break;
					case 'list-grouped':
						echo '<div class="gce-widget-list" id="' . $args['widget_id'] . '-container">';
						//Output main widget content as a grouped list
						gce_widget_content_list( $feed_ids, $title_text, $max_events, $sort_order, true );
						echo '</div>';
						break;
				}
			}
		} else {
			if ( current_user_can( 'manage_options' ) ) {
				_e( 'No feeds have been added yet. You can add a feed in the Google Calendar Events settings.', GCE_TEXT_DOMAIN );
			} else {
				$options = get_option( GCE_GENERAL_OPTIONS_NAME );
				echo $options['error'];
			}
		}

		//Output after widget stuff
		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['id'] = sanitize_text_field( $new_instance['id'] );
		if ( in_array( $new_instance['display_type'], array( 'grid', 'ajax', 'list', 'list-grouped' ) ) )
			$instance['display_type'] = sanitize_key( $new_instance['display_type'] );
		$instance['max_events'] = absint( $new_instance['max_events'] );
		$instance['order'] = ( 'asc' == $new_instance['order'] ) ? 'asc' : 'desc';
		$instance['display_title'] = ( 'on' == $new_instance['display_title'] ) ? true : false;
		$instance['display_title_text'] = wp_filter_kses( $new_instance['display_title_text'] );
		return $instance;
	}

	function form( $instance ) {
		//Get saved feed options
		$options = get_option( GCE_OPTIONS_NAME );

		if ( empty( $options ) ) {
			//If no feeds or groups ?>
			<p><?php _e( 'No feeds have been added yet. You can add feeds in the Google Calendar Events settings.', GCE_TEXT_DOMAIN ); ?></p>
			<?php
		}else{
			$title = ( isset( $instance['title'] ) ) ? $instance['title'] : '';
			$ids = ( isset( $instance['id'] ) ) ? $instance['id'] : '';
			$display_type = ( isset( $instance['display_type'] ) ) ? $instance['display_type'] : 'grid';
			$max_events = ( isset( $instance['max_events'] ) ) ? $instance['max_events'] : 0;
			$order = ( isset( $instance['order'] ) ) ? $instance['order'] : 'asc';
			$display_title = ( isset($instance['display_title'] ) ) ? $instance['display_title'] : true;
			$title_text = ( isset($instance['display_title_text'] ) ) ? $instance['display_title_text'] : 'Events on';
			?>
			<p>
				<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label>
				<input type="text" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $title ); ?>" class="widefat" />
			</p><p>
				<label for="<?php echo $this->get_field_id( 'id' ); ?>">
					<?php _e( 'Feeds to display, as a comma separated list (e.g. 1, 2, 4). Leave blank to display all feeds:', GCE_TEXT_DOMAIN ); ?>
				</label>
				<input type="text" id="<?php echo $this->get_field_id( 'id' ); ?>" name="<?php echo $this->get_field_name( 'id' ); ?>" value="<?php echo esc_attr( $ids ); ?>" class="widefat" />
			</p><p>
				<label for="<?php echo $this->get_field_id( 'display_type' ); ?>"><?php _e( 'Display events as:', GCE_TEXT_DOMAIN ); ?></label>
				<select id="<?php echo $this->get_field_id( 'display_type' ); ?>" name="<?php echo $this->get_field_name( 'display_type' ); ?>" class="widefat">
					<option value="grid"<?php selected( $display_type, 'grid' ); ?>><?php _e( 'Calendar Grid', GCE_TEXT_DOMAIN ); ?></option>
					<option value="ajax"<?php selected( $display_type, 'ajax' ); ?>><?php _e( 'Calendar Grid - with AJAX', GCE_TEXT_DOMAIN ); ?></option>
					<option value="list"<?php selected( $display_type, 'list' ); ?>><?php _e( 'List', GCE_TEXT_DOMAIN ); ?></option>
					<option value="list-grouped"<?php selected( $display_type, 'list-grouped' );?>><?php _e( 'List - grouped by date', GCE_TEXT_DOMAIN ); ?></option>
				</select>
			</p><p>
				<label for="<?php echo $this->get_field_id( 'max_events' ); ?>"><?php _e( 'Maximum no. events to display. Enter 0 to show all retrieved.' ); ?></label>
				<input type="text" id="<?php echo $this->get_field_id( 'max_events' ); ?>" name="<?php echo $this->get_field_name( 'max_events' ); ?>" value="<?php echo esc_attr( $max_events ); ?>" class="widefat" />
			</p><p>
				<label for="<?php echo $this->get_field_id( 'order' ); ?>"><?php _e( 'Sort order (only applies to lists):' ); ?></label>
				<select id="<?php echo $this->get_field_id( 'order' ); ?>" name="<?php echo $this->get_field_name( 'order' ); ?>" class="widefat">
					<option value="asc"<?php selected( $order, 'asc' ); ?>><?php _e( 'Ascending', GCE_TEXT_DOMAIN ); ?></option>
					<option value="desc"<?php selected( $order, 'desc' ); ?>><?php _e( 'Descending', GCE_TEXT_DOMAIN ); ?></option>
				</select>
			</p><p>
				<label for="<?php echo $this->get_field_id( 'display_title' ); ?>"><?php _e( 'Display title on tooltip / list item? (e.g. \'Events on 7th March\') Grouped lists always have a title displayed.', GCE_TEXT_DOMAIN ); ?></label>
				<br />
				<input type="checkbox" id="<?php echo $this->get_field_id( 'display_title' ); ?>" name="<?php echo $this->get_field_name( 'display_title' ); ?>"<?php checked( $display_title, true ); ?> value="on" />
				<input type="text" id="<?php echo $this->get_field_id( 'display_title_text' ); ?>" name="<?php echo $this->get_field_name( 'display_title_text' ); ?>" value="<?php echo esc_attr( $title_text ); ?>" style="width:90%;" />
			</p>
			<?php 
		}
	}
}

function gce_widget_content_grid( $feed_ids, $title_text, $max_events, $widget_id, $ajaxified = false, $month = null, $year = null ) {
	require_once GCE_PLUGIN_ROOT . 'inc/gce-parser.php';

	$ids = explode( '-', $feed_ids );

	//Create new GCE_Parser object, passing array of feed id(s)
	$grid = new GCE_Parser( $ids, $title_text, $max_events );

	$num_errors = $grid->get_num_errors();

	$markup = '';

	//If there are less errors than feeds parsed, at least one feed must have parsed successfully so continue to display the grid
	if ( $num_errors < count( $ids ) ) {
		$ids = esc_attr( $ids );
		$title_text = isset( $title_text ) ? esc_html( $title_text) : 'null';

		//If there was at least one error, and user is an admin, output error messages
		if ( $num_errors > 0 && current_user_can( 'manage_options' ) )
			$markup .= $grid->error_messages();

		//Add AJAX script if required
		if ( $ajaxified )
			$markup .= '<script type="text/javascript">jQuery(document).ready(function($){gce_ajaxify("' . $widget_id . '", "' . $feed_ids . '", "' . $max_events . '", "' . $title_text .'", "widget");});</script>';

		$markup .= $grid->get_grid( $year, $month, $ajaxified );
	} else {
		//If current user is an admin, display an error message explaining problem. Otherwise, display a 'nice' error messsage
		if ( current_user_can( 'manage_options' ) ) {
			$markup .= $grid->error_messages();
		} else {
			$options = get_option( GCE_GENERAL_OPTIONS_NAME );
			$markup .= $options['error'];
		}
	}

	echo $markup;
}

function gce_widget_content_list( $feed_ids, $title_text, $max_events, $sort_order, $grouped = false ) {
	require_once GCE_PLUGIN_ROOT . 'inc/gce-parser.php';

	$ids = explode( '-', $feed_ids );

	//Create new GCE_Parser object, passing array of feed id(s)
	$list = new GCE_Parser( $ids, $title_text, $max_events, $sort_order );

	$num_errors = $list->get_num_errors();

	//If there are less errors than feeds parsed, at least one feed must have parsed successfully so continue to display the list
	if ( $num_errors < count( $ids ) ) {
		//If there was at least one error, and user is an admin, output error messages
		if ( $num_errors > 0 && current_user_can( 'manage_options' ) )
			echo $list->error_messages();

		echo $list->get_list( $grouped );
	} else {
		//If current user is an admin, display an error message explaining problem(s). Otherwise, display a 'nice' error messsage
		if ( current_user_can( 'manage_options' ) ) {
			echo $list->error_messages();
		} else {
			$options = get_option( GCE_GENERAL_OPTIONS_NAME );
			echo $options['error'];
		}
	}
}
?>