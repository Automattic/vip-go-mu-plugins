<?php
/*
Plugin Name: Google Calendar Events
Plugin URI: http://www.rhanney.co.uk/plugins/google-calendar-events
Description: Parses Google Calendar feeds and displays the events as a calendar grid or list on a page, post or widget.
Version: 0.7.2-wpcom
Author: Ross Hanney
Author URI: http://www.rhanney.co.uk
License: GPL2

---

v0.7.2-wpcom is v0.7.2 from the WordPress.org plugin directory with coding standards, etc. improvements
The full log of changes is here:
https://vip-review-trac.wordpress.com/changeset?reponame=&new=711%40plugins%2Fgoogle-calendar-events&old=629%40plugins%2Fgoogle-calendar-events

---

Copyright 2010 Ross Hanney (email: rosshanney@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

---

Contains code inspired by and adapted from GCalendar - http://g4j.laoneo.net/content/extensions/download/cat_view/2-simplepie-gcalendar.html

GCalendar: Copyright 2007-2009 Allon Moritz
*/

define( 'GCE_PLUGIN_NAME', str_replace( '.php', '', basename( __FILE__ ) ) );
define( 'GCE_PLUGIN_URL', plugins_url( '/', __FILE__ ) );
define( 'GCE_PLUGIN_ROOT', trailingslashit( dirname( __FILE__ ) ) );
define( 'GCE_TEXT_DOMAIN', 'google-calendar-events' );
define( 'GCE_OPTIONS_NAME', 'gce_options' );
define( 'GCE_GENERAL_OPTIONS_NAME', 'gce_general' );
define( 'GCE_VERSION', '0.7.2' );

