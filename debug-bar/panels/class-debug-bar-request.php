<?php

class Debug_Bar_Request extends Debug_Bar_Panel {
	function init() {
		$this->title( __('Request', 'debug-bar') );
	}

	function prerender() {
		$this->set_visible( ! is_admin() );
	}

	function render() {
		global $wp;

		echo "<div id='debug-bar-request'>";

		if ( empty($wp->request) )
			$request = 'None';
		else
			$request = $wp->request;

		echo '<h3>Request:</h3>';
		echo '<p>' . esc_html( $request ) . '</p>';

		if ( empty($wp->query_string) )
			$query_string = 'None';
		else
			$query_string = $wp->query_string;

		echo '<h3>Query String:</h3>';
		echo '<p>' . esc_html( $query_string ) . '</p>';

		if ( empty($wp->matched_rule) )
			$matched_rule = 'None';
		else
			$matched_rule = $wp->matched_rule;

		echo '<h3>Matched Rewrite Rule:</h3>';
		echo '<p>' . esc_html( $matched_rule ) . '</p>';

		if ( empty($wp->matched_query) )
			$matched_query = 'None';
		else
			$matched_query = $wp->matched_query;

		echo '<h3>Matched Rewrite Query:</h3>';
		echo '<p>' . esc_html( $matched_query ) . '</p>';

		echo '</div>';
	}
}
