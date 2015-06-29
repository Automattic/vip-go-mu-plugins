<?php
class GCE_Parser {
	private $feeds = array();
	private $merged_feed_data = array();
	private $errors = array();
	private $title = null;
	private $max_events_display = 0;
	private $start_of_week = 0;
	private $sort_order = 'asc';

	function __construct( $feed_ids, $title_text = null, $max_events = 0, $sort_order = 'asc' ) {
		require_once GCE_PLUGIN_ROOT . 'inc/gce-feed.php';

		$this->title = $title_text;
		$this->max_events_display = $max_events;
		$this->sort_order = $sort_order;

		//Get the feed options
		$options = get_option( GCE_OPTIONS_NAME );

		$this->start_of_week = get_option( 'start_of_week' );

		foreach ( $feed_ids as $single_feed ) {
			//Get the options for this particular feed
			if ( isset( $options[$single_feed] ) ) {
				$feed_options = $options[$single_feed];

				$feed = new GCE_Feed();

				$feed->set_feed_id( $feed_options['id'] );
				$feed->set_feed_title( $feed_options['title'] );
				$feed->set_feed_url( $feed_options['url'] );
				$feed->set_max_events( $feed_options['max_events'] );
				$feed->set_cache_duration( $feed_options['cache_duration'] );

				//Set the timezone if anything other than default
				if ( 'default' != $feed_options['timezone'] )
					$feed->set_timezone( $feed_options['timezone'] );

				//Set the start date to the appropriate value based on the retrieve_from option
				switch ( $feed_options['retrieve_from'] ) {
					//Don't just use time() for 'now', as this will effectively make cache duration 1 second. Instead set to previous minute. Events in Google Calendar cannot be set to precision of seconds anyway
					case 'now':
						$feed->set_feed_start( mktime( date( 'H' ), date( 'i' ), 0, date( 'm' ), date( 'j' ), date( 'Y' ) ) + $feed_options['retrieve_from_value'] );
						break;
					case 'today':
						$feed->set_feed_start( mktime( 0, 0, 0, date( 'm' ), date( 'j' ), date( 'Y' ) ) + $feed_options['retrieve_from_value'] );
						break;
					case 'week':
						$feed->set_feed_start( mktime( 0, 0, 0, date( 'm' ), ( date( 'j' ) - date( 'w' ) + $this->start_of_week ), date( 'Y' ) ) + $feed_options['retrieve_from_value'] );
						break;
					case 'month-start':
						$feed->set_feed_start( mktime( 0, 0, 0, date( 'm' ), 1, date( 'Y' ) ) + $feed_options['retrieve_from_value'] );
						break;
					case 'month-end':
						$feed->set_feed_start( mktime( 0, 0, 0, date( 'm' ) + 1, 1, date( 'Y' ) ) + $feed_options['retrieve_from_value'] );
						break;
					case 'date':
						$feed->set_feed_start( $feed_options['retrieve_from_value'] );
						break;
					default:
						$feed->set_feed_start( 0 ); //any - 1970-01-01 00:00
				}

				//Set the end date to the appropriate value based on the retrieve_until option
				switch ( $feed_options['retrieve_until'] ) {
					case 'now':
						$feed->set_feed_end( mktime( date( 'H' ), date( 'i' ), 0, date( 'm' ), date( 'j' ), date( 'Y' ) ) + $feed_options['retrieve_until_value'] );
						break;
					case 'today':
						$feed->set_feed_end( mktime( 0, 0, 0, date( 'm' ), date( 'j' ), date( 'Y' ) ) + $feed_options['retrieve_until_value'] );
						break;
					case 'week':
						$feed->set_feed_end( mktime( 0, 0, 0, date( 'm' ), ( date( 'j' ) - date( 'w' ) + $this->start_of_week ), date( 'Y' ) ) + $feed_options['retrieve_until_value'] );
						break;
					case 'month-start':
						$feed->set_feed_end( mktime( 0, 0, 0, date( 'm' ), 1, date( 'Y' ) ) + $feed_options['retrieve_until_value'] );
						break;
					case 'month-end':
						$feed->set_feed_end( mktime( 0, 0, 0, date( 'm' ) + 1, 1, date( 'Y' ) ) + $feed_options['retrieve_until_value'] );
						break;
					case 'date':
						$feed->set_feed_end( $feed_options['retrieve_until_value'] );
						break;
					case 'any':
						$feed->set_feed_end( 2145916800 ); //any - 2038-01-01 00:00
				}

				//Set date and time formats. If they have not been set by user, set to global WordPress formats 
				$feed->set_date_format( ( empty( $feed_options['date_format'] ) ) ? get_option( 'date_format' ) : $feed_options['date_format'] );
				$feed->set_time_format( ( empty( $feed_options['time_format'] ) ) ? get_option( 'time_format' ) : $feed_options['time_format'] );
				//Set whether to handle multiple day events
				$feed->set_multi_day( ( 'true' == $feed_options['multiple_day'] ) ? true : false );

				//Sets all display options
				$feed->set_display_options( array(
					'display_start' => $feed_options['display_start'],
					'display_end' => $feed_options['display_end'],
					'display_location' => $feed_options['display_location'],
					'display_desc' => $feed_options['display_desc'],
					'display_link' => $feed_options['display_link'],
					'display_start_text' => $feed_options['display_start_text'],
					'display_end_text' => $feed_options['display_end_text'],
					'display_location_text' => $feed_options['display_location_text'],
					'display_desc_text' => $feed_options['display_desc_text'],
					'display_desc_limit' => $feed_options['display_desc_limit'],
					'display_link_text' => $feed_options['display_link_text'],
					'display_link_target' => $feed_options['display_link_target'],
					'display_separator' => $feed_options['display_separator']
				) );

				$feed->set_use_builder( ( 'true' == $feed_options['use_builder'] ) ? true : false );
				$feed->set_builder( $feed_options['builder'] );

				//Parse the feed
				$feed->init();

				//Add feed object to array of feeds
				$this->feeds[$single_feed] = $feed;
			}
		}

		$this->merged_feed_data = array();

		//Merge the feeds together into one array of events
		foreach ( $this->feeds as $feed_id => $feed ) {
			$errors_occurred = $feed->error();

			if ( false === $errors_occurred )
				$this->merged_feed_data = array_merge( $this->merged_feed_data, $feed->get_events() );
			else
				$this->errors[$feed_id] = $errors_occurred;
		}

		//Sort the items into date order
		if ( ! empty( $this->merged_feed_data ) )
			usort( $this->merged_feed_data, array( $this, 'compare' ) );
	}