if ( ! class_exists( 'Google_Calendar_Events' ) ) {
	class Google_Calendar_Events {
		function __construct() {
			register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
			add_action( 'init', array( $this, 'init_plugin' ) );
			add_action( 'wp_ajax_gce_ajax', array( $this, 'gce_ajax' ) );
			add_action( 'wp_ajax_nopriv_gce_ajax', array( $this, 'gce_ajax' ) );
			add_action( 'widgets_init', array( $this, 'add_widget' ) );

			//No point doing any of this if currently processing an AJAX request
			if ( ! defined( 'DOING_AJAX' ) || !DOING_AJAX ) {
				add_action( 'admin_menu', array( $this, 'setup_admin' ) );
				add_action( 'admin_init', array( $this, 'init_admin' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'add_styles' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
				add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );
				add_shortcode( 'google-calendar-events', array( $this, 'shortcode_handler' ) );
			}
		}

		//PHP 5.2 is required (json_decode), so if PHP version is lower then 5.2, display an error message and deactivate the plugin
		function activate_plugin(){
			if( version_compare( PHP_VERSION, '5.2', '<' ) ) {
				if( is_admin() && ( ! defined('DOING_AJAX') || ! DOING_AJAX ) ) {
					require_once ABSPATH . '/wp-admin/includes/plugin.php';
					deactivate_plugins( basename( __FILE__ ) );
					wp_die( 'Google Calendar Events requires the server on which your site resides to be running PHP 5.2 or higher. As of version 3.2, WordPress itself will also <a href="http://wordpress.org/news/2010/07/eol-for-php4-and-mysql4">have this requirement</a>. You should get in touch with your web hosting provider and ask them to update PHP.<br /><br /><a href="' . admin_url( 'plugins.php' ) . '">Back to Plugins</a>' );
				}
			}
		}

		//If any new options have been added between versions, this will update any saved feeds with defaults for new options (shouldn't overwrite anything saved)
		function update_settings() {
			//If there are some plugin options in the database, but no version info, then this must be an upgrade from version 0.5 or below, so add flag that will provide user with option to clear old transients
			if ( get_option( GCE_OPTIONS_NAME ) && ! get_option( 'gce_version' ) )
				add_option( 'gce_clear_old_transients', true );

			add_option( 'gce_version', GCE_VERSION );

			add_option( GCE_OPTIONS_NAME );
			add_option( GCE_GENERAL_OPTIONS_NAME );

			//Get feed options
			$options = get_option( GCE_OPTIONS_NAME );

			if ( ! empty( $options ) ) {
				foreach ( $options as $key => $saved_feed_options ) {
					$defaults = array(
						'id' => 1, 
						'title' => '',
						'url' => '',
						'retrieve_from' => 'today',
						'retrieve_from_value' => 0,
						'retrieve_until' => 'any',
						'retrieve_until_value' => 0,
						'max_events' => 25,
						'date_format' => '',
						'time_format' => '',
						'timezone' => 'default',
						'cache_duration' => 43200,
						'multiple_day' => 'false',
						'display_start' => 'time',
						'display_end' => 'time-date',
						'display_location' => '',
						'display_desc' => '',
						'display_link' => 'on',
						'display_start_text' => 'Starts:',
						'display_end_text' => 'Ends:',
						'display_location_text' => 'Location:',
						'display_desc_text' => 'Description:',
						'display_desc_limit' => '',
						'display_link_text' => 'More details',
						'display_link_target' => '',
						'display_separator' => ', ',
						'use_builder' => 'false',
						'builder' => ''
					);

					//If necessary, copy saved behaviour of old show_past_events and day_limit options into the new from / until options
					if ( isset( $saved_feed_options['show_past_events'] ) ) {
						if ( 'true' == $saved_feed_options['show_past_events'] ) {
							$saved_feed_options['retrieve_from'] = 'month-start';
						} else {
							$saved_feed_options['retrieve_from'] = 'today';
						}
					}

					if ( isset( $saved_feed_options['day_limit'] ) && '' != $saved_feed_options['day_limit'] ) {
						$saved_feed_options['retrieve_until'] = 'today';
						$saved_feed_options['retrieve_until_value'] = (int) $saved_feed_options['day_limit'] * 86400;
					}

					//Update old display_start / display_end values
					if ( ! isset( $saved_feed_options['display_start'] ) )
						$saved_feed_options['display_start'] = 'none';
					elseif ( 'on' == $saved_feed_options['display_start'] )
						$saved_feed_options['display_start'] = 'time';

					if( ! isset( $saved_feed_options['display_end'] ) )
						$saved_feed_options['display_end'] = 'none';
					elseif ( 'on' == $saved_feed_options['display_end'] )
						$saved_feed_options['display_end'] = 'time-date';

					//Merge saved options with defaults
					foreach ( $saved_feed_options as $option_name => $option ) {
						$defaults[$option_name] = $saved_feed_options[$option_name];
					}

					$options[$key] = $defaults;
				}
			}

			//Save feed options
			update_option( GCE_OPTIONS_NAME, $options );

			//Get general options
			$options = get_option( GCE_GENERAL_OPTIONS_NAME );

			$defaults = array(
				'stylesheet' => '',
				'javascript' => false,
				'loading' => 'Loading...',
				'error' => 'Events cannot currently be displayed, sorry! Please check back later.',
				'fields' => true,
				'old_stylesheet' => false
			);

			$old_stylesheet_option = get_option( 'gce_stylesheet' );

			//If old custom stylesheet option was set, add it to general options, then delete old option
			if( false !== $old_stylesheet_option ) {
				$defaults['stylesheet'] = $old_stylesheet_option;
				delete_option( 'gce_stylesheet' );
			} elseif ( isset($options['stylesheet'] ) ) {
				$defaults['stylesheet'] = $options['stylesheet'];
			}

			if ( isset($options['javascript'] ) )
				$defaults['javascript'] = $options['javascript'];

			if ( isset( $options['loading'] ) )
				$defaults['loading'] = $options['loading'];

			if ( isset($options['error'] ) )
				$defaults['error'] = $options['error'];

			if ( isset($options['fields'] ) )
				$defaults['fields'] = $options['fields'];

			if( isset( $options['old_stylesheet'] ) )
				$defaults['old_stylesheet'] = $options['old_stylesheet'];

			//Save general options
			update_option( GCE_GENERAL_OPTIONS_NAME, $defaults );
		}

		function init_plugin() {
			//Load text domain for i18n
			load_plugin_textdomain( GCE_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
		}

		//Adds 'Settings' link to main WordPress Plugins page
		function add_settings_link( $links ) {
			array_unshift( $links, '<a href="options-general.php?page=google-calendar-events.php">' . __( 'Settings', GCE_TEXT_DOMAIN ) . '</a>' );
			return $links;
		}

		//Setup admin settings page
		function setup_admin(){
			global $gce_settings_page;

			$gce_settings_page = add_options_page( 'Google Calendar Events', 'Google Calendar Events', 'manage_options', basename( __FILE__ ), array( $this, 'admin_page' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}

		//Add admin JavaScript (to GCE settings page only)
		function enqueue_admin_scripts( $hook_suffix ) {
			global $gce_settings_page;

			if ( $gce_settings_page == $hook_suffix )
				wp_enqueue_script( 'gce_scripts', GCE_PLUGIN_URL . 'js/gce-admin-script.js', array( 'jquery' ) );
		}

		//Prints admin settings page
		function admin_page() {
			?>
			<div class="wrap">
				<div id="icon-options-general" class="icon32"><br /></div>

				<h2><?php _e( 'Google Calendar Events', GCE_TEXT_DOMAIN ); ?></h2>

				<?php if ( get_option( 'gce_clear_old_transients' ) ): ?>
					<div class="error">
						<p><strong><?php _e( 'Notice:', GCE_TEXT_DOMAIN ); ?></strong> <?php _e( 'The way in which Google Calendar Events stores cached data has been much improved in version 0.6. As you have upgraded from a previous version of the plugin, there is likely to be some data from the old caching system hanging around in your database that is now useless. Click below to clear expired cached data from your database.', GCE_TEXT_DOMAIN); ?></p>
						<p><a href="<?php echo wp_nonce_url( add_query_arg( array( 'gce_action' => 'clear_old_transients' ) ), 'gce_action_clear_old_transients' ); ?>"><?php _e( 'Clear expired cached data', GCE_TEXT_DOMAIN ); ?></a></p>
						<p><?php _e( 'or', GCE_TEXT_DOMAIN ); ?></p>
						<p><a href="<?php echo wp_nonce_url( add_query_arg( array( 'gce_action' => 'ignore_old_transients' ) ), 'gce_action_ignore_old_transients' ); ?>"><?php _e( 'Ignore this notice', GCE_TEXT_DOMAIN ); ?></a></p>
					</div>
				<?php endif; ?>

				<form method="post" action="options.php" id="test-form">
					<?php
					if ( isset( $_GET['action'] ) && ! isset( $_GET['settings-updated'] ) ) {
						switch ( $_GET['action'] ) {
							//Add feed section
							case 'add':
								settings_fields( 'gce_options' );
								do_settings_sections( 'add_feed' );
								do_settings_sections( 'add_display' );
								do_settings_sections( 'add_builder' );
								do_settings_sections( 'add_simple_display' );
								?><p class="submit"><input type="submit" class="button-primary submit" name="gce_options[submit_add]" value="<?php _e( 'Add Feed', GCE_TEXT_DOMAIN ); ?>" /></p>
								<p><a href="<?php echo admin_url( 'options-general.php?page=' . GCE_PLUGIN_NAME . '.php' ); ?>" class="button-secondary"><?php _e( 'Cancel', GCE_TEXT_DOMAIN ); ?></a></p><?php
								break;
							case 'refresh':
								settings_fields( 'gce_options' );
								do_settings_sections( 'refresh_feed' );
								?><p class="submit"><input type="submit" class="button-primary submit" name="gce_options[submit_refresh]" value="<?php _e( 'Refresh Feed', GCE_TEXT_DOMAIN ); ?>" /></p>
								<p><a href="<?php echo admin_url( 'options-general.php?page=' . GCE_PLUGIN_NAME . '.php' ); ?>" class="button-secondary"><?php _e( 'Cancel', GCE_TEXT_DOMAIN ); ?></a></p><?php
								break;
							//Edit feed section
							case 'edit':
								settings_fields( 'gce_options' );
								do_settings_sections( 'edit_feed' );
								do_settings_sections( 'edit_display' );
								do_settings_sections( 'edit_builder' );
								do_settings_sections( 'edit_simple_display' );
								?><p class="submit"><input type="submit" class="button-primary submit" name="gce_options[submit_edit]" value="<?php _e( 'Save Changes', GCE_TEXT_DOMAIN ); ?>" /></p>
								<p><a href="<?php echo admin_url( 'options-general.php?page=' . GCE_PLUGIN_NAME . '.php' ); ?>" class="button-secondary"><?php _e( 'Cancel', GCE_TEXT_DOMAIN ); ?></a></p><?php
								break;
							//Delete feed section
							case 'delete':
								settings_fields( 'gce_options' );
								do_settings_sections( 'delete_feed' );
								?><p class="submit"><input type="submit" class="button-primary submit" name="gce_options[submit_delete]" value="<?php _e( 'Delete Feed', GCE_TEXT_DOMAIN ); ?>" /></p>
								<p><a href="<?php echo admin_url( 'options-general.php?page=' . GCE_PLUGIN_NAME . '.php' ); ?>" class="button-secondary"><?php _e( 'Cancel', GCE_TEXT_DOMAIN ); ?></a></p><?php
						}
					}else{
						//Main admin section
						settings_fields( 'gce_general' );
						require_once GCE_PLUGIN_ROOT . 'admin/main.php';
					}
					?>
				</form>
			</div>
			<?php
		}

		//Initialize admin stuff
		function init_admin() {
			$version = get_option( 'gce_version' );

			//If updating from before 0.7, set old_stylesheet option to true
			if ( false === $version || version_compare( $version, '0.7', '<' ) ) {
				$options = get_option( GCE_GENERAL_OPTIONS_NAME );
				$options['old_stylesheet'] = true;
			}

			//If updating from a previous version, update the settings
			if ( false === $version || version_compare( $version, GCE_VERSION, '<' ) )
				$this->update_settings();

			//If the message about old transients was displayed, check authority and intention, and then either clear transients or clear flag
			if ( isset( $_GET['gce_action'] ) && current_user_can( 'manage_options' ) ) {
				switch ( $_GET['gce_action'] ) {
					case 'clear_old_transients':
						check_admin_referer( 'gce_action_clear_old_transients' );
						$this->clear_old_transients();
						add_settings_error( 'gce_options', 'gce_cleared_old_transients', __( 'Old cached data cleared.', GCE_TEXT_DOMAIN ), 'updated' );
						break;
					case 'ignore_old_transients':
						check_admin_referer( 'gce_action_ignore_old_transients' );
						delete_option( 'gce_clear_old_transients' );
				}
			}

			register_setting( 'gce_options', 'gce_options', array( $this, 'validate_feed_options' ) );
			register_setting( 'gce_general', 'gce_general', array( $this, 'validate_general_options' ) );

			require_once GCE_PLUGIN_ROOT . 'admin/add.php';
			require_once GCE_PLUGIN_ROOT . 'admin/edit.php';
			require_once GCE_PLUGIN_ROOT . 'admin/delete.php';
			require_once GCE_PLUGIN_ROOT . 'admin/refresh.php';
		}

		//Clears any expired transients from the database
		function clear_old_transients() {
			global $wpdb;

			//Retrieve names of all transients
			$transients = $wpdb->get_results( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '%transient%' AND option_name NOT LIKE '%transient_timeout%'" );

			if ( ! empty( $transients ) ) {
				foreach ( $transients as $transient ) {
					//Attempt to retrieve the transient. If it has expired, it will be deleted
					get_transient( str_replace( '_transient_', '', $transient->option_name ) );
				}
			}

			//Remove the flag
			delete_option( 'gce_clear_old_transients' );
		}

		//Register the widget
		function add_widget() {
			require_once GCE_PLUGIN_ROOT . 'widget/gce-widget.php';
			return register_widget( 'GCE_Widget' );
		}

		//Check / validate submitted feed options data before being stored
		function validate_feed_options( $input ) {
			//Get saved options
			$options = get_option( GCE_OPTIONS_NAME );

			if ( isset( $input['submit_delete'] ) ) {
				//If delete button was clicked, delete feed from options array and remove associated transients
				unset( $options[$input['id']] );
				$this->delete_feed_transients( (int) $input['id'] );
				add_settings_error( 'gce_options', 'gce_deleted', __( sprintf('Feed %s deleted.', absint( $input['id'] ) ), GCE_TEXT_DOMAIN ), 'updated' );
			} elseif ( isset($input['submit_refresh'] ) ) {
				//If refresh button was clicked, delete transients associated with feed
				$this->delete_feed_transients( (int) $input['id'] );
				add_settings_error( 'gce_options', 'gce_refreshed', __( sprintf('Cached data for feed %s cleared.', absint( $input['id'] ) ), GCE_TEXT_DOMAIN ), 'updated' );
			} else {
				//Otherwise, validate options and add / update them

				//Check id is positive integer
				$id = absint( $input['id'] );
				// Sanitize title text
				$title = sanitize_text_field( $input['title'] );
				//Escape feed url
				$url = esc_url( $input['url'] );

				//Array of valid options for retrieve_from and retrieve_until settings
				$valid_retrieve_options = array( 'now', 'today', 'week', 'month-start', 'month-end', 'any', 'date' );

				$retrieve_from = 'today';
				$retrieve_from_value = 0;

				//Ensure retrieve_from is valid
				if( in_array( $input['retrieve_from'], $valid_retrieve_options ) ) {
					$retrieve_from = $input['retrieve_from'];
					$retrieve_from_value = (int) $input['retrieve_from_value'];
				}

				$retrieve_until = 'any';
				$retrieve_until_value = 0;

				//Ensure retrieve_until is valid
				if ( in_array( $input['retrieve_until'], $valid_retrieve_options ) ) {
					$retrieve_until = $input['retrieve_until'];
					$retrieve_until_value = (int) $input['retrieve_until_value'];
				}

				//Check max events is a positive integer. If absint returns 0, reset to default (25)
				$max_events = ( 0 == absint($input['max_events'] ) ) ? 25 : absint( $input['max_events'] );

				$date_format = wp_filter_kses( $input['date_format'] );
				$time_format = wp_filter_kses( $input['time_format'] );

				// Sanitize the timezone
				$timezone = sanitize_text_field( $input['timezone'] );

				// Cache must be greater than 5 minutes
				$cache_duration = absint( $input['cache_duration'] );
				if ( $cache_duration < 300 )
					$cache_duration = 300;

				$multiple_day = ( isset( $input['multiple_day'] ) ) ? 'true' : 'false';

				// Sanitize the display_start and display_end
				$display_start = sanitize_text_field( $input['display_start'] );
				$display_end = sanitize_text_field( $input['display_end'] );

				//Display options must be 'on' or null
				$display_location = ( isset( $input['display_location'] ) ) ? 'on' : null;
				$display_desc = ( isset( $input['display_desc'] ) ) ? 'on' : null;
				$display_link = ( isset( $input['display_link'] ) ) ? 'on' : null;
				$display_link_target = ( isset( $input['display_link_target'] ) ) ? 'on' : null;

				//Filter display text
				$display_start_text = wp_filter_kses( $input['display_start_text'] );
				$display_end_text = wp_filter_kses( $input['display_end_text'] );
				$display_location_text = wp_filter_kses( $input['display_location_text'] );
				$display_desc_text = wp_filter_kses( $input['display_desc_text'] );
				$display_link_text = wp_filter_kses( $input['display_link_text'] );

				$display_separator = wp_filter_kses( $input['display_separator'] );

				$display_desc_limit = ( 0 == absint( $input['display_desc_limit'] ) ) ? '' : absint( $input['display_desc_limit'] );

				$use_builder = ( 'false' == $input['use_builder'] ) ? 'false' : 'true';
				$builder = wp_kses_post( $input['builder'] );

				//Fill options array with validated values
				$options[$id] = array(
					'id' => $id, 
					'title' => $title,
					'url' => $url,
					'retrieve_from' => $retrieve_from,
					'retrieve_until' => $retrieve_until,
					'retrieve_from_value' => $retrieve_from_value,
					'retrieve_until_value' => $retrieve_until_value,
					'max_events' => $max_events,
					'date_format' => $date_format,
					'time_format' => $time_format,
					'timezone' => $timezone,
					'cache_duration' => $cache_duration,
					'multiple_day' => $multiple_day,
					'display_start' => $display_start,
					'display_end' => $display_end,
					'display_location' => $display_location,
					'display_desc' => $display_desc,
					'display_link' => $display_link,
					'display_start_text' => $display_start_text,
					'display_end_text' => $display_end_text,
					'display_location_text' => $display_location_text,
					'display_desc_text' => $display_desc_text,
					'display_desc_limit' => $display_desc_limit,
					'display_link_text' => $display_link_text,
					'display_link_target' => $display_link_target,
					'display_separator' => $display_separator,
					'use_builder' => $use_builder,
					'builder' => $builder
				);

				if ( isset( $input['submit_add'] ) )
					add_settings_error( 'gce_options', 'gce_added', __( sprintf( 'Feed %s added.', absint( $input['id'] ) ), GCE_TEXT_DOMAIN ), 'updated' );
				else
					add_settings_error( 'gce_options', 'gce_edited', __( sprintf( 'Settings for feed %s updated.', absint( $input['id'] ) ), GCE_TEXT_DOMAIN ), 'updated' );
			}

			return $options;
		}

		//Validate submitted general options
		function validate_general_options( $input ) {
			$options = get_option(GCE_GENERAL_OPTIONS_NAME);

			$options['stylesheet'] = esc_url( $input['stylesheet'] );
			$options['javascript'] = ( isset( $input['javascript'] ) ) ? true : false;
			$options['loading'] = sanitize_text_field( $input['loading'] );
			$options['error'] = wp_filter_kses( $input['error'] );
			$options['fields'] = ( isset( $input['fields'] ) ) ? true : false;
			$options['old_stylesheet'] = ( isset( $input['old_stylesheet'] ) ) ? true : false;

			add_settings_error( 'gce_general', 'gce_general_updated', __( 'General options updated.', GCE_TEXT_DOMAIN ), 'updated' );

			return $options;
		}

		//Delete all transients (cached feed data) associated with feed specified
		function delete_feed_transients( $id ) {
				delete_transient( 'gce_feed_' . $id );
				delete_transient( 'gce_feed_' . $id . '_url' );
		}

		//Handles the shortcode stuff
		function shortcode_handler( $atts ) {
			$options = get_option( GCE_OPTIONS_NAME );

			//Check that any feeds have been added
			if ( is_array( $options ) && ! empty( $options ) ) {
				extract( shortcode_atts( array(
					'id' => '',
					'type' => 'grid',
					'title' => null,
					'max' => 0,
					'order' => 'asc'
				), $atts ) );

				$no_feeds_exist = true;
				$feed_ids = array();

				if ( '' != $id ) {
					//Break comma delimited list of feed ids into array
					$feed_ids = explode( ',', str_replace( ' ', '', $id ) );

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

				//Ensure max events is a positive integer
				$max_events = absint( $max );

				//Ensure sort order is asc or desc
				$sort_order = ( 'desc' == $order ) ? 'desc' : 'asc';

				//Check that at least one valid feed id has been entered
				if ( empty( $feed_ids ) || $no_feeds_exist ) {
					return __( 'No valid Feed IDs have been entered for this shortcode. Please check that you have entered the IDs correctly and that the Feeds have not been deleted.', GCE_TEXT_DOMAIN );
				} else {
					//Turns feed_ids back into string of feed ids delimited by '-' ('1-2-3-4' for example)
					$feed_ids = implode( '-', $feed_ids );

					//If title has been omitted from shortcode, set title_text to null, otherwise set to title (even if empty string)
					$title_text = ( false === $title ) ? null : $title;

					switch ( $type ) {
						case 'grid':
							return gce_print_grid( $feed_ids, $title_text, $max_events );
						case 'ajax':
							return gce_print_grid( $feed_ids, $title_text, $max_events, true );
						case 'list':
							return gce_print_list( $feed_ids, $title_text, $max_events, $sort_order );
						case 'list-grouped':
							return gce_print_list( $feed_ids, $title_text, $max_events, $sort_order, true );
					}
				}
			} else {
				return __( 'No feeds have been added yet. You can add a feed in the Google Calendar Events settings.', GCE_TEXT_DOMAIN );
			}
		}

		//Adds the required CSS
		function add_styles() {
			wp_enqueue_style( 'gce_styles', GCE_PLUGIN_URL . 'css/gce-style.css' );

			$options = get_option( GCE_GENERAL_OPTIONS_NAME );

			//If old stylesheet option is enabled, enqueue old styles
			if ( $options['old_stylesheet'] )
				wp_enqueue_style( 'gce_old_styles', GCE_PLUGIN_URL . 'css/gce-old-style.css' );

			//If user has entered a URL to a custom stylesheet, enqueue it too
			if( '' != $options['stylesheet'] )
				wp_enqueue_style( 'gce_custom_styles', $options['stylesheet'] );
		}

		//Adds the required scripts
		function add_scripts() {
			$options = get_option( GCE_GENERAL_OPTIONS_NAME );
			$add_to_footer = (bool) $options['javascript'];

			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'gce_jquery_qtip', GCE_PLUGIN_URL . 'js/jquery-qtip.js', array( 'jquery' ), null, $add_to_footer );
			wp_enqueue_script( 'gce_scripts', GCE_PLUGIN_URL . 'js/gce-script.js', array( 'jquery' ), null, $add_to_footer );
			wp_localize_script( 'gce_scripts', 'GoogleCalendarEvents', array(
				'ajaxurl' => admin_url( 'admin-ajax.php', is_ssl() ? 'https' : 'http' ),
				'loading' => $options['loading']
			) );
		}

		//AJAX stuffs
		function gce_ajax() {
			if ( isset( $_GET['gce_feed_ids'] ) ) {
				$ids = sanitize_text_field( $_GET['gce_feed_ids'] );
				$title = sanitize_text_field( $_GET['gce_title_text'] );
				$max = intval( $_GET['gce_max_events'] );
				$month = intval( $_GET['gce_month'] );
				$year = intval( $_GET['gce_year'] );

				$title = ( 'null' == $title ) ? null : $title;

				if ( 'page' == $_GET['gce_type'] ) {
					//The page grid markup to be returned via AJAX
					echo gce_print_grid( $ids, $title, $max, true, $month, $year );
				} elseif ( 'widget' == $_GET['gce_type'] ) {
					$widget = esc_html( $_GET['gce_widget_id'] );

					//The widget grid markup to be returned via AJAX
					gce_widget_content_grid( $ids, $title, $max, $widget, true, $month, $year );
				}
			}
			die();
		}
	}
}

function gce_print_list( $feed_ids, $title_text, $max_events, $sort_order, $grouped = false ) {
	require_once GCE_PLUGIN_ROOT . 'inc/gce-parser.php';

	$ids = explode( '-', $feed_ids );

	//Create new GCE_Parser object, passing array of feed id(s)
	$list = new GCE_Parser( $ids, $title_text, $max_events, $sort_order );

	$num_errors = $list->get_num_errors();

	//If there are less errors than feeds parsed, at least one feed must have parsed successfully so continue to display the list
	if ( $num_errors < count( $ids ) ) {
		$markup = '<div class="gce-page-list">' . $list->get_list( $grouped ) . '</div>';

		//If there was at least one error, return the list markup with error messages (for admins only)
		if ( $num_errors > 0 && current_user_can( 'manage_options' ) )
			return $list->error_messages() . $markup;

		//Otherwise just return the list markup
		return $markup;
	} else {
		//If current user is an admin, display an error message explaining problem(s). Otherwise, display a 'nice' error messsage
		if ( current_user_can( 'manage_options' ) ) {
			return $list->error_messages();
		} else {
			$options = get_option( GCE_GENERAL_OPTIONS_NAME );
			return wp_kses_post( $options['error'] );
		}
	}
}

function gce_print_grid( $feed_ids, $title_text, $max_events, $ajaxified = false, $month = null, $year = null ) {
	require_once GCE_PLUGIN_ROOT . 'inc/gce-parser.php';

	$ids = explode( '-', $feed_ids );

	//Create new GCE_Parser object, passing array of feed id(s) returned from gce_get_feed_ids()
	$grid = new GCE_Parser( $ids, $title_text, $max_events );

	$num_errors = $grid->get_num_errors();

	//If there are less errors than feeds parsed, at least one feed must have parsed successfully so continue to display the grid
	if ( $num_errors < count( $ids ) ) {
		$feed_ids = esc_attr( $feed_ids );
		$title_text = isset( $title_text ) ? esc_html( $title_text) : 'null';

		$markup = '<div class="gce-page-grid" id="gce-page-grid-' . $feed_ids . '">';

		//Add AJAX script if required
		if ( $ajaxified )
			$markup .= '<script type="text/javascript">jQuery(document).ready(function($){gce_ajaxify("gce-page-grid-' . $feed_ids . '", "' . $feed_ids . '", "' . absint( $max_events ) . '", "' . $title_text . '", "page");});</script>';

		$markup .= $grid->get_grid( $year, $month, $ajaxified ) . '</div>';

		//If there was at least one error, return the grid markup with an error message (for admins only)
		if ( $num_errors > 0 && current_user_can( 'manage_options' ) )
			return $grid->error_messages() . $markup;

		//Otherwise just return the grid markup
		return $markup;
	} else {
		//If current user is an admin, display an error message explaining problem. Otherwise, display a 'nice' error messsage
		if ( current_user_can( 'manage_options' ) ) {
			return $grid->error_messages();
		} else {
			$options = get_option( GCE_GENERAL_OPTIONS_NAME );
			return wp_kses_post( $options['error'] );
		}
	}
}

$gce = new Google_Calendar_Events();
?>