<?php
/*
Plugin Name: Taxonomy List Widget
Plugin URI: https://ethitter.com/plugins/taxonomy-list-widget/
Description: Creates a list of non-hierarchical taxonomies as an alternative to the term (tag) cloud. Widget provides numerous options to tailor the output to fit your site. List function can also be called directly for use outside of the widget. Formerly known as <strong><em>Tag List Widget</em></strong>.
Author: Erick Hitter
Version: 1.2
Author URI: https://ethitter.com/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 ** TAXONOMY WIDGET PLUGIN
 **/
class taxonomy_list_widget_plugin {
	/*
	 * Class variables
	 */
	var $option_defaults = array(
		'taxonomy' => 'post_tag',
		'max_name_length' => 0,
		'cutoff' => '&hellip;',
		'delimiter' => 'ul',
		'limit' => 0,
		'order' => 'ASC',
		'orderby' => 'name',
		'threshold' => 0,
		'incexc' => 'exclude',
		'incexc_ids' => array(),
		'hide_empty' => true,
		'post_counts' => false,
		'rel' => 'nofollow'
	);

	/*
	 * Register actions and activation/deactivation hooks
	 * @uses add_action, register_activation_hook, register_deactivation_hook
	 * @return null
	 */
	function __construct() {
		add_action( 'widgets_init', array( $this, 'action_widgets_init' ) );

		register_activation_hook( __FILE__, array( $this, 'activation_hook' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );

		add_action( 'split_shared_term', array( $this, 'action_split_shared_term' ), 10, 4 );
	}

	/*
	 * Run plugin cleanup on activation
	 * @uses $this::cleanup
	 * @hook activation
	 * @return null
	 */
	function activation_hook() {
		$this->cleanup();
	}

	/*
	 * Unregister widget when plugin is deactivated and run cleanup
	 * @uses unregister_widget, $this::cleanup
	 * @hook deactivation
	 * @return null
	 */
	function deactivation_hook() {
		unregister_widget( 'taxonomy_list_widget' );

		$this->cleanup();
	}

	/*
	 * Remove options related to plugin versions older than 2.0.
	 * @uses delete_option
	 * @return null
	 */
	function cleanup() {
		$legacy_options = array(
			'TLW',
			'TLW_direct'
		);

		foreach( $legacy_options as $legacy_option )
			delete_option( $legacy_option );
	}

	/*
	 * Register widget
	 * @uses register_widget
	 * @action widgets_init
	 * @return null
	 */
	function action_widgets_init() {
		if( class_exists( 'taxonomy_list_widget' ) )
			register_widget( 'taxonomy_list_widget' );
	}

	/**
	 * Update widget options when terms are split
	 *
	 * Starting in WP 4.2, terms that were previously shared will now be split into their own terms when the terms are updated.
	 * To ensure the widget continues to include/exclude the updated terms, we search widget options on terms split and update stored IDs.
	 *
	 * @param int    $old_id   ID of shared term before the split
	 * @param int    $new_id   ID of new term created after the split
	 * @param int    $tt_id    Term taxonomy ID of split term
	 * @param string $taxonomy Taxonomy of the term being split from its shared entry
	 * @action split_shared_term
	 * @return null
	 */
	public function action_split_shared_term( $old_id, $new_id, $tt_id, $taxonomy ) {
		// WP provides no utility function for getting widget options, so we go straight to the source
		$all_widget_options = $_all_widget_options = get_option( 'widget_taxonomy_list_widget', false );

		// Loop through each widget's options and update stored term IDs if they're being split here
		if ( is_array( $all_widget_options ) && ! empty( $all_widget_options ) ) {
			foreach ( $all_widget_options as $key => $options ) {
				// Check if widget needs updating
				if ( ! is_array( $options ) ) {
					continue;
				}

				if ( $options['taxonomy'] !== $taxonomy ) {
					continue;
				}

				if ( empty( $options['incexc_ids'] ) ) {
					continue;
				}

				// Account for legacy data storage option
				if ( is_string( $options['incexc_ids'] ) ) {
					$options['incexc_ids'] = explode( ',', $options['incexc_ids'] );
					$options['incexc_ids'] = array_map( 'absint', $options['incexc_ids'] );
					$options['incexc_ids'] = array_filter( $options['incexc_ids'] );
				}

				// Find stored term to update and do so
				$key_to_update = array_search( $old_id, $options['incexc_ids'] );

				if ( false === $key_to_update ) {
					continue;
				} else {
					$all_widget_options[ $key ]['incexc_ids'][ $key_to_update ] = $new_id;
				}
			}
		}

		// If the term split was one in a widget option, update the options
		// Reduces `update_option()` calls if nothing's changed
		if ( $all_widget_options !== $_all_widget_options ) {
			update_option( 'widget_taxonomy_list_widget', $all_widget_options );
		}
	}

	/*
	 * Render list
	 * @param array $options
	 * @param string|int $id
	 * @uses wp_parse_args, sanitize_title, apply_filters, get_terms, is_wp_error, is_tag, is_tax, esc_url, get_term_link, selected
	 * @return string or false
	 */
	function render_list( $options, $id = false ) {
		$options = wp_parse_args( $options, $this->option_defaults );
		extract( $options );

		//ID
		if( is_numeric( $id ) )
			$id = intval( $id );
		elseif( is_string( $id ) )
			$id = sanitize_title( $id );

		//Set up options array for get_terms
		$options = array(
			'order' => $order,
			'orderby' => $orderby,
			'hide_empty' => $hide_empty,
			'hierarchical' => false
		);

		if( $limit )
			$options[ 'number' ] = $limit;

		if( !empty( $incexc_ids ) )
			$options[ $incexc ] = $incexc_ids;

		$options = apply_filters( 'taxonomy_list_widget_options', $options, $id );

		//Get terms
		$terms = get_terms( $taxonomy, $options );

		if( !is_wp_error( $terms ) && is_array( $terms ) && !empty( $terms ) ) {
			//CSS ID
			if( is_int( $id ) )
				$css_id = ' id="taxonomy_list_widget_list_' . $id . '"';
			elseif( is_string( $id ) && !empty( $id ) )
				$css_id = ' id="' . $id . '"';
			else
				$css_id = '';

			//Delimiters
			$before_list = '<div class="tlw-list"' . $css_id . '>';
			$after_list = '</div><!-- .tlw-list -->';
			$before_item = '';
			$after_item = ' ';

			if( is_array( $delimiter ) )
				extract( $delimiter );
			else {
				switch( $delimiter ) {
					case 'ol':
						$before_list = '<ol class="tlw-list"' . $css_id . '>';
						$after_list = '</ol><!-- .tlw-list -->';
						$before_item = '<li>';
						$after_item = '</li>';
					break;

					case 'nl':
						$after_item = '<br />';
					break;

					case 'ul':
					default:
						$before_list = '<ul class="tlw-list"' . $css_id . '>';
						$after_list = '</ul><!-- .tlw-list -->';
						$before_item = '<li>';
						$after_item = '</li>';
					break;
				}
			}

			//Start list
			$output = $before_list;

			//Populate dropdown
			$i = 1;
			foreach( $terms as $term ) {
				if( $threshold > 0 && $term->count < $threshold )
					continue;

				//Open item
				$output .= $before_item;
				$output .= '<a href="' . esc_url( get_term_link( (int)$term->term_id, $taxonomy ) ) . '"' . apply_filters( 'taxonomy_list_widget_link_rel', ( $rel == 'dofollow' ? ' rel="dofollow"' : ' rel="nofollow"' ), $id ) . '>';

				//Tag name
				$name = esc_attr( $term->name );
				if( $max_name_length > 0 && strlen( $name ) > $max_name_length )
					$name = substr( $name, 0, $max_name_length ) . $cutoff;
				$output .= $name;

				//Count
				if( $post_counts )
					$output .= ' (' . intval( $term->count ) . ')';

				//Close item
				$output .= '</a>';
				$output .= $after_item;

				$i++;
			}

			//End list
			$output .= $after_list;

			return $output;
		}
		else
			return false;
	}

	/*
	 * Sanitize plugin options
	 * @param array $options
	 * @uses taxonomy_exists, sanitize_text_field, absint, wp_parse_args
	 * @return array
	 */
	function sanitize_options( $options ) {
		$options_sanitized = array(
			'hide_empty' => true,
			'post_counts' => false
		);

		$keys = array_merge( array_keys( $this->option_defaults ), array( 'title' ) );

		if( is_array( $options ) ) {
			foreach( $keys as $key ) {
				if( !array_key_exists( $key, $options ) )
					continue;

				$value = $options[ $key ];

				switch( $key ) {
					case 'taxonomy':
						if( taxonomy_exists( $value ) )
							$options_sanitized[ $key ] = $value;
					break;

					case 'title':
					case 'cutoff':
						$value = sanitize_text_field( $value );

						if( !empty( $value ) || $key == 'title' )
							$options_sanitized[ $key ] = $value;
					break;

					case 'max_name_length':
					case 'limit':
					case 'threshold':
						$options_sanitized[ $key ] = absint( $value );
					break;

					case 'order':
						if( $value == 'ASC' || $value == 'DESC' )
							$options_sanitized[ $key ] = $value;
					break;

					case 'orderby':
						if( $value == 'name' || $value == 'count' )
							$options_sanitized[ $key ] = $value;
					break;

					case 'incexc':
						if( $value == 'include' || $value == 'exclude' )
							$options_sanitized[ $key ] = $value;
					break;

					case 'incexc_ids':
						$options_sanitized[ $key ] = array();

						if( is_string( $value ) )
							$value = explode( ',', $value );

						if( is_array( $value ) ) {
							foreach( $value as $term_id ) {
								$term_id = intval( $term_id );

								if( $term_id > 0 )
									$options_sanitized[ $key ][] = $term_id;

								unset( $term_id );
							}

							sort( $options_sanitized[ $key ], SORT_NUMERIC );
						}
					break;

					case 'hide_empty':
					case 'post_counts':
						$options_sanitized[ $key ] = (bool)$value;
					break;

					case 'delimiter':
						if( is_array( $value ) ) {
							$options_sanitized[ $key ] = array();

							foreach( $value as $delim_key => $delim )
								$options_sanitized[ $key ][ $delim_key ] = wp_filter_post_kses( $delim );
						}
						elseif( $value == 'custom' && array_key_exists( 'delimiter_custom', $options ) && is_array( $options[ 'delimiter_custom' ] ) ) {
							$options_sanitized[ $key ] = array();

							foreach( $options[ 'delimiter_custom' ] as $delim_key => $delim )
								$options_sanitized[ $key ][ $delim_key ] = wp_filter_post_kses( $delim );
						}
						elseif( is_string( $value ) ) {
							$delims = array(
								'ul',
								'ol',
								'nl'
							);

							if( in_array( $value, $delims ) )
								$options_sanitized[ $key ] = $value;
						}
					break;

					case 'rel':
						if( in_array( $value, array( 'nofollow', 'dofollow' ) ) )
							$options_sanitized[ $key ] = $value;
					break;

					default:
						continue;
					break;
				}
			}
		}

		//Ensure array contains all keys by parsing against defaults after options are sanitized
		$options_sanitized = wp_parse_args( $options_sanitized, $this->option_defaults );

		return $options_sanitized;
	}

	/*
	 * PHP 4 compatibility
	 */
	function taxonomy_list_widget_plugin() {
		$this->__construct();
	}
}
global $taxonomy_list_widget_plugin;
if( !is_a( $taxonomy_list_widget_plugin, 'taxonomy_list_widget_plugin' ) )
	$taxonomy_list_widget_plugin = new taxonomy_list_widget_plugin;

/**
 ** Taxonomy List WIDGET
 **/
class taxonomy_list_widget extends WP_Widget {
	/*
	 * Class variables
	 */
	var $defaults = array(
		'title' => 'Tags'
	);

