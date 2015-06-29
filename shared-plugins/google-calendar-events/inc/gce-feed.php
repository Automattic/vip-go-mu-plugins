<?php
class GCE_Feed{
	private $feed_id = 1;
	private $feed_title = '';
	private $feed_url = '';
	private $max_events = 25;
	private $cache_duration = 43200;
	private $date_format = '';
	private $time_format = '';
	private $timezone = '';
	private $display_opts = array();
	private $multi_day = false;
	private $feed_start = 0;
	private $feed_end = 2145916800;
	private $use_builder = true;
	private $builder = '';
	private $events = array();
	private $error = false;

	function init() {
		require_once GCE_PLUGIN_ROOT . 'inc/gce-event.php';

		//Break the feed URL up into its parts (scheme, host, path, query)
		$url_parts = parse_url( $this->feed_url );

		$scheme_and_host = $url_parts['scheme'] . '://' . $url_parts['host'];

		//Remove the exisitng projection from the path, and replace it with '/full-noattendees'
		$path = substr( $url_parts['path'], 0, strrpos( $url_parts['path'], '/' ) ) . '/full-noattendees';

		//Add the default parameters to the querystring (retrieving JSON, not XML)
		$query = '?alt=json&singleevents=true&sortorder=ascending&orderby=starttime';

		$gmt_offset = get_option( 'gmt_offset' ) * 3600;

		//Append the feed specific parameters to the querystring
		$query .= '&start-min=' . date( 'Y-m-d\TH:i:s', $this->feed_start - $gmt_offset );
		$query .= '&start-max=' . date( 'Y-m-d\TH:i:s', $this->feed_end - $gmt_offset );
		$query .= '&max-results=' . $this->max_events;

		if ( ! empty( $this->timezone ) )
			$query .= '&ctz=' . $this->timezone;

		//If enabled, use experimental 'fields' parameter of Google Data API, so that only necessary data is retrieved. This *significantly* reduces amount of data to retrieve and process
		$general_options = get_option( GCE_GENERAL_OPTIONS_NAME );
		if ( $general_options['fields'] )
			$query .= '&fields=entry(title,link[@rel="alternate"],content,gd:where,gd:when,gCal:uid)';

		//Put the URL back together
		$url = $scheme_and_host . $path . $query;

		//Attempt to retrieve the cached feed data
		$this->events = get_transient( 'gce_feed_' . $this->feed_id );

		//If the cached feed data isn't valid any more (has expired), or the URL has changed (settings have changed), then the feed data needs to be retrieved and decoded again
		if ( false === $this->events || 0 == $this->cache_duration || get_transient( 'gce_feed_' . $this->feed_id . '_url' ) != $url ) {
			$this->events = array();

			//Retrieve the feed data
			$raw_data = wp_remote_get( $url, array(
				'sslverify' => false, //sslverify is set to false to ensure https URLs work reliably. Data source is Google's servers, so is trustworthy
				'timeout'   => 10     //Increase timeout from the default 5 seconds to ensure even large feeds are retrieved successfully
			) );

			//If $raw_data is a WP_Error, something went wrong
			if ( ! is_wp_error( $raw_data ) ) {
				//If response code isn't 200, something went wrong
				if ( 200 == $raw_data['response']['code'] ) {
					//Attempt to convert the returned JSON into an array
					$raw_data = json_decode( $raw_data['body'], true );

					//If decoding was successful
					if ( ! empty( $raw_data ) ) {
						//If there are some entries (events) to process
						if ( isset( $raw_data['feed']['entry'] ) ) {
							//Loop through each event, extracting the relevant information
							foreach ( $raw_data['feed']['entry'] as $event ) {
								$id          = esc_html( substr( $event['gCal$uid']['value'], 0, strpos( $event['gCal$uid']['value'], '@' ) ) );
								$title       = esc_html( $event['title']['$t'] );
								$description = esc_html( $event['content']['$t'] );
								$link        = esc_url( $event['link'][0]['href'] );
								$location    = esc_html( $event['gd$where'][0]['valueString'] );
								$start_time  = $this->iso_to_ts( $event['gd$when'][0]['startTime'] );
								$end_time    = $this->iso_to_ts( $event['gd$when'][0]['endTime'] );

								//Create a GCE_Event using the above data. Add it to the array of events
								$this->events[] = new GCE_Event( $id, $title, $description, $location, $start_time, $end_time, $link );
							}
						}

						if ( 0 != $this->cache_duration ) {
							//Cache the feed data
							set_transient( 'gce_feed_' . $this->feed_id, $this->events, $this->cache_duration );
							set_transient( 'gce_feed_' . $this->feed_id . '_url', $url, $this->cache_duration );
						}
					} else {
						//json_decode failed
						$this->error = __( 'Some data was retrieved, but could not be parsed successfully. Please ensure your feed URL is correct.', GCE_TEXT_DOMAIN );
					}
				} else {
					//The response code wasn't 200, so generate a helpful(ish) error message depending on error code 
					switch ( $raw_data['response']['code'] ) {
						case 404:
							$this->error = __( 'The feed could not be found (404). Please ensure your feed URL is correct.', GCE_TEXT_DOMAIN );
							break;
						case 403:
							$this->error = __( 'Access to this feed was denied (403). Please ensure you have public sharing enabled for your calendar.', GCE_TEXT_DOMAIN );
							break;
						default:
							$this->error = sprintf( __( 'The feed data could not be retrieved. Error code: %s. Please ensure your feed URL is correct.', GCE_TEXT_DOMAIN ), $raw_data['response']['code'] );
					}
				}
			}else{
				//Generate an error message from the returned WP_Error
				$this->error = $raw_data->get_error_message() . ' Please ensure your feed URL is correct.';
			}
		}

		//Makes sure each event knows it came from this feed
		foreach ( $this->events as $event ) {
			$event->set_feed( $this );
		}
	}

