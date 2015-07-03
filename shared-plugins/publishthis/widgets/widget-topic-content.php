<?php

/**
 * defines Publishthis Topic widget
 * - setup widget options
 * - output results
 */
class Publishthis_Topic_Content_Widget extends WP_Widget {
	
	function __construct() {
		parent::__construct (
								'publishthis_topic_content_widget', 
								'PublishThis: Topic Content', 
								array ( 'classname' => 'topic-content-widget', 
										'description' => 'Display topic content from PublishThis.' ) );
		
		// define ajax call to get topics
		add_action( 'wp_ajax_get_publishthis_topics', array ($this, 'get_topics' ) );
	}
	
	/**
	 * @desc Get topics for ajax call
	 * @return JSON object
	 */
	function get_topics() {
		global $publishthis;
		
		// Check user access and ajax nonce
		if ( !current_user_can('manage_options') || !check_ajax_referer('publishthis_admin_widgets_nonce') ) {
			echo json_encode( array('status' => 'access deny' ) );
			exit();
		}
		
		// Check topic name
		$safe_topic_name = sanitize_text_field( $_GET['topic_name'] );
		$topic_name = ! empty( $safe_topic_name ) ? $safe_topic_name : '';
		if (! $topic_name) {
			$json = array('message' => 'Empty topic name', 'status' => 'error' );
			echo json_encode( $json );
			exit();
		}
		
		// Get topics (API call)
		$topics = $publishthis->api->get_topics( $topic_name );
		
		if (! $topics) {
			$json = array ('message' => 'No topics found', 'status' => 'error' );
			echo json_encode( $json );
			exit();
		}
		
		// Return topics as json object
		$json = array ('topics' => $topics, 'status' => 'success' );
		echo json_encode( $json );
		exit();
	}
	
	/**
	 * @desc Display Topics widget output
	 */
	function widget($args, $instance) {
		global $publishthis;
		
		// sanitize data
		$instance = $this->sanitize_data_array( $instance );
		
		// check for cached content
		$html = get_transient( $this->id_base );
		if ($html) {
			echo $html;
			return;
		}
		
		// check that topic id passed
		if (! $instance['topic_id'])
			return;
		
		// generate output
		ob_start ();
		
		echo $args['before_widget'];
		
		if ($title = apply_filters ( 'widget_title', $instance['title'] )) {
			echo $args['before_title'] . $title . $args ['after_title'];
		}
		
		$params = array ('sort' => $instance['sort_by'], 'results' => $instance['num_results'] );
		
		// retrieve topics (API call)
		$content = $publishthis->api->get_topic_content_by_id( $instance['topic_id'], $params );
		
		// pass widget settings to template
		if ($content) {
			$GLOBALS ['pt_content'] = array (
					'result'             => $content, 
					'show_links'         => $instance['show_links'], 
					'show_photos'        => $instance['show_photos'], 
					'show_source'        => $instance['show_source'], 
					'show_summary'       => $instance['show_summary'], 
					'max_width_images'   => $instance['max_width_images'], 
					'ok_resize_previews' => $instance['ok_resize_previews'] );
			$publishthis->load_template ( 'widgets/automated-saved-search-content.php' );
			unset ( $GLOBALS ['pt_content'] );
		}
		
		echo $args ['after_widget'];
		
		$html = ob_get_clean ();
		set_transient ( $this->id_base, $html, $instance['cache_interval'] );
		
		// render output
		echo $html;
	}
	
	/**
	 * @desc Sanitize data before usage
	 */
	function sanitize_data_array( $data ) {
		$safe_data = array();
		foreach( $data as $key=>$val) {
			$safe_data[ $key ] = sanitize_text_field( $val );
		}
		return $safe_data;
	}
	
	/**
	 * @desc Prepare Topics widget settings for saving
	 */
	function update($new_instance, $old_instance) {
		$instance = $new_instance + $old_instance;
		return $this->sanitize_data_array( $instance );;
	}
	
