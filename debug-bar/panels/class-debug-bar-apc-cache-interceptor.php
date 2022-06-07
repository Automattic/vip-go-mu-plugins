<?php

class WPCOM_Debug_Bar_Apcu_Hotcache extends Debug_Bar_Panel {
	function init() {
		$this->title( __( 'APCU Hot-Cache', 'debug-bar' ) );
	}

	function prerender() {
		$this->set_visible( true );
	}

	function render() {
		global $apc_cache_interceptor;
		echo "<div id='apcu-stats'>";
		if ( ! isset( $apc_cache_interceptor ) || ! is_object( $apc_cache_interceptor ) ) {
			echo '<h2>APCU Hot-Caching is currently disabled</h2></div>';
			return;
		}
		$apc_cache_interceptor->stats();
		echo '</div>';
	}
}
