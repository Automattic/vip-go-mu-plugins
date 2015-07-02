<?php
/*
 * Security check
 * Exit if file accessed directly.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



/*
 * Recommendations Widget
 * WordPress widget that displays playbuzz related playful content links and recommendations on sites sidebar.
 *
 * @since 0.1.0
 */
class Playbuzz_Recommendations_Widget extends WP_Widget {

	/*
	 * Constructor
	 */
	public function __construct() {

		// load plugin text domain
		//add_action( 'init', array( $this, 'playbuzz' ) );

		// register new widget
		parent::__construct(
			'playbuzz-recommendations-id',
			__( 'Playbuzz Recommendations', 'playbuzz' ),
			array(
				'classname'   => 'playbuzz-recommendations',
				'description' => __( 'Related Playful Content links and recommendations by playbuzz.', 'playbuzz' )
			)
		);

	}


	/*
	 * Outputs the content of the widget.
	 *
	 * @param	array	args		The array of form elements
	 * @param	array	instance	The current instance of the widget
	 */
	public function widget( $args, $instance ) {

		// Load WordPress globals
		global $wp_version;

		// Load global site settings from DB
		$options = (array) get_option( 'playbuzz' );

		// Prepare widget settings
		$key   = ( ( ( array_key_exists( 'key', $options ) ) ) ? $options['key'] : str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) ) );
		$title = empty( $instance['title'] ) ? '' : apply_filters( 'title', $instance['title'] );
		$view  = empty( $instance['view']  ) ? '' : apply_filters( 'view',  $instance['view']  );
		$items = empty( $instance['items'] ) ? '' : apply_filters( 'items', $instance['items'] );
		$links = empty( $instance['links'] ) ? '' : apply_filters( 'links', $instance['links'] );
		$tags  = '';
		$tags .= ( ( '1' == $instance['tags-mix']          ) ? 'All,'                  : '' );
		$tags .= ( ( '1' == $instance['tags-fun']          ) ? 'Fun,'                  : '' );
		$tags .= ( ( '1' == $instance['tags-pop']          ) ? 'Pop,'                  : '' );
		$tags .= ( ( '1' == $instance['tags-geek']         ) ? 'Geek,'                 : '' );
		$tags .= ( ( '1' == $instance['tags-sports']       ) ? 'Sports,'               : '' );
		$tags .= ( ( '1' == $instance['tags-editors-pick'] ) ? 'EditorsPick_Featured,' : '' );
		$tags .= $instance['more-tags'];
		$tags  = rtrim( $tags, ',');

		// Output
		echo $args['before_widget'];
		if ( ! empty( $title ) )
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		echo '
			<script type="text/javascript" src="//cdn.playbuzz.com/widget/widget.js"></script>
			<div class="pb_recommendations" data-provider="WordPress ' . esc_attr( $wp_version ) . '" data-key="' . esc_attr( $key ) . '" data-tags="' . esc_attr( $tags ) . '" data-view="' . esc_attr( $view ) . '" data-num-items="' . esc_attr( $items ) . '" data-links="' . esc_attr( $links ) . '" data-nostyle="false"></div>
		';
		echo $args['after_widget'];

	}


	/*
	 * Processes the widget's options to be saved.
	 *
	 * @param	array	new_instance	The new instance of values to be generated via the update.
	 * @param	array	old_instance	The previous instance of values before the update.
	 */
	public function update( $new_instance, $old_instance ) {

		$instance = $old_instance;

		$instance['title']             = sanitize_text_field( $new_instance['title']                             );
		$instance['view']              = sanitize_text_field( stripslashes( $new_instance['view']              ) );
		$instance['items']             = sanitize_text_field( stripslashes( $new_instance['items']             ) );
		$instance['tags-mix']          = sanitize_text_field( stripslashes( $new_instance['tags-mix']          ) );
		$instance['tags-fun']          = sanitize_text_field( stripslashes( $new_instance['tags-fun']          ) );
		$instance['tags-pop']          = sanitize_text_field( stripslashes( $new_instance['tags-pop']          ) );
		$instance['tags-geek']         = sanitize_text_field( stripslashes( $new_instance['tags-geek']         ) );
		$instance['tags-sports']       = sanitize_text_field( stripslashes( $new_instance['tags-sports']       ) );
		$instance['tags-editors-pick'] = sanitize_text_field( stripslashes( $new_instance['tags-editors-pick'] ) );
		$instance['more-tags']         = sanitize_text_field( stripslashes( $new_instance['more-tags']         ) );
		$instance['section-page']      = sanitize_text_field( $new_instance['section-page'] );

		// for backwards compatibility
		if ( empty( $instance['section-page'] ) OR ( 0 == $instance['section-page'] ) ) {
			$instance['links']         = sanitize_text_field( $new_instance['links'] );
		} else {
			$instance['links']         = esc_url_raw( get_permalink( $new_instance['section-page'] ) );
		}

		return $instance;

	}


	/*
	 * Generates the administration form for the widget.
	 *
	 * @param	array	instance	The array of keys and values for the widget.
	 */
	public function form( $instance ) {

		// Load options
		$options = (array) get_option( 'playbuzz' );

		// Set default values
		$defaults = array(
				'title'				=> __( 'Play It', 'playbuzz' ),
				'view'              => ( isset( $options['view']              ) ? $options['view']              : 'large_images' ),
				'items'             => ( isset( $options['items']             ) ? $options['items']             : '3' ),
				'tags-mix'          => ( isset( $options['tags-mix']          ) ? $options['tags-mix']          : '1' ),
				'tags-fun'          => ( isset( $options['tags-fun']          ) ? $options['tags-fun']          : ''  ),
				'tags-pop'          => ( isset( $options['tags-pop']          ) ? $options['tags-pop']          : ''  ),
				'tags-geek'         => ( isset( $options['tags-geek']         ) ? $options['tags-geek']         : ''  ),
				'tags-sports'       => ( isset( $options['tags-sports']       ) ? $options['tags-sports']       : ''  ),
				'tags-editors-pick' => ( isset( $options['tags-editors-pick'] ) ? $options['tags-editors-pick'] : ''  ),
				'more-tags'         => ( isset( $options['more-tags']         ) ? $options['more-tags']         : ''  ),
				'links'             => ( isset( $options['links']             ) ? $options['links']             : ''  ),
				'section-page'      => ( isset( $options['section-page']      ) ? $options['section-page']      : ''  ),
			);

		// New instance (use defaults if empty)
		$new_instance = wp_parse_args( (array)$instance, $defaults );

		// Display the admin form
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id('title') ); ?>"><?php esc_html_e( 'Title', 'playbuzz' ); ?></label>
			<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id('title') ); ?>" name="<?php echo esc_attr( $this->get_field_name('title') ); ?>" value="<?php echo esc_attr( $new_instance['title'] ); ?>" placeholder="<?php esc_attr_e( 'Widget title', 'playbuzz' ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id('view') ); ?>"><?php esc_html_e( 'Items layout', 'playbuzz' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id('view') ); ?>" name="<?php echo esc_attr( $this->get_field_name('view') ); ?>">
				<option value="large_images"      <?php if ( 'large_images'      == $new_instance['view'] ) echo 'selected'; ?>><?php esc_html_e( 'Large Images',      'playbuzz' ); ?></option>
				<option value="horizontal_images" <?php if ( 'horizontal_images' == $new_instance['view'] ) echo 'selected'; ?>><?php esc_html_e( 'Horizontal Images', 'playbuzz' ); ?></option>
				<option value="no_images"         <?php if ( 'no_images'         == $new_instance['view'] ) echo 'selected'; ?>><?php esc_html_e( 'No Images',         'playbuzz' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id('items') ); ?>"><?php esc_html_e( 'Number of Items', 'playbuzz' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id('items') ); ?>" name="<?php echo esc_attr( $this->get_field_name('items') ); ?>">
				<option value="2"  <?php if ( '2'  == $new_instance['items'] ) echo 'selected'; ?>>2</option>
				<option value="3"  <?php if ( '3'  == $new_instance['items'] ) echo 'selected'; ?>>3</option>
				<option value="4"  <?php if ( '4'  == $new_instance['items'] ) echo 'selected'; ?>>4</option>
				<option value="5"  <?php if ( '5'  == $new_instance['items'] ) echo 'selected'; ?>>5</option>
				<option value="6"  <?php if ( '6'  == $new_instance['items'] ) echo 'selected'; ?>>6</option>
				<option value="7"  <?php if ( '7'  == $new_instance['items'] ) echo 'selected'; ?>>7</option>
				<option value="8"  <?php if ( '8'  == $new_instance['items'] ) echo 'selected'; ?>>8</option>
				<option value="9"  <?php if ( '9'  == $new_instance['items'] ) echo 'selected'; ?>>9</option>
				<option value="10" <?php if ( '10' == $new_instance['items'] ) echo 'selected'; ?>>10</option>
				<option value="11" <?php if ( '11' == $new_instance['items'] ) echo 'selected'; ?>>11</option>
				<option value="12" <?php if ( '12' == $new_instance['items'] ) echo 'selected'; ?>>12</option>
				<option value="13" <?php if ( '13' == $new_instance['items'] ) echo 'selected'; ?>>13</option>
				<option value="14" <?php if ( '14' == $new_instance['items'] ) echo 'selected'; ?>>14</option>
				<option value="15" <?php if ( '15' == $new_instance['items'] ) echo 'selected'; ?>>15</option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id('tags') ); ?>"><?php esc_html_e( 'Tags', 'playbuzz' ); ?></label><br>
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name('tags-mix') );          ?>" value="1" <?php if ( '1' == $new_instance['tags-mix']          ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'All',            'playbuzz' ); ?> 
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name('tags-fun') );          ?>" value="1" <?php if ( '1' == $new_instance['tags-fun']          ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Fun',            'playbuzz' ); ?> 
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name('tags-pop') );          ?>" value="1" <?php if ( '1' == $new_instance['tags-pop']          ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Pop',            'playbuzz' ); ?> 
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name('tags-geek') );         ?>" value="1" <?php if ( '1' == $new_instance['tags-geek']         ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Geek',           'playbuzz' ); ?> 
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name('tags-sports') );       ?>" value="1" <?php if ( '1' == $new_instance['tags-sports']       ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Sports',         'playbuzz' ); ?> 
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name('tags-editors-pick') ); ?>" value="1" <?php if ( '1' == $new_instance['tags-editors-pick'] ) echo 'checked="checked"'; ?>> <?php esc_html_e( 'Editor\'s Pick', 'playbuzz' ); ?> 
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id('more-tags') ); ?>"><?php esc_html_e( 'Custom Tags', 'playbuzz' ); ?></label>
			<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id('more-tags') ); ?>" name="<?php echo esc_url( $this->get_field_name('more-tags') ); ?>" value="<?php echo esc_attr( $new_instance['more-tags'] ); ?>" placeholder="<?php esc_attr_e( 'Comma separated tags', 'playbuzz' ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id('links') ); ?>"><?php esc_html_e( 'Open Items at (location of section)', 'playbuzz' ); ?></label><br>
			<p><?php printf( __( '<a href="%s" target="_blank">Create</a> a new page containing the <code>[playbuzz-section]</code> shortcode. Then select it below as the destination page where items will open:', 'playbuzz' ), 'post-new.php?post_type=page' ); ?></p>
			<?php
			if ( isset( $new_instance['section-page'] ) ) {
				$link_page_id = $new_instance['section-page'];
			} else {
				$link_page_id = 0;
			}
			?>
			<?php wp_dropdown_pages( array( 'selected' => $link_page_id, 'post_type' => 'page', 'hierarchical' => 1, 'class' => 'widefat', 'id' => $this->get_field_id('section-page'), 'name' => $this->get_field_name('section-page'), 'show_option_none' => __( '&mdash; Select &mdash;' ), 'option_none_value' => '0' ) ); ?>
			<input type="hidden" class="widefat" id="<?php echo esc_attr( $this->get_field_id('links') ); ?>" name="<?php echo esc_attr( $this->get_field_name('links') ); ?>" value="<?php echo esc_attr( $new_instance['links'] ); ?>" placeholder="https://www.playbuzz.com/">
		</p>
		<?php
	}


	/*
	 * Loads the Widget's text domain for localization and translation.
	 */
	public function playbuzz() {
		load_plugin_textdomain( 'playbuzz', false, plugin_dir_path( __FILE__ ) . '/lang' );
	}

}


/*
 * Register Recommendations Widget
 *
 * @since 0.1.0
 */
function register_playbuzz_recommendations_widget() {
	register_widget("Playbuzz_Recommendations_Widget");
}
add_action( 'widgets_init', 'register_playbuzz_recommendations_widget' );
