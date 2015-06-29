<?php
class GCE_Event{
	private $id;
	private $title;
	private $description;
	private $location;
	private $start_time;
	private $end_time;
	private $link;
	private $type;
	private $num_in_day;
	private $pos;
	private $feed;
	private $day_type;
	private $time_now;
	private $regex;

	function __construct( $id, $title, $description, $location, $start_time, $end_time, $link ) {
		$this->id = $id;
		$this->title = $title;
		$this->description = $description;
		$this->location = $location;
		$this->start_time = $start_time;
		$this->end_time = $end_time;
		$this->link = $link;

		//Calculate which day type this event is (SWD = single whole day, SPD = single part day, MWD = multiple whole day, MPD = multiple part day)
		if ( ( $start_time + 86400 ) <= $end_time ) {
			if ( ( $start_time + 86400 ) == $end_time ) {
				$this->day_type = 'SWD';
			} else {
				if ( ( '12:00 am' == date( 'g:i a', $start_time ) ) && ( '12:00 am' == date( 'g:i a', $end_time ) ) ) {
					$this->day_type = 'MWD';
				} else {
					$this->day_type = 'MPD';
				}
			}
		} else {
			$this->day_type = 'SPD';
		}
	}

	function set_feed( $feed ) {
		$this->feed = $feed;
	}

	function get_feed() {
		return $this->feed;
	}

	function get_start_time() {
		return $this->start_time;
	}

	function get_end_time() {
		return $this->end_time;
	}

	function get_day_type() {
		return $this->day_type;
	}

	//Returns an array of days (as UNIX timestamps) that this events spans
	function get_days() {
		//Round start date to nearest day
		$start_time = mktime( 0, 0, 0, date( 'm', $this->start_time ), date( 'd', $this->start_time ) , date( 'Y', $this->start_time ) );

		$days = array();

		//If multiple day events should be handled, and this event is a multi-day event, add multiple day event to required days
		if ( $this->feed->get_multi_day() && ( 'MPD' == $this->day_type || 'MWD' == $this->day_type ) ) {
			$on_next_day = true;
			$next_day = $start_time;

			while ( $on_next_day ) {
				//If the end time of the event is after 00:00 on the next day (therefore, not doesn't end on this day)
				if ( $this->end_time > $next_day ) {
					//If $next_day is within the event retrieval date range (specified by retrieve events from / until settings)
					if ( $next_day >= $this->feed->get_feed_start() && $next_day < $this->feed->get_feed_end() ) {
						$days[] = $next_day;
					}
				} else {
					$on_next_day = false;
				}
				$next_day += 86400;
			}
		} else {
			//Add event into array of events for that day
			$days[] = $start_time;
		}

		return $days;
	}

	//Returns the markup for this event, so that it can be used in the construction of a grid / list
	function get_event_markup( $display_type, $num_in_day, $num ) {
		//Set the display type (either tooltip or list)
		$this->type = $display_type;

		//Set which number event this is in day (first in day etc)
		$this->num_in_day = $num_in_day;

		//Set the position of this event in array of events currently being processed
		$this->pos = $num;

		$this->time_now = current_time( 'timestamp' );

		//Use the builder or the old display options to create the markup, depending on user choice
		if ( $this->feed->get_use_builder() )
			return $this->use_builder();

		return $this->use_old_display_options();
	}