	//Comparison function for use when sorting merged feed data (with usort)
	function compare( $event1, $event2 ) {
		//Sort ascending or descending
		if ( 'asc' == $this->sort_order )
			return $event1->get_start_time() - $event2->get_start_time();

		return $event2->get_start_time() - $event1->get_start_time();
	}

	//Returns number of errors that have occurred
	function get_num_errors() {
		return count( $this->errors );
	}

	//Outputs a message describing each error that has occurred
	function error_messages() {
		$message = '<p>' . __( '1 or more of your feeds could not be displayed. The following errors occurred:', GCE_TEXT_DOMAIN ) . '</p><ul>';

		foreach ( $this->errors as $feed_id => $error ) {
			$message .= '<li><strong>' . sprintf( __( 'Feed %s:', GCE_TEXT_DOMAIN ), $feed_id ) . '</strong> ' . $error . '</li>';
		}

		return $message . '</ul>';
	}

	//Returns array of days with events, with sub-arrays of events for that day
	function get_event_days() {
		$event_days = array();

		//Total number of events retrieved
		$count = count( $this->merged_feed_data );

		//If maximum events to display is 0 (unlimited) set $max to 1, otherwise use maximum of events specified by user
		$max = ( 0 == $this->max_events_display ) ? 1 : $this->max_events_display;

		//Loop through entire array of events, or until maximum number of events to be displayed has been reached
		for ( $i = 0; $i < $count && $max > 0; $i++ ) {
			$event = $this->merged_feed_data[$i];

			//Check that event ends, or starts (or both) within the required date range. This prevents all-day events from before / after date range from showing up.
			if ( $event->get_end_time() > $event->get_feed()->get_feed_start() && $event->get_start_time() < $event->get_feed()->get_feed_end() ) {
				foreach ( $event->get_days() as $day ) {
					$event_days[$day][] = $event;
				}

				//If maximum events to display isn't 0 (unlimited) decrement $max counter
				if ( 0 != $this->max_events_display )
					$max--;
			}
		}

		return $event_days;
	}

