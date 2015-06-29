<?php
/**
 * Plugin view manager class
 *
 * @since 1.0
 */
class SF_Plugin_View {

	/**
	 * Hold view filename
	 *
	 * @since 1.0
	 * @param string
	 */
	var $filename;

	/**
	 * Associative array of data that will be available in the view
	 *
	 * @since 1.0
	 * @param array
	 */
	var $data;

	/**
	 * Hold plugin abspath
	 * 
	 * @since 1.0
	 * @param string
	 */
	var $abspath;

	/**
	 * Hold plugin views dirname
	 *
	 * @since 1.0
	 * @param string
	 */
	var $views_dirname = 'views';

	/**
	 * Hold debug enabled status
	 *
	 * @since 1.0
	 * @param bool
	 */
	var $debug = false;

	/**
	 * Returns or render view html
	 * 
	 * @since 1.0
	 * @access public
	 *
	 * @param   string  view filename
	 * @param   array   array of values
	 * @return  string
	 */
	function __construct( $filename = NULL, array $data = NULL ) {

		if ( NULL !== $filename )
			$this->setFileName( $filename );

		if ( NULL !== $data )
			$this->setData( $data );

	}


	/* =Class Setters
	----------------------------------------------- */

	/**
	 * Set path to the directory that contains views directory inside
	 * 
	 * @since 1.0
	 * @access public
	 *
	 * @param string
	 */
	function setAbspath( $abspath = '' ) {
		$this->abspath = $abspath;
	}

	/**
	 * Set name for the views directory
	 * 
	 * @since 1.0
	 * @access public
	 *
	 * @param string
	 */
	function setViewsDirname( $dirname = '' ) {
		$this->views_dirname = $dirname;
	}

	/**
	 * Set debug attribute
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param bool
	 */
	function setDebug( $debug ) {
		$this->debug = (bool) $debug;
	}

	/**
	 * Set View file name without .php
	 * 
	 * @since 1.0
	 * @access public
	 *
	 * @param string
	 */
	function setFileName( $filename ) {
		$this->filename = $filename;
	}

	/**
	 * Set View file data
	 * 
	 * @since 1.0
	 * @access public
	 *
	 * @param string
	 */
	function setData( $data ) {
		$this->data = $data;
	}


	/* = Class Flow
	----------------------------------------------- */

	/**
	 * Try to render view
	 *
	 * @since 1.0
	 * @access public
	 */
	function render() {

		// Check if file exists
		if ( $this->file_exists() ) {

			// Set vaiw variables to common data variable
			if ( isset( $this->data ) AND is_array( $this->data ) ) {
				$data = $this->data;
			}

			// Store rendered view
			ob_start();
			include $this->file;
			$this->render = ob_get_clean();
		} else {
			$this->add_error( 'no_file_found', __( "Can't Load template " . $this->filename , 'plugin_view' ) );
		}
	}

	/**
	 * Check if requested template exists
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @return  bool
	 */
	function file_exists() {

		if ( file_exists( $this->abspath . $this->views_dirname . '/' . $this->filename . '.php' ) ) {

			// Store full file path
			$this->file = $this->abspath . $this->views_dirname . '/' . $this->filename . '.php';
		}

		return isset( $this->file );
	}

	/**
	 * Returns view html
	 * 
	 * @since 1.0
	 * @access private
	 *
	 * @param   string  view filename
	 * @return  string
	 */
	function __toString() {
		$output = '';

		// Check if render attribute presents
		if ( isset( $this->render ) ) {
			$output = $this->render;
		}

		// Maybe add Errors to view
		if ( $this->debug AND isset( $this->error ) AND $this->error->get_error_messages() ) {
			foreach ( $this->error->get_error_messages() as $error ) {
				$output .= '<p class="view-error">'. $error .'</p>';
			}
		}

		return $output;
	}

	/**
	 * Add View Error
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @param string error code
	 * @param string error message
	 * @param mixed error data
	 */
	function add_error( $key = '', $message = '', array $data = NULL ) {
		if ( !isset( $this->error ) ) {
			$this->error = new WP_Error;
		}

		$this->error->add( $key, $message, $data );
	}

}