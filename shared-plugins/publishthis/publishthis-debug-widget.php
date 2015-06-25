<?php
/**
 * Used to show debug or error messages from our plugin on the
 * clients dashboard. Used for debugging any issues
 * with the client.
 */
PublishThis_Debug_Dashboard_Widget::on_load();

class PublishThis_Debug_Dashboard_Widget {

	static function on_load() {
		add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
	}

	static function admin_init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'wp_dashboard_setup' ) );
	}

	static function wp_dashboard_setup() {
		wp_add_dashboard_widget( 'ptdebug-log-widget', __ ( 'PublishThis Message Log', 'ptdebug-log-widget' ), array( __CLASS__, 'ptwidgetlog_callback' ) );
	}

	static function ptwidgetlog_callback() {
		$lines = get_transient( "pt_local_messages" );

		if ( empty( $lines ) ) {
			echo '<p>' . __ ( 'No messages found.', 'ptdebug-log-widget' ) . '</p>';
			return;
		}

		?><table class="widefat"><?php

		foreach ( $lines as $line ) {
			$debugLine = esc_html( $line );

			if ( !empty( $debugLine ) ) {
				echo "<tr><td>{$debugLine}</td></tr>";
			}
		}

		?></table><?php
	}

}