	//Returns grid markup
	function get_grid ( $year = null, $month = null, $ajaxified = false ) {
		require_once GCE_PLUGIN_ROOT . 'inc/php-calendar.php';

		$time_now = current_time( 'timestamp' );

		//If year and month have not been passed as paramaters, use current month and year
		if( ! isset( $year ) )
			$year = date( 'Y', $time_now );

		if( ! isset( $month ) )
			$month = date( 'm', $time_now );

		//Get timestamps for the start and end of current month
		$current_month_start = mktime( 0, 0, 0, date( 'm', $time_now ), 1, date( 'Y', $time_now ) );
		$current_month_end = mktime( 0, 0, 0, date( 'm', $time_now ) + 1, 1, date( 'Y', $time_now ) );

		//Get timestamps for the start and end of the month to be displayed in the grid
		$display_month_start = mktime( 0, 0, 0, $month, 1, $year );
		$display_month_end = mktime( 0, 0, 0, $month + 1, 1, $year );

		//It should always be possible to navigate to the current month, even if it doesn't have any events
		//So, if the display month is before the current month, set $nav_next to true, otherwise false
		//If the display month is after the current month, set $nav_prev to true, otherwise false
		$nav_next = ( $display_month_start < $current_month_start );
		$nav_prev = ( $display_month_start >= $current_month_end );

		//Get events data
		$event_days = $this->get_event_days();

		//If event_days is empty, then there are no events in the feed(s), so set ajaxified to false (Prevents AJAX calendar from allowing to endlessly click through months with no events)
		if ( empty( $event_days ) )
			$ajaxified = false;

		$today = mktime( 0, 0, 0, date( 'm', $time_now ), date( 'd', $time_now ), date( 'Y', $time_now ) );

		$i = 1;

		foreach ( $event_days as $key => $event_day ) {
			//If event day is in the month and year specified (by $month and $year)
			if ( $key >= $display_month_start && $key < $display_month_end ) {
				//Create array of CSS classes. Add gce-has-events
				$css_classes = array( 'gce-has-events' );

				//Create markup for display
				$markup = '<div class="gce-event-info">';

				//If title option has been set for display, add it
				if ( isset( $this->title ) )
					$markup .= '<div class="gce-tooltip-title">' . esc_html( $this->title ) . ' ' . date_i18n( $event_day[0]->get_feed()->get_date_format(), $key ) . '</div>';

				$markup .= '<ul>';

				foreach ( $event_day as $num_in_day => $event ) {
					$feed_id = absint( $event->get_feed()->get_feed_id() );
					$markup .= '<li class="gce-tooltip-feed-' . $feed_id . '">' . $event->get_event_markup( 'tooltip', $num_in_day, $i ) . '</li>';

					//Add CSS class for the feed from which this event comes. If there are multiple events from the same feed on the same day, the CSS class will only be added once.
					$css_classes['feed-' . $feed_id] = 'gce-feed-' . $feed_id;

					$i++;
				}

				$markup .= '</ul></div>';

				//If number of CSS classes is greater than 2 ('gce-has-events' plus one specific feed class) then there must be events from multiple feeds on this day, so add gce-multiple CSS class
				if ( count( $css_classes ) > 2 )
					$css_classes[] = 'gce-multiple';

				//If event day is today, add gce-today CSS class, otherwise add past or future class
				if ( $key == $today )
					$css_classes[] = 'gce-today gce-today-has-events';
				elseif ( $key < $today )
					$css_classes[] = 'gce-day-past';
				else
					$css_classes[] = 'gce-day-future';

				//Change array entry to array of link href, CSS classes, and markup for use in gce_generate_calendar (below)
				$event_days[$key] = array( null, implode( ' ', $css_classes ), $markup );
			} elseif ( $key < $display_month_start ) {
				//This day is before the display month, so set $nav_prev to true. Remove the day from $event_days, as it's no use for displaying this month
				$nav_prev = true;
				unset( $event_days[$key] );
			} else {
				//This day is after the display month, so set $nav_next to true. Remove the day from $event_days, as it's no use for displaying this month
				$nav_next = true;
				unset( $event_days[$key] );
			}
		}

		//Ensures that gce-today CSS class is added even if there are no events for 'today'. A bit messy :(
		if ( ! isset( $event_days[$today] ) )
			$event_days[$today] = array( null, 'gce-today gce-today-no-events', null );

		$pn = array();

		//Only add previous / next functionality if AJAX grid is enabled
		if ( $ajaxified ) {
			//If there are events to display in a previous month, add previous month link
			$prev_key = ( $nav_prev ) ? '&laquo;' : '&nbsp;';
			$prev = ( $nav_prev ) ? date( 'm-Y', mktime( 0, 0, 0, $month - 1, 1, $year ) ) : null;

			//If there are events to display in a future month, add next month link
			$next_key = ( $nav_next ) ? '&raquo;' : '&nbsp;';
			$next = ( $nav_next ) ? date( 'm-Y', mktime( 0, 0, 0, $month + 1, 1, $year ) ) : null;

			//Array of previous and next link stuff for use in gce_generate_calendar (below)
			$pn = array( $prev_key => $prev, $next_key => $next );
		}

		//Generate the calendar markup and return it
		return gce_generate_calendar( $year, $month, $event_days, 1, null, $this->start_of_week, $pn );
	}

