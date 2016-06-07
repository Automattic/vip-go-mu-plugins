<?php
// Alot of this code is massaged from Andrew Nacin's log-deprecated-notices plugin

class Debug_Bar_Deprecated extends Debug_Bar_Panel {
	var $deprecated_functions = array();
	var $deprecated_files = array();
	var $deprecated_arguments = array();

	function init() {
		$this->title( __('Deprecated', 'debug-bar') );

		add_action( 'deprecated_function_run', array( &$this, 'deprecated_function_run' ), 10, 3 );
		add_action( 'deprecated_file_included', array( &$this, 'deprecated_file_included' ), 10, 4 );
		add_action( 'deprecated_argument_run',  array( &$this, 'deprecated_argument_run' ),  10, 3 );

		// Silence E_NOTICE for deprecated usage.
		foreach ( array( 'function', 'file', 'argument' ) as $item )
			add_filter( "deprecated_{$item}_trigger_error", '__return_false' );
	}

	function prerender() {
		$this->set_visible(
			count( $this->deprecated_functions )
			|| count( $this->deprecated_files )
			|| count( $this->deprecated_arguments )
		);
	}

	function render() {
		echo "<div id='debug-bar-deprecated'>";
		echo '<h2><span>Total Functions:</span>' . number_format( count( $this->deprecated_functions ) ) . "</h2>\n";
		echo '<h2><span>Total Arguments:</span>' . number_format( count( $this->deprecated_arguments ) ) . "</h2>\n";
		echo '<h2><span>Total Files:</span>' . number_format( count( $this->deprecated_files ) ) . "</h2>\n";
		if ( count( $this->deprecated_functions ) ) {
			echo '<ol class="debug-bar-deprecated-list">';
			foreach ( $this->deprecated_functions as $location => $message_stack) {
				list( $message, $stack) = $message_stack;
				echo "<li class='debug-bar-deprecated-function'>";
				echo str_replace(ABSPATH, '', $location) . ' - ' . strip_tags($message);
				echo "<br/>";
				echo $stack;
				echo "</li>";
			}
			echo '</ol>';
		}
		if ( count( $this->deprecated_files ) ) {
			echo '<ol class="debug-bar-deprecated-list">';
			foreach ( $this->deprecated_files as $location => $message_stack) {
				list( $message, $stack) = $message_stack;
				echo "<li class='debug-bar-deprecated-file'>";
				echo str_replace(ABSPATH, '', $location) . ' - ' . strip_tags($message);
				echo "<br/>";
				echo $stack;
				echo "</li>";
			}
			echo '</ol>';
		}
		if ( count( $this->deprecated_arguments ) ) {
			echo '<ol class="debug-bar-deprecated-list">';
			foreach ( $this->deprecated_arguments as $location => $message_stack) {
				list( $message, $stack) = $message_stack;
				echo "<li class='debug-bar-deprecated-argument'>";
				echo str_replace(ABSPATH, '', $location) . ' - ' . strip_tags($message);
				echo "<br/>";
				echo $stack;
				echo "</li>";
			}
			echo '</ol>';
		}
		echo "</div>";
	}

	function deprecated_function_run($function, $replacement, $version) {
		$backtrace = debug_backtrace( false );
		$bt = 4;
		// Check if we're a hook callback.
		if ( ! isset( $backtrace[4]['file'] ) && 'call_user_func_array' == $backtrace[5]['function'] ) {
			$bt = 6;
		}
		$file = $backtrace[ $bt ]['file'];
		$line = $backtrace[ $bt ]['line'];
		if ( ! is_null($replacement) )
			$message = sprintf( __('%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', 'debug-bar'), $function, $version, $replacement );
		else
			$message = sprintf( __('%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', 'debug-bar'), $function, $version );

		$this->deprecated_functions[$file.':'.$line] = array( $message, wp_debug_backtrace_summary( null, $bt ) );
	}

	function deprecated_file_included( $old_file, $replacement, $version, $message ) {
		$backtrace = debug_backtrace( false );
		$file = $backtrace[4]['file'];
		$file_abs = str_replace(ABSPATH, '', $file);
		$line = $backtrace[4]['line'];
		$message = empty( $message ) ? '' : ' ' . $message;
		if ( ! is_null( $replacement ) )
			$message = sprintf( __('%1$s is <strong>deprecated</strong> since version %2$s! Use %3$s instead.', 'debug-bar'), $file_abs, $version, $replacement ) . $message;
		else
			$message = sprintf( __('%1$s is <strong>deprecated</strong> since version %2$s with no alternative available.', 'debug-bar'), $file_abs, $version ) . $message;

		$this->deprecated_functions[$file.':'.$line] = array( $message, wp_debug_backtrace_summary( null, 4 ) );
	}

	function deprecated_argument_run( $function, $message, $version) {
		$backtrace = debug_backtrace( false );
		if ( $function === 'define()' ) {
			$this->deprecated_functions[] = array( $message, '' );
			return;
		}

		$bt = 4;
		if ( ! isset( $backtrace[4]['file'] ) && 'call_user_func_array' == $backtrace[5]['function'] ) {
			$bt = 6;
		}
		$file = $backtrace[ $bt ]['file'];
		$line = $backtrace[ $bt ]['line'];

		$this->deprecated_functions[$file.':'.$line] = array( $message, wp_debug_backtrace_summary( null, $bt ) );
	}
}