	//Return the event markup using the builder
	function use_builder() {
		//Array of valid shortcodes
		$shortcodes = array(
			//Event / feed information shortcodes

			'event-title',    //The event title
			'start-time',     //The start time of the event (uses the time format from the feed options, if it is set. Otherwise uses the default WordPress time format)
			'start-date',     //The start date of the event (uses the date format from the feed options, if it is set. Otherwise uses the default WordPress date format)
			'start-custom',   //The start time / date of the event (uses a custom PHP date format, specified in the 'format' attribute)
			'start-human',    //The difference between the start time of the event and the time now, in human-readable format, such as '1 hour', '4 days', '15 mins'
			'end-time',       //The end time of the event (uses the time format from the feed options, if it is set. Otherwise uses the default WordPress time format)
			'end-date',       //The end date of the event (uses the date format from the feed options, if it is set. Otherwise uses the default WordPress date format)
			'end-custom',     //The end time / date of the event (uses a custom PHP date format, specified in the 'format' attribute)
			'end-human',      //The difference between the end time of the event and the time now, in human-readable format, such as '1 hour', '4 days', '15 mins'
			'location',       //The event location
			'description',    //The event deescription (number of words can be limited by the 'limit' attribute)
			'link',           //Anything within this shortcode (including further shortcodes) will be linked to the Google Calendar page for this event
			'url',            //The raw link URL to the Google Calendar page for this event (can be used to construct more customized links)
			'feed-id',        //The ID of this feed (Can be useful for constructing feed specific CSS classes)
			'feed-title',     //The feed title
			'maps-link',      //Anything within this shortcode (including further shortcodes) will be linked to a Google Maps page based on whatever is specified for the event location
			'length',         //How long the events lasts, in human-readable format
			'event-num',      //The position of the event in the current list, or the position of the event in the current month (for grids)
			'event-id',       //The event UID (unique identifier assigned by Google)
			'cal-id',         //The calendar ID

			//Anything between the opening and closing tags of the following logical shortcodes (including further shortcodes) will only be displayed if:

			'if-all-day',     //This is an all-day event
			'if-not-all-day', //This is not an all-day event
			'if-title',       //The event has a title
			'if-description', //The event has a description
			'if-location',    //The event has a location
			'if-tooltip',     //The current display type is 'tooltip'
			'if-list',        //The current display type is 'list'
			'if-now',         //The event is taking place now (after the start time, but before the end time)
			'if-not-now',     //The event is not taking place now (may have ended or not yet started)
			'if-started',     //The event has started (and even if it has ended)
			'if-not-started', //The event has not yet started
			'if-ended',       //The event has ended
			'if-not-ended',   //The event has not ended (and even if it hasn't started)
			'if-first',       //The event is the first in the day
			'if-not-first',   //The event is not the first in the day
			'if-multi-day',   //The event spans multiple days
			'if-single-day'   //The event does not span multiple days
		);

		$this->regex = '/(.?)\[(' . implode( '|', $shortcodes ) . ')\b(.*?)(?:(\/))?\](?:(.+?)\[\/\2\])?(.?)/s';

		return $this->look_for_shortcodes( $this->feed->get_builder() );
	}

	//Look through the EDB markup for shortcodes
	function look_for_shortcodes( $markup ) {
		return preg_replace_callback( $this->regex, array( $this, 'process_shortcode' ), $markup );
	}