	/*
	 * Register widget and populate class variables
	 * @uses $this::WP_Widget, $taxonomy_list_widget_plugin
	 * @return null
	 */
	function taxonomy_list_widget() {
		$this->WP_Widget( false, 'Taxonomy List Widget', array( 'description' => 'Displays selected non-hierarchical taxonomy terms in a list format.' ) );

		//Load plugin class and populate defaults
		global $taxonomy_list_widget_plugin;
		if( !is_a( $taxonomy_list_widget_plugin, 'taxonomy_list_widget_plugin' ) )
			$taxonomy_list_widget_plugin = new taxonomy_list_widget_plugin;

		if( is_object( $taxonomy_list_widget_plugin ) && property_exists( $taxonomy_list_widget_plugin, 'option_defaults' ) && is_array( $taxonomy_list_widget_plugin->option_defaults ) )
			$this->defaults = array_merge( $taxonomy_list_widget_plugin->option_defaults, $this->defaults );
	}

	/*
	 * Render widget
	 * @param array $args
	 * @param array $instance
	 * @uses $taxonomy_list_widget_plugin, wp_parse_args, apply_filters
	 * @return string or null
	 */
	function widget( $args, $instance ) {
		//Get plugin class for default options and to build widget
		global $taxonomy_list_widget_plugin;
		if( !is_a( $taxonomy_list_widget_plugin, 'taxonomy_list_widget_plugin' ) )
			$taxonomy_list_widget_plugin = new taxonomy_list_widget_plugin;

		//Options
		$instance = wp_parse_args( $instance, $this->defaults );
		extract( $args );
		extract( $instance );

		//Widget
		if( $widget = $taxonomy_list_widget_plugin->render_list( $instance, $this->number ) ) {
			//Wrapper and title
			$output = $before_widget;

			if( !empty( $title ) )
				$output .= $before_title . apply_filters( 'taxonomy_list_widget_title',  $title, $this->number ) . $after_title;

			//Widget
			$output .= $widget;

			//Wrapper
			$output .= $after_widget;

			echo $output;
		}
	}

