<?php

class Debug_Bar_Panel {
	var $_title = '';
	var $_visible = true;

	function Debug_Bar_Panel( $title='' ) {
		$this->title( $title );

		if ( $this->init() === false ) {
			$this->set_visible( false );
			return;
		}

		add_filter( 'debug_bar_classes', array( &$this, 'debug_bar_classes' ) );
	}

	/**
	 * Initializes the panel.
	 */
	function init() {}

	function prerender() {}

	/**
	 * Renders the panel.
	 */
	function render() {}

	function is_visible() {
		return $this->_visible;
	}

	function set_visible( $visible ) {
		$this->_visible = $visible;
	}

	function title( $title=NULL ) {
		if ( ! isset( $title ) )
			return $this->_title;
		$this->_title = $title;
	}

	function debug_bar_classes( $classes ) {
		return $classes;
	}
}