	//Parse a shortcode, returning the appropriate event information
	//Much of this code is 'borrowed' from WordPress' own shortcode handling stuff!
	function process_shortcode( $m ) {
		if ( '[' == $m[1] && ']' == $m[6] )
			return substr( $m[0], 1, -1 );

		//Extract any attributes contained in the shortcode
		extract( shortcode_atts( array(
			'newwindow' => 'false',
			'format'    => '',
			'limit'     => '0',
			'html'      => 'false',
			'markdown'  => 'false',
			'precision' => '1',
			'offset'    => '0',
			'autolink'  => 'true'
		), shortcode_parse_atts( $m[3] ) ) );

		//Sanitize the attributes
		$newwindow = ( 'true' === $newwindow );
		$format    = esc_attr( $format );
		$limit     = absint( $limit );
		$html      = ( 'true' === $html );
		$markdown  = ( 'true' === $markdown );
		$precision = absint( $precision );
		$offset    = intval( $offset );
		$autolink  = ( 'true' === $autolink );

		//Do the appropriate stuff depending on which shortcode we're looking at. See valid shortcode list (above) for explanation of each shortcode
		switch ( $m[2] ) {
			case 'event-title':
				$title = esc_html( trim( $this->title ) );

				if ( $markdown && function_exists( 'Markdown' ) )
					$title = Markdown( $title );

				if ( $html )
					$title = wp_kses_post( html_entity_decode( $title ) );

				return $m[1] . $title . $m[6];

			case 'start-time':
				return $m[1] . date_i18n( $this->feed->get_time_format(), $this->start_time + $offset ) . $m[6];

			case 'start-date':
				return $m[1] . date_i18n( $this->feed->get_date_format(), $this->start_time + $offset ) . $m[6];

			case 'start-custom':
				return $m[1] . date_i18n( $format, $this->start_time + $offset ) . $m[6];

			case 'start-human':
				return $m[1] . $this->gce_human_time_diff( $this->start_time + $offset, $this->time_now, $precision ) . $m[6];

			case 'end-time':
				return $m[1] . date_i18n( $this->feed->get_time_format(), $this->end_time + $offset ) . $m[6];

			case 'end-date':
				return $m[1] . date_i18n( $this->feed->get_date_format(), $this->end_time + $offset ) . $m[6];

			case 'end-custom':
				return $m[1] . date_i18n( $format, $this->end_time + $offset ) . $m[6];

			case 'end-human':
				return $m[1] . $this->gce_human_time_diff( $this->end_time + $offset, $this->time_now, $precision ) . $m[6];

			case 'location':
				$location = esc_html( trim( $this->location ) );

				if ( $markdown && function_exists( 'Markdown' ) )
					$location = Markdown( $location );

				if ( $html )
					$location = wp_kses_post( html_entity_decode( $location ) );

				return $m[1] . $location . $m[6];

			case 'description':
				$description = esc_html( trim( $this->description ) );

				//If a word limit has been set, trim the description to the required length
				if ( 0 != $limit ) {
					preg_match( '/([\S]+\s*){0,' . $limit . '}/', esc_html( $this->description ), $description );
					$description = trim( $description[0] );
				}

				if ( $markdown || $html ) {
					if ( $markdown && function_exists( 'Markdown' ) )
						$description = Markdown( $description );

					if ( $html )
						$description = wp_kses_post( html_entity_decode( $description ) );
				}else{
					//Otherwise, preserve line breaks
					$description = nl2br( $description );

					//Make URLs clickable if required
					if ( $autolink )
						$description = make_clickable( $description );
				}

				return $m[1] . $description . $m[6];

			case 'link':
				$new_window = ( $newwindow ) ? ' target="_blank"' : '';
				return $m[1] . '<a href="' . esc_url( $this->link . '&ctz=' . $this->feed->get_timezone() ) . '"' . $new_window . '>' . $this->look_for_shortcodes( $m[5] ) . '</a>' . $m[6];

			case 'url':
				return $m[1] . esc_url( $this->link . '&ctz=' . $this->feed->get_timezone() ) . $m[6];

			case 'feed-id':
				return $m[1] . intval( $this->feed->get_feed_id() ) . $m[6];

			case 'feed-title':
				return $m[1] . esc_html( $this->feed->get_feed_title() ) . $m[6];

			case 'maps-link':
				$new_window = ( $newwindow ) ? ' target="_blank"' : '';
				return $m[1] . '<a href="' . esc_url( 'http://maps.google.com?q=' . urlencode( $this->location ) ) . '"' . $new_window . '>' . $this->look_for_shortcodes( $m[5] ) . '</a>' . $m[6];

			case 'length':
				return $m[1] . $this->gce_human_time_diff( $this->start_time, $this->end_time, $precision ) . $m[6];

			case 'event-num':
				return $m[1] . intval( $this->pos ) . $m[6];

			case 'event-id':
				return $m[1] . esc_html( $this->id ) . $m[6];

			case 'cal-id':
				$cal_id = explode( '/', $this->feed->get_feed_url() );
				return $m[1] . esc_html( $cal_id[5] ) . $m[6];

			case 'if-all-day':
				if ( 'SWD' == $this->day_type || 'MWD' == $this->day_type )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-not-all-day':
				if ( 'SPD' == $this->day_type || 'MPD' == $this->day_type )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-title':
				if ( '' != $this->title )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-description':
				if ( '' != $this->description )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-location':
				if ( '' != $this->location )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-tooltip':
				if ( 'tooltip' == $this->type )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-list':
				if ( 'list' == $this->type )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-now':
				if ( $this->time_now >= $this->start_time && $this->time_now < $this->end_time )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-not-now':
				if ( $this->end_time < $this->time_now || $this->start_time > $this->time_now )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-started':
				if ( $this->start_time < $this->time_now )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-not-started':
				if ( $this->start_time > $this->time_now )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-ended':
				if ( $this->end_time < $this->time_now )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-not-ended':
				if ( $this->end_time > $this->time_now )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-first':
				if ( 0 == $this->num_in_day )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-not-first':
				if ( 0 != $this->num_in_day )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-multi-day':
				if ( 'MPD' == $this->day_type || 'MWD' == $this->day_type )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';

			case 'if-single-day':
				if ( 'SPD' == $this->day_type || 'SWD' == $this->day_type )
					return $m[1] . $this->look_for_shortcodes( $m[5] ) . $m[6];

				return '';
		}
	}