	/*
	 * Options sanitization
	 * @param array $new_instance
	 * @param array $old_instance
	 * @uses $taxonomy_list_widget_plugin
	 * @return array
	 */
	function update( $new_instance, $old_instance ) {
		//Get plugin class for sanitization function
		global $taxonomy_list_widget_plugin;
		if( !is_a( $taxonomy_list_widget_plugin, 'taxonomy_list_widget_plugin' ) )
			$taxonomy_list_widget_plugin = new taxonomy_list_widget_plugin;

		return $taxonomy_list_widget_plugin->sanitize_options( $new_instance );
	}

	/*
	 * Widget options
	 * @param array $instance
	 * @uses wp_parse_args, get_taxonomies, _e, $this::get_field_id, $this::get_field_name, esc_attr, selected, checked
	 * @return string
	 */
	function form( $instance ) {
		//Get options
		$options = wp_parse_args( $instance, $this->defaults );
		extract( $options );

		if( is_array( $delimiter ) ) {
			$custom_delims = $delimiter;
			$delimiter = 'custom';
		}

		//Get taxonomies and remove certain Core taxonomies that shouldn't be accessed directly.
		$taxonomies = get_taxonomies( array(
			'public' => true,
			'hierarchical' => false
		), 'objects' );

		if( array_key_exists( 'nav_menu', $taxonomies ) )
			unset( $taxonomies[ 'nav_menu' ] );

		if( array_key_exists( 'post_format', $taxonomies ) )
			unset( $taxonomies[ 'post_format' ] );

	?>
		<h3><?php _e( 'Basic Settings' ); ?></h3>

		<p>
			<label for="<?php echo $this->get_field_id( 'taxonomy' ); ?>"><?php _e( 'Taxonomy' ); ?>:</label><br />
			<select name="<?php echo $this->get_field_name( 'taxonomy' ); ?>" id="<?php echo $this->get_field_id( 'taxonomy' ); ?>">
				<?php foreach( $taxonomies as $tax ): ?>
					<option value="<?php echo esc_attr( $tax->name ); ?>"<?php selected( $tax->name, $taxonomy, true ); ?>><?php echo $tax->labels->name; ?></option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'title' ); ?>" class="widefat code" id="<?php echo $this->get_field_id( 'title' ); ?>" value="<?php echo esc_attr( $title ); ?>" />
		</p>