	function get_list( $grouped = false ) {
		$time_now = current_time( 'timestamp' );

		$event_days = $this->get_event_days();

		//If event_days is empty, there are no events in the feed(s), so return a message indicating this
		if( empty( $event_days) )
			return '<p>' . __( 'There are currently no events to display.', GCE_TEXT_DOMAIN ) . '</p>';

		$today = mktime( 0, 0, 0, date( 'm', $time_now ), date( 'd', $time_now ), date( 'Y', $time_now ) );

		$i = 1;

		$markup = '<ul class="gce-list">';

		foreach ( $event_days as $key => $event_day ) {
			//If this is a grouped list, add the date title and begin the nested list for this day
			if ( $grouped ) {
				$markup .=
					'<li' . ( ( $key == $today ) ? ' class="gce-today"' : '' ) . '>' .
					'<div class="gce-list-title">' . esc_html( $this->title ) . ' ' . date_i18n( $event_day[0]->get_feed()->get_date_format(), $key ) . '</div>' .
					'<ul>';
			}

			foreach ( $event_day as $num_in_day => $event ) {
				//Create the markup for this event
				$markup .=
					'<li class="gce-feed-' . $event->get_feed()->get_feed_id() . '">' .
					//If this isn't a grouped list and a date title should be displayed, add the date title
					( ( ! $grouped && isset( $this->title ) ) ? '<div class="gce-list-title">' . esc_html( $this->title ) . ' ' . date_i18n( $event->get_feed()->get_date_format(), $key ) . '</div>' : '' ) .
					//Add the event markup
					$event->get_event_markup( 'list', $num_in_day, $i ) .
					'</li>';

				$i++;
			}

			//If this is a grouped list, close the nested list for this day
			if ( $grouped )
				$markup .= '</ul></li>';
		}

		$markup .= '</ul>';

		return $markup;
	}
}
?>