	//Return the event markup using the old display options
	function use_old_display_options() {
		$display_options = $this->feed->get_display_options();

		$markup = '<p class="gce-' . $this->type . '-event">' . esc_html( $this->title )  . '</p>';

		$start_end = array();

		//If start date / time should be displayed, set up array of start date and time
		if ( 'none' != $display_options['display_start'] ) {
			$sd = $this->start_time;
			$start_end['start'] = array(
				'time' => date_i18n( $this->feed->get_time_format(), $sd ),
				'date' => date_i18n( $this->feed->get_date_format(), $sd )
			);
		}

		//If end date / time should be displayed, set up array of end date and time
		if ( 'none' != $display_options['display_end'] ) {
			$ed = $this->end_time;
			$start_end['end'] = array(
				'time' => date_i18n( $this->feed->get_time_format(), $ed ),
				'date' => date_i18n( $this->feed->get_date_format(), $ed )
			);
		}

		//Add the correct start / end, date / time information to $markup
		foreach ( $start_end as $start_or_end => $info ) {
			$markup .= '<p class="gce-' . $this->type . '-' . $start_or_end . '"><span>' . esc_html( $display_options['display_' . $start_or_end . '_text'] ) . '</span> ';

			switch ( $display_options['display_' . $start_or_end] ) {
				case 'time': $markup .= esc_html( $info['time'] );
					break;
				case 'date': $markup .= esc_html( $info['date'] );
					break;
				case 'time-date': $markup .= esc_html( $info['time'] . $display_options['display_separator'] . $info['date'] );
					break;
				case 'date-time': $markup .= esc_html( $info['date'] . $display_options['display_separator'] . $info['time'] );
			}

			$markup .= '</p>';
		}

		//If location should be displayed (and is not empty) add to $markup
		if ( isset( $display_options['display_location'] ) ) {
			$event_location = $this->location;
			if ( '' != $event_location )
				$markup .= '<p class="gce-' . $this->type . '-loc"><span>' . esc_html( $display_options['display_location_text'] ) . '</span> ' . esc_html( $event_location ) . '</p>';
		}

		//If description should be displayed (and is not empty) add to $markup
		if ( isset($display_options['display_desc'] ) ) {
			$event_desc = $this->description;

			if ( '' != $event_desc ) {
				//Limit number of words of description to display, if required
				if ( '' != $display_options['display_desc_limit'] ) {
					preg_match( '/([\S]+\s*){0,' . $display_options['display_desc_limit'] . '}/', $this->description, $event_desc );
					$event_desc = trim( $event_desc[0] );
				}

				$markup .= '<p class="gce-' . $this->type . '-desc"><span>' . $display_options['display_desc_text'] . '</span> ' . make_clickable( nl2br( esc_html( $event_desc ) ) ) . '</p>';
			}
		}

		//If link should be displayed add to $markup
		if ( isset($display_options['display_link'] ) )
			$markup .= '<p class="gce-' . $this->type . '-link"><a href="' . esc_url( $this->link ) . '&amp;ctz=' . esc_html( $this->feed->get_timezone() ) . '"' . ( ( isset( $display_options['display_link_target'] ) ) ? ' target="_blank"' : '' ) . '>' . esc_html( $display_options['display_link_text'] ) . '</a></p>';

		return $markup;
	}

	//Returns the difference between two times in human-readable format. Based on a patch for human_time_diff posted in the WordPress trac (http://core.trac.wordpress.org/ticket/9272) by Viper007Bond 
	function gce_human_time_diff( $from, $to = '', $limit = 1 ) {
		$units = array(
			31556926 => array( __( '%s year', GCE_TEXT_DOMAIN ),  __( '%s years', GCE_TEXT_DOMAIN ) ),
			2629744  => array( __( '%s month', GCE_TEXT_DOMAIN ), __( '%s months', GCE_TEXT_DOMAIN ) ),
			604800   => array( __( '%s week', GCE_TEXT_DOMAIN ),  __( '%s weeks', GCE_TEXT_DOMAIN ) ),
			86400    => array( __( '%s day', GCE_TEXT_DOMAIN ),   __( '%s days', GCE_TEXT_DOMAIN ) ),
			3600     => array( __( '%s hour', GCE_TEXT_DOMAIN ),  __( '%s hours', GCE_TEXT_DOMAIN ) ),
			60       => array( __( '%s min', GCE_TEXT_DOMAIN ),   __( '%s mins', GCE_TEXT_DOMAIN ) ),
		);

		if ( empty( $to ) )
			$to = time(); 

		$from = (int) $from;
		$to   = (int) $to;
		$diff = (int) abs( $to - $from );

		$items = 0;
		$output = array();

		foreach ( $units as $unitsec => $unitnames ) {
			if ( $items >= $limit )
				break; 

			if ( $diff < $unitsec )
				continue; 

			$numthisunits = floor( $diff / $unitsec ); 
			$diff = $diff - ( $numthisunits * $unitsec ); 
			$items++; 

			if ( $numthisunits > 0 )
				$output[] = sprintf( _n( $unitnames[0], $unitnames[1], $numthisunits ), $numthisunits ); 
		} 

		$seperator = _x( ', ', 'human_time_diff' ); 

		if ( ! empty( $output ) ) {
			return implode( $seperator, $output ); 
		} else {
			$smallest = array_pop( $units ); 
			return sprintf( $smallest[0], 1 ); 
		} 
	} 
}
?>