		<h3><?php _e( 'List Style' ); ?></h3>

		<p>
			<input type="radio" name="<?php echo $this->get_field_name( 'delimiter' ); ?>" id="<?php echo $this->get_field_id( 'delimiter-ul' ); ?>" value="ul"<?php checked( 'ul', $delimiter, true ); ?>> <label for="<?php echo $this->get_field_id( 'delimiter-ul' ); ?>"><?php _e( 'Bulleted list' ); ?></label><br />
			<input type="radio" name="<?php echo $this->get_field_name( 'delimiter' ); ?>" id="<?php echo $this->get_field_id( 'delimiter-ol' ); ?>" value="ol"<?php checked( 'ol', $delimiter, true ); ?>> <label for="<?php echo $this->get_field_id( 'delimiter-ol' ); ?>"><?php _e( 'Numbered list' ); ?></label><br />
			<input type="radio" name="<?php echo $this->get_field_name( 'delimiter' ); ?>" id="<?php echo $this->get_field_id( 'delimiter-nl' ); ?>" value="nl"<?php checked( 'nl', $delimiter, true ); ?>> <label for="<?php echo $this->get_field_id( 'delimiter-nl' ); ?>"><?php _e( 'Line break' ); ?></label><br />
			<input type="radio" name="<?php echo $this->get_field_name( 'delimiter' ); ?>" id="<?php echo $this->get_field_id( 'delimiter-custom' ); ?>" value="custom"<?php checked( 'custom', $delimiter, true ); ?>> <label for="<?php echo $this->get_field_id( 'delimiter-custom' ); ?>"><?php _e( 'Custom, as specified below' ); ?></label><br />
		</p>