	//Convert an ISO date/time to a UNIX timestamp
	function iso_to_ts( $iso ) {
		sscanf( $iso, "%u-%u-%uT%u:%u:%uZ", $year, $month, $day, $hour, $minute, $second );
		return mktime( $hour, $minute, $second, $month, $day, $year );
	}

	//Return error message, or false if no error occurred
	function error() {
		return $this->error;
	}

	//Setters

	function set_feed_id( $v ) {
		$this->feed_id = $v;
	}

	function set_feed_title( $v ) {
		$this->feed_title = $v;
	}

	function set_feed_url( $v ) {
		$this->feed_url = $v;
	}

	function set_max_events( $v ) {
		$this->max_events = $v;
	}

	function set_cache_duration( $v ) {
		$this->cache_duration = $v;
	}

	function set_date_format( $v ) {
		$this->date_format = $v;
	}

	function set_time_format( $v ) {
		$this->time_format = $v;
	}

	function set_timezone( $v ) {
		$this->timezone = $v;
	}

	function set_display_options( $v ) {
		$this->display_opts = $v;
	}

	function set_multi_day( $v ) {
		$this->multi_day = $v;
	}

	function set_feed_start( $v ) {
		$this->feed_start = $v;
	}

	function set_feed_end( $v ) {
		$this->feed_end = $v;
	}

	function set_use_builder( $v ) {
		$this->use_builder = $v;
	}

	function set_builder( $v ) {
		$this->builder = $v;
	}

	//Getters

	function get_events() {
		return $this->events;
	}

	function get_feed_id() {
		return $this->feed_id;
	}

	function get_feed_title() {
		return $this->feed_title;
	}

	function get_feed_url() {
		return $this->feed_url;
	}

	function get_date_format() {
		return $this->date_format;
	}

	function get_time_format() {
		return $this->time_format;
	}

	function get_display_options() {
		return $this->display_opts;
	}

	function get_multi_day() {
		return $this->multi_day;
	}

	function get_feed_start() {
		return $this->feed_start;
	}

	function get_feed_end() {
		return $this->feed_end;
	}

	function get_timezone() {
		return $this->timezone;
	}

	function get_use_builder() {
		return $this->use_builder;
	}

	function get_builder() {
		return $this->builder;
	}
}
?>