	/**
	 * @desc Init and display Topics widget setup form
	 */
	function form($instance) {
		global $publishthis;
		
		// set defaults
		$instance = ( array ) $instance + 
			array (	
				'title'              => 'Topic Content - PublishThis', 
				'topic_name'         => '', 
				'topic_id'           => '0', 
				'sort_by'            => 'most_recent', 
				'num_results'        => 10, 
				'show_links'         => '1', 
				'show_photos'        => '1', 
				'show_source'        => '1', 
				'show_summary'       => '1', 
				'max_width_images'   => '300', 
				'ok_resize_previews' => '1', 
				'cache_interval'     => 60 );
		
		// fill available topics select
		$topic_options = '';
		if (defined('DOING_AJAX')) {
			$topics = $publishthis->api->get_topics( $instance['topic_name'] );
			if ($topics) {
				foreach ( $topics as $topic ) {
					$topic_options .= sprintf ( '<option value="%s"%s>%s</option>', $topic->topicId, selected ( $topic->topicId, $instance['topic_id'], false ), $topic->displayName . " (" . $topic->shortLabel . ")" );
				}
			}
		}
		
		$instance = $this->sanitize_data_array( $instance );
		?>

<p>
	<label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
	<input class="widefat" type="text"
		id="<?php echo $this->get_field_id('title'); ?>"
		name="<?php echo $this->get_field_name('title'); ?>"
		value="<?php echo $instance['title']; ?>" />
</p>
<p>
	<label for="<?php echo $this->get_field_id('topic_name'); ?>">Topic Name:</label> 
	<input class="widefat topic-name-field" type="text"
		id="<?php echo $this->get_field_id('topic_name'); ?>"
		name="<?php echo $this->get_field_name('topic_name'); ?>"
		value="<?php echo $instance['topic_name']; ?>" /> 
	<span style="display: block; padding-top: 4px;"> 
		<a href="#" class="button topic-name-button">Search Topics</a> 
		<img src="<?php echo admin_url('/images/wpspin_light.gif'); ?>" class="publishthis-ajax-img" style="visibility: hidden; position: relative; top: 5px;" />
	</span>
</p>
<p>
	<label for="<?php echo $this->get_field_id('topic_id'); ?>">Topic:</label>
	<select class="topic-id-field" style="width: 150px;"
		name="<?php echo $this->get_field_name('topic_id'); ?>"
		id="<?php echo $this->get_field_id('topic_id'); ?>"
		data-current="<?php echo esc_attr($instance['topic_id']); ?>">
		<?php echo ($topic_options) ? $topic_options :'<option value="0">No topics found</option>'; ?>
	</select>
</p>
<p>
	<label for="<?php echo $this->get_field_id('sort_by'); ?>">Sort By:</label>
	<select name="<?php echo $this->get_field_name('sort_by'); ?>" id="<?php echo $this->get_field_id('sort_by'); ?>">
		<option value="most_recent" <?php selected('most_recent', $instance['sort_by']); ?>>Most Recent</option>
		<option value="trending_today" <?php selected('trending_today', $instance['sort_by']); ?>>Trending Today</option>
		<option value="trending_pastweek" <?php selected('trending_pastweek', $instance['sort_by']); ?>>Trending Past Week</option>
	</select>
</p>
<p>
	<label for="<?php echo $this->get_field_id('num_results'); ?>">Number of Results to Display:</label> 
	<select name="<?php echo $this->get_field_name('num_results'); ?>" id="<?php echo $this->get_field_id('num_results'); ?>">
		<option value="5" <?php selected(5, $instance['num_results']); ?>>5</option>
		<option value="10" <?php selected(10 == $instance['num_results']); ?>>10</option>
		<option value="15" <?php selected(15 == $instance['num_results']); ?>>15</option>
	</select>
</p>
<p>
	<label for="<?php echo $this->get_field_id('max_width_images'); ?>">Maximum Width for Images:</label> 
	<input class="widefat" type="text"
		id="<?php echo $this->get_field_id('max_width_images'); ?>"
		name="<?php echo $this->get_field_name('max_width_images'); ?>"
		value="<?php echo $instance['max_width_images']; ?>" />
</p>
<p>
	<input type="hidden" name="<?php echo $this->get_field_name('show_photos'); ?>" value="0" />
	<input class="checkbox" type="checkbox" <?php checked($instance['show_photos'], '1'); ?>
		id="<?php echo $this->get_field_id('show_photos'); ?>"
		name="<?php echo $this->get_field_name('show_photos'); ?>" value="1" />
	<label for="<?php echo $this->get_field_id('show_photos'); ?>">Show Photos</label> <br />
</p>
<p>
	<input type="hidden" name="<?php echo $this->get_field_name('ok_resize_previews'); ?>" value="0" /> 
	<input class="checkbox" type="checkbox" <?php checked($instance['ok_resize_previews'], '1'); ?>
		id="<?php echo $this->get_field_id('ok_resize_previews'); ?>"
		name="<?php echo $this->get_field_name('ok_resize_previews'); ?>"
		value="1" /> 
	<label for="<?php echo $this->get_field_id('ok_resize_previews'); ?>">Okay to Resize Previews</label> <br />
</p>
<p>
	<input type="hidden" name="<?php echo $this->get_field_name('show_links'); ?>" value="0" />
	<input class="checkbox" type="checkbox" <?php checked($instance['show_links'], '1'); ?>
		id="<?php echo $this->get_field_id('show_links'); ?>"
		name="<?php echo $this->get_field_name('show_links'); ?>" 
		value="1" />
	<label for="<?php echo $this->get_field_id('show_links'); ?>">Display Title with Link</label> <br />
</p>
<p>
	<input type="hidden" name="<?php echo $this->get_field_name('show_summary'); ?>" value="0" />
	<input class="checkbox" type="checkbox" <?php checked($instance['show_summary'], '1'); ?>
		id="<?php echo $this->get_field_id('show_summary'); ?>"
		name="<?php echo $this->get_field_name('show_summary'); ?>" 
		value="1" />
	<label for="<?php echo $this->get_field_id('show_summary'); ?>">Show Summaries</label> <br />
</p>
<p>
	<input type="hidden" name="<?php echo $this->get_field_name('show_source'); ?>" value="0" />
	<input class="checkbox" type="checkbox" <?php checked($instance['show_source'], '1'); ?>
		id="<?php echo $this->get_field_id('show_source'); ?>"
		name="<?php echo $this->get_field_name('show_source'); ?>" 
		value="1" />
	<label for="<?php echo $this->get_field_id('show_source'); ?>">Show Source Info</label> <br />
</p>
<p>
	<label for="<?php echo $this->get_field_id('cache_interval'); ?>">Cache Interval:</label> 
	<select name="<?php echo $this->get_field_name('cache_interval'); ?>" id="<?php echo $this->get_field_id('cache_interval'); ?>">
		<option value="1" <?php selected(1, $instance['cache_interval']); ?>>1 minute</option>
		<option value="5" <?php selected(5, $instance['cache_interval']); ?>>5 minutes</option>
		<option value="15" <?php selected(15, $instance['cache_interval']); ?>>15 minutes</option>
		<option value="30" <?php selected(30, $instance['cache_interval']); ?>>30 minutes</option>
		<option value="60" <?php selected(60, $instance['cache_interval']); ?>>1 hour</option>
		<option value="120" <?php selected(120, $instance['cache_interval']); ?>>2 hours</option>
		<option value="360" <?php selected(360, $instance['cache_interval']); ?>>12 hours</option>
		<option value="1440" <?php selected(1440, $instance['cache_interval']); ?>>1 day</option>
	</select>
</p>
<?php
	}
}