		<label><strong><?php _e( 'Custom list style' ); ?></strong></label>

		<ul>
			<?php
				$delims = array(
					'before_list' => 'Before List:',
					'after_list' => 'After List:',
					'before_item' => 'Before Item:',
					'after_item' => 'After Item:'
				);

				foreach( $delims as $key => $name ):
				?>
					<li>
						<label for="<?php echo $this->get_field_id( 'delimiter_custom' ); ?>_<?php echo $key; ?>"><?php _e( $name ); ?></label>
						<input type="text" name="<?php echo $this->get_field_name( 'delimiter_custom' ); ?>[<?php echo $key; ?>]" id="<?php echo $this->get_field_id( 'delimiter_custom' ); ?>_<?php echo $key; ?>" class="small-text code" value="<?php if( isset( $custom_delims ) && array_key_exists( $key, $custom_delims ) ) echo esc_attr( $custom_delims[ $key ] ); ?>" />
					</li>
				<?php
				endforeach;
			?>
		</ul>

		<h3><?php _e( 'Order' ); ?></h3>

		<p>
			<label><?php _e( 'Order terms by:' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'orderby' ); ?>" value="name" id="<?php echo $this->get_field_name( 'order_name' ); ?>"<?php checked( $orderby, 'name', true ); ?> />
			<label for="<?php echo $this->get_field_name( 'order_name' ); ?>"><?php _e( 'Name' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'orderby' ); ?>" value="count" id="<?php echo $this->get_field_name( 'order_count' ); ?>"<?php checked( $orderby, 'count', true ); ?> />
			<label for="<?php echo $this->get_field_name( 'order_count' ); ?>"><?php _e( 'Post count' ); ?></label>
		</p>

