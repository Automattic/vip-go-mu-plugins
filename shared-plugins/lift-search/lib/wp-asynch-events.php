<?php

/**
 * Transient Async Events
 * Version 0.1-alpha
 */

/**
 * Server for handling TAE crons, restores previous events when cron is running
 */
class TAE_Server {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ), 9 );
	}

	public function init() {
		if ( (defined( 'DOING_CRON' ) && DOING_CRON) ||
			(defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON) ) {

			$keys = get_option( 'tae_event_keys', array( ) );
			foreach ( $keys as $key ) {
				$event = TAE_Async_Event::Restore( $key );
				add_action( 'tae_event_' . $key, array( $event, 'execute' ) );
			}
		}
	}
}
new TAE_Server();

class TAE_Async_Event {

	public $key;
	public $callback;
	public $params;
	public $frequency;
	public $then_events;

	/**
	 * 
	 * @param type $callback
	 * @param type $params
	 * @param type $frequency
	 * @return TAE_Async_Event
	 */
	public static function WatchWhen( $callback, $params = array( ), $frequency = 300, $event_key = null ) {
		if(is_null($event_key))
			$event_key = self::GenerateKey ( $callback, $params );
		if ( !($event = self::Restore( $event_key )) ) {
			$event = new TAE_Async_Event( $callback, $params, $frequency, $event_key );
		}
		return $event;
	}
	
	public static function Unwatch( $event_key ) {
		if($event = self::Restore($event_key)) {
			$event->then_events = array();
			$event->commit();
		}
	}
	
	public static function GenerateKey($callback, $params) {
		return substr( md5( serialize( $callback ) . serialize( $params ) ), 0, 30 );
	}

	/**
	 * Restores an event based on the key
	 * @param string $key
	 * @return TAE_Async_Event|boolean
	 */
	public static function Restore( $key ) {
		if ( $event_data = get_option( 'tae_event_' . $key, false ) ) {
			$event = new TAE_Async_Event( $event_data['callback'], $event_data['params'], $event_data['frequency'], $key );
			$event->then_events = $event_data['then_events'];
			return $event;
		}
		return false;
	}

	public function __construct( $callback, $params, $frequency, $key ) {
		$this->then_events = array( );
		$this->callback = $callback;
		$this->params = $params;
		$this->frequency = $frequency;
		$this->key = $key;

		add_filter( 'cron_schedules', array( $this, '_filter_cron_schedule' ) );
	}

	public function _filter_cron_schedule( $schedules ) {
		$schedules['tae_schedule_' . $this->key] = array(
			'interval' => $this->frequency,
			'display' => 'TAE Event ' . $this->key
		);
		return $schedules;
	}

	/**
	 * 
	 * @param type $callback
	 * @param type $params
	 * @param type $reschedule_on_error
	 * @return TAE_Async_Event
	 */
	public function then( $callback, $params = array( ), $reschedule_on_error = false ) {
		$key = md5( serialize( $callback ) . serialize( $params ) );
		$this->then_events[$key] = array(
			'callback' => $callback,
			'params' => $params,
			'reschedule_on_error' => $reschedule_on_error
		);

		return $this;
	}

	public function execute() {
		if ( !is_callable( $this->callback ) ) {
			_doing_it_wrong( __FUNCTION__, "TAE Events must be callable before 'init' priority '10' for cron to call them", '0.1' );
			return false;
		}
		if ( true === call_user_func_array( $this->callback, $this->params ) ) {
			$keep_events = array( );
			foreach ( $this->then_events as $then_key => $event ) {
				if ( is_callable( $event['callback'] ) ) {
					$result = call_user_func_array( $event['callback'], $event['params'] );
					if ( $event['reschedule_on_error'] && is_wp_error( $result ) ) {
						$keep_events[$then_key] = $event;
					}
				} else {
					_doing_it_wrong( __FUNCTION__, "TAE Events must be callable before 'init' priority '10' for cron to call them", '0.1' );
				}
			}
			$this->then_events = $keep_events;
			$this->commit();
		}
	}

	public function commit() {
		$event_keys = get_option( 'tae_event_keys', array( ) );
		$next_scheduled_time = wp_next_scheduled( 'tae_event_' . $this->key );
		if ( !count( $this->then_events ) ) {
			$event_keys = array_diff( $event_keys, array( $this->key ) );
			delete_option( 'tae_event_' . $this->key );
			wp_unschedule_event( $next_scheduled_time, 'tae_event_' . $this->key );
		} else {
			$event_keys = array_merge( $event_keys, array( $this->key ) );
			update_option( 'tae_event_' . $this->key, array(
				'callback' => $this->callback,
				'params' => $this->params,
				'frequency' => $this->frequency,
				'then_events' => $this->then_events
			) );
			if ( !$next_scheduled_time ) {
				wp_schedule_event( time() + $this->frequency, 'tae_schedule_' . $this->key, 'tae_event_' . $this->key );
			}
		}
		if(empty($event_keys))
			delete_option ( 'tae_event_keys');
		else
			update_option( 'tae_event_keys', $event_keys );
	}

}

/* Test functions
add_action( 'init', function() {
		$version = get_option( 'tae_test_v' );
		$cur_version = 19;
		if ( $version < $cur_version ) {

			TAE_Async_Event::WatchWhen( 'test_time', array( time() + 120 ), 30 )
				->then( 'do_this_now' )
				->commit();
			update_option( 'tae_test_v', $cur_version );
		}
	} );

function test_time( $time ) {
	return $time < time();
}

function do_this_now() {
	update_option( 'tae_answer', 'the time is now ' . time() );
}

register_shutdown_function( function() {
		var_dump( array(
			'version' => get_option( 'tae_test_v' ),
			'tae_anwser' => get_option( 'tae_answer' ),
			'tae_events' => get_option('tae_event_keys'),
		) );
	} );
 * 
 */