		<p>
			<label><?php _e( 'Order terms:' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'order' ); ?>" value="ASC" id="<?php echo $this->get_field_name( 'order_asc' ); ?>"<?php checked( $order, 'ASC', true ); ?> />
			<label for="<?php echo $this->get_field_name( 'order_asc' ); ?>"><?php _e( 'Ascending' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'order' ); ?>" value="DESC" id="<?php echo $this->get_field_name( 'order_desc' ); ?>"<?php checked( $order, 'DESC', true ); ?> />
			<label for="<?php echo $this->get_field_name( 'order_desc' ); ?>"><?php _e( 'Descending' ); ?></label>
		</p>

		<h3><?php _e( 'Term Display' ); ?></h3>

		<p>
			<label for="<?php echo $this->get_field_id( 'limit' ); ?>"><?php _e( 'Limit number of terms shown to:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'limit' ); ?>" id="<?php echo $this->get_field_id( 'limit' ); ?>" value="<?php echo intval( $limit ); ?>" size="3" /><br />
			<span class="description"><?php _e( '<small>Enter <strong>0</strong> for no limit.' ); ?></small></span>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'max_name_length' ); ?>"><?php _e( 'Trim long term names to <em>x</em> characters:</label>' ); ?><br />
			<input type="text" name="<?php echo $this->get_field_name( 'max_name_length' ); ?>" id="<?php echo $this->get_field_id( 'max_name_length' ); ?>" value="<?php echo intval( $max_name_length ); ?>" size="3" /><br />
			<span class="description"><?php _e( '<small>Enter <strong>0</strong> to show full tag names.' ); ?></small></span>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'cutoff' ); ?>"><?php _e( 'Indicator that term names are trimmed:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'cutoff' ); ?>" id="<?php echo $this->get_field_id( 'cutoff' ); ?>" value="<?php echo esc_attr( $cutoff ); ?>" size="3" /><br />
			<span class="description"><?php _e( '<small>Leave blank to use an elipsis (&hellip;).</small>' ); ?></span>
		</p>

		<p>
			<input type="checkbox" name="<?php echo $this->get_field_name( 'hide_empty' ); ?>" id="<?php echo $this->get_field_id( 'hide_empty' ); ?>"  value="0"<?php checked( false, $hide_empty, true ); ?> />
			<label for="<?php echo $this->get_field_id( 'hide_empty' ); ?>"><?php _e( 'Include terms that aren\'t assigned to any objects (empty terms).' ); ?></label>
		</p>

		<p>
			<input type="checkbox" name="<?php echo $this->get_field_name( 'post_counts' ); ?>" id="<?php echo $this->get_field_id( 'post_counts' ); ?>"  value="1"<?php checked( true, $post_counts, true ); ?> />
			<label for="<?php echo $this->get_field_id( 'post_counts' ); ?>"><?php _e( 'Display object (post) counts after term names.' ); ?></label>
		</p>

		<h3><?php _e( 'Include/Exclude Terms' ); ?></h3>

		<p>
			<label><?php _e( 'Include/exclude terms:' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'incexc' ); ?>" value="include" id="<?php echo $this->get_field_id( 'include' ); ?>"<?php checked( $incexc, 'include', true ); ?> />
			<label for="<?php echo $this->get_field_id( 'include' ); ?>"><?php _e( 'Include only the term IDs listed below' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'incexc' ); ?>" value="exclude" id="<?php echo $this->get_field_id( 'exclude' ); ?>"<?php checked( $incexc, 'exclude', true ); ?> />
			<label for="<?php echo $this->get_field_id( 'exclude' ); ?>"><?php _e( 'Exclude the term IDs listed below' ); ?></label>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'incexc_ids' ); ?>"><?php _e( 'Term IDs to include/exclude based on above setting:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'incexc_ids' ); ?>" class="widefat code" id="<?php echo $this->get_field_id( 'incexc_ids' ); ?>" value="<?php echo esc_attr( implode( ', ', $incexc_ids ) ); ?>" /><br />
			<span class="description"><?php _e( '<small>Enter comma-separated list of term IDs.</small>' ); ?></span>
		</p>

		<h3><?php _e( 'Advanced' ); ?></h3>

		<p>
			<label for="<?php echo $this->get_field_id( 'threshold' ); ?>"><?php _e( 'Show terms assigned to at least this many posts:' ); ?></label><br />
			<input type="text" name="<?php echo $this->get_field_name( 'threshold' ); ?>" id="<?php echo $this->get_field_id( 'threshold' ); ?>" value="<?php echo intval( $threshold ); ?>" size="3" /><br />
			<span class="description"><?php _e( '<small>Set to <strong>0</strong> to display all terms matching the above criteria.</small>' ); ?></span>
		</p>

		<p>
			<label><?php _e( 'Link relationship:' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'rel' ); ?>" value="nofollow" id="<?php echo $this->get_field_id( 'rel-n' ); ?>"<?php checked( $rel, 'nofollow', true ); ?> />
			<label for="<?php echo $this->get_field_id( 'rel-n' ); ?>"><?php _e( 'nofollow' ); ?></label><br />

			<input type="radio" name="<?php echo $this->get_field_name( 'rel' ); ?>" value="dofollow" id="<?php echo $this->get_field_id( 'rel-d' ); ?>"<?php checked( $rel, 'dofollow', true ); ?> />
			<label for="<?php echo $this->get_field_id( 'rel-d' ); ?>"><?php _e( 'dofollow' ); ?></label><br />

			<span class="description"><?php _e( 'The above setting determines whether or not search engines visit linked pages from links in this widget\'s list.' ); ?></span>
		</p>

	<?php
	}
}

/**
 ** HELPER FUNCTIONS
 **/

/*
 * Render Taxonomy List
 * @param array $options
 * @param string|int $id
 * @uses $taxonomy_list_widget_plugin
 * @return string or false
 */
function taxonomy_list_widget( $options = array(), $id = '' ) {
	global $taxonomy_list_widget_plugin;
	if( !is_a( $taxonomy_list_widget_plugin, 'taxonomy_list_widget_plugin' ) )
		$taxonomy_list_widget_plugin = new taxonomy_list_widget_plugin;

	//Sanitize options
	$options = $taxonomy_list_widget_plugin->sanitize_options( $options );

	return $taxonomy_list_widget_plugin->render_list( $options, $id );
}

/**
 ** LEGACY FUNCTIONS FOR BACKWARDS COMPATIBILITY
 **/

if( !function_exists( 'TLW_direct' ) ):
	/*
	 * Build term list based on provided arguments
	 * @since 0.3
	 * @uses $taxonomy_list_widget_plugin
	 * @return string or false
	 */
	function TLW_direct( $limit = false, $count = false, $before_item = '', $after_item = ' ', $exclude = false ) {
		global $taxonomy_list_widget_plugin;
		if( !is_a( $taxonomy_list_widget_plugin, 'taxonomy_list_widget_plugin' ) )
			$taxonomy_list_widget_plugin = new taxonomy_list_widget_plugin;

		//Build options array from function parameters
		$options = array(
			'max_name_length' => $limit,
			'post_count' => $count,
			'delimiter' => array(
				'before_list' => '',
				'after_list' => '',
				'before_item' => $before_item,
				'after_item' => $after_item
			)
		);

		if( $exclude ) {
			$options[ 'incexc' ] = 'exclude';
			$options[ 'incexc_ids' ] = $exclude;
		}

		//Sanitize options
		$options = $taxonomy_list_widget_plugin->sanitize_options( $options );

		echo '<!-- NOTICE: The function used to generate this dropdown list is deprecated as of version 1.0 of Taxonomy List Widget. You should update your template to use `taxonomy_list_widget` instead. -->' . $taxonomy_list_widget_plugin->render_list( $options, 'legacy_tlw' );
	}
endif;
?>