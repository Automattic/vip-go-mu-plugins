<?php

// Administration page base class

abstract class scbAdminPage {
	/** Page args
	 * $page_title string (mandatory)
	 * $parent (string)  (default: options-general.php)
	 * $capability (string)  (default: 'manage_options')
	 * $menu_title (string)  (default: $page_title)
	 * $page_slug (string)  (default: sanitized $page_title)
	 * $toplevel (string)  If not empty, will create a new top level menu (for expected values see http://codex.wordpress.org/Administration_Menus#Using_add_submenu_page)
	 * - $icon_url (string)  URL to an icon for the top level menu
	 * - $position (int)  Position of the toplevel menu (caution!)
	 * $screen_icon (string)  The icon type to use in the screen header
	 * $nonce string  (default: $page_slug)
	 * $action_link (string|bool)  Text of the action link on the Plugins page (default: 'Settings')
	 * $admin_action_priority int  The priority that the admin_menu action should be executed at (default: 10)
	 */
	protected $args;

	// URL to the current plugin directory.
	// Useful for adding css and js files
	protected $plugin_url;

	// Created at page init
	protected $pagehook;

	// scbOptions object holder
	// Normally, it's used for storing formdata
	protected $options;
	protected $option_name;

	// l10n
	protected $textdomain;


//  ____________REGISTRATION COMPONENT____________


	private static $registered = array();

	static function register( $class, $file, $options = null ) {
		if ( isset( self::$registered[$class] ) )
			return false;

		self::$registered[$class] = array( $file, $options );

		add_action( '_admin_menu', array( __CLASS__, '_pages_init' ) );

		return true;
	}

	static function replace( $old_class, $new_class ) {
		if ( ! isset( self::$registered[$old_class] ) )
			return false;

		self::$registered[$new_class] = self::$registered[$old_class];
		unset( self::$registered[$old_class] );

		return true;
	}

	static function remove( $class ) {
		if ( ! isset( self::$registered[$class] ) )
			return false;

		unset( self::$registered[$class] );

		return true;
	}

	static function _pages_init() {
		foreach ( self::$registered as $class => $args )
			new $class( $args[0], $args[1] );
	}


//  ____________MAIN METHODS____________


	// Constructor
	function __construct( $file = false, $options = null ) {
		if ( is_a( $options, 'scbOptions' ) )
			$this->options = $options;

		$this->setup();
		$this->check_args();

		if ( isset( $this->option_name ) ) {
			add_action( 'admin_init', array( $this, 'option_init' ) );
			if ( function_exists( 'settings_errors' ) )
				add_action( 'admin_notices', 'settings_errors' );
		}

		add_action( 'admin_menu', array( $this, 'page_init' ), $this->args['admin_action_priority'] );
		add_filter( 'contextual_help', array( $this, '_contextual_help' ), 10, 2 );

		if ( $file ) {
			$this->file = $file;
			$this->plugin_url = plugin_dir_url( $file );

			if ( $this->args['action_link'] )
				add_filter( 'plugin_action_links_' . plugin_basename( $file ), array( $this, '_action_link' ) );
		}
	}

	// This is where all the page args can be set
	function setup(){}

	// This is where the css and js go
	// Both wp_enqueue_*() and inline code can be added
	function page_head(){}

	// This is where the contextual help goes
	// @return string
	function page_help(){}

	// A generic page header
	function page_header() {
		echo "<div class='wrap'>\n";
		screen_icon( $this->args['screen_icon'] );
		echo "<h2>" . $this->args['page_title'] . "</h2>\n";
	}

	// This is where the page content goes
	abstract function page_content();

	// A generic page footer
	function page_footer() {
		echo "</div>\n";
	}

	// This is where the form data should be validated
	function validate( $new_data, $old_data ) {
		return $new_data;
	}

	// Manually handle option saving ( use Settings API instead )
	function form_handler() {
		if ( empty( $_POST['action'] ) )
			return false;

		check_admin_referer( $this->nonce );

		if ( !isset($this->options) ) {
			trigger_error('options handler not set', E_USER_WARNING);
			return false;
		}

		$new_data = wp_array_slice_assoc( $_POST, array_keys( $this->options->get_defaults() ) );

		$new_data = stripslashes_deep( $new_data );

		$new_data = $this->validate( $new_data, $this->options->get() );

		$this->options->set( $new_data );

		$this->admin_msg();
	}

	// Manually generate a standard admin notice ( use Settings API instead )
	function admin_msg( $msg = '', $class = "updated" ) {
		if ( empty( $msg ) )
			$msg = __( 'Settings <strong>saved</strong>.', $this->textdomain );

		echo scb_admin_notice( $msg, $class );
	}


//  ____________UTILITIES____________


	// Generates a form submit button
	function submit_button( $value = '', $action = 'action', $class = "button" ) {
		if ( is_array( $value ) ) {
			extract( wp_parse_args( $value, array(
				'value' => __( 'Save Changes', $this->textdomain ),
				'action' => 'action',
				'class' => 'button',
				'ajax' => true
			) ) );

			if ( ! $ajax )
				$class .= ' no-ajax';
		}
		else {
			if ( empty( $value ) )
				$value = __( 'Save Changes', $this->textdomain );
		}

		$input_args = array(
			'type' => 'submit',
			'name' => $action,
			'value' => $value,
			'extra' => '',
			'desc' => false,
			'wrap' => html( 'p class="submit"', scbForms::TOKEN )
		);

		if ( ! empty( $class ) )
			$input_args['extra'] = compact( 'class' );

		return scbForms::input( $input_args );
	}

	/*
	Mimics scbForms::form_wrap()

	$this->form_wrap( $content );	// generates a form with a default submit button

	$this->form_wrap( $content, false ); // generates a form with no submit button

	// the second argument is sent to submit_button()
	$this->form_wrap( $content, array( 'text' => 'Save changes',
		'name' => 'action',
		'ajax' => true,
	) );
	*/
	function form_wrap( $content, $submit_button = true ) {
		if ( is_array( $submit_button ) ) {
			$content .= $this->submit_button( $submit_button );
		} elseif ( true === $submit_button ) {
			$content .= $this->submit_button();
		} elseif ( false !== strpos( $submit_button, '<input' ) ) {
			$content .= $submit_button;
		} elseif ( false !== $submit_button ) {
			$button_args = array_slice( func_get_args(), 1 );
			$content .= call_user_func_array( array( $this, 'submit_button' ), $button_args );
		}

		return scbForms::form_wrap( $content, $this->nonce );
	}

	// Generates a table wrapped in a form
	function form_table( $rows, $formdata = false ) {
		$output = '';
		foreach ( $rows as $row )
			$output .= $this->table_row( $row, $formdata );

		$output = $this->form_table_wrap( $output );

		return $output;
	}

	// Wraps the given content in a <form><table>
	function form_table_wrap( $content ) {
		$output = $this->table_wrap( $content );
		$output = $this->form_wrap( $output );

		return $output;
	}

	// Generates a form table
	function table( $rows, $formdata = false ) {
		$output = '';
		foreach ( $rows as $row )
			$output .= $this->table_row( $row, $formdata );

		$output = $this->table_wrap( $output );

		return $output;
	}

	// Generates a table row
	function table_row( $args, $formdata = false ) {
		return $this->row_wrap( $args['title'], $this->input( $args, $formdata ) );
	}

	// Wraps the given content in a <table>
	function table_wrap( $content ) {
		return
		html( 'table class="form-table"', $content );
	}

	// Wraps the given content in a <tr><td>
	function row_wrap( $title, $content ) {
		return
		html( 'tr',
			 html( 'th scope="row"', $title )
			.html( 'td', $content ) );
	}

	// Mimic scbForms inheritance
	function __call( $method, $args ) {
		if ( in_array( $method, array( 'input', 'form' ) ) ) {
			if ( empty( $args[1] ) && isset( $this->options ) )
				$args[1] = $this->options->get();

			if ( 'form' == $method )
				$args[2] = $this->nonce;
		}

		return call_user_func_array( array( 'scbForms', $method ), $args );
	}

	// Wraps a string in a <script> tag
	function js_wrap( $string ) {
		return "\n<script type='text/javascript'>\n" . $string . "\n</script>\n";
	}

	// Wraps a string in a <style> tag
	function css_wrap( $string ) {
		return "\n<style type='text/css'>\n" . $string . "\n</style>\n";
	}


//  ____________INTERNAL METHODS____________


	// Registers a page
	function page_init() {
		extract( $this->args );

		if ( ! $toplevel ) {
			$this->pagehook = add_submenu_page( $parent, $page_title, $menu_title, $capability, $page_slug, array( $this, '_page_content_hook' ) );
		} else {
			$func = 'add_' . $toplevel . '_page';
			$this->pagehook = $func( $page_title, $menu_title, $capability, $page_slug, array( $this, '_page_content_hook' ), $icon_url, $position );
		}

		if ( ! $this->pagehook )
			return;

		if ( $ajax_submit ) {
			$this->ajax_response();
			add_action( 'admin_footer', array( $this, 'ajax_submit' ), 20 );
		}

		add_action( 'admin_print_styles-' . $this->pagehook, array( $this, 'page_head' ) );
	}

	function option_init() {
		register_setting( $this->option_name, $this->option_name, array( $this, 'validate' ) );
	}

	private function check_args() {
		if ( empty( $this->args['page_title'] ) )
			trigger_error( 'Page title cannot be empty', E_USER_WARNING );

		$this->args = wp_parse_args( $this->args, array(
			'toplevel' => '',
			'position' => null,
			'icon_url' => '',
			'screen_icon' => '',
			'parent' => 'options-general.php',
			'capability' => 'manage_options',
			'menu_title' => $this->args['page_title'],
			'page_slug' => '',
			'nonce' => '',
			'action_link' => __( 'Settings', $this->textdomain ),
			'ajax_submit' => false,
			'admin_action_priority' => 10,
		) );

		if ( empty( $this->args['page_slug'] ) )
			$this->args['page_slug'] = sanitize_title_with_dashes( $this->args['menu_title'] );

		if ( empty( $this->args['nonce'] ) )
			$this->nonce = $this->args['page_slug'];
	}

	function _contextual_help( $help, $screen ) {
		if ( is_object( $screen ) )
			$screen = $screen->id;

		$actual_help = $this->page_help();

		if ( $screen == $this->pagehook && $actual_help )
			return $actual_help;

		return $help;
	}

	function ajax_response() {
		if ( ! isset( $_POST['_ajax_submit'] ) || $_POST['_ajax_submit'] != $this->pagehook )
			return;

		$this->form_handler();
		die;
	}

	function ajax_submit() {
		global $page_hook;

		if ( $page_hook != $this->pagehook )
			return;
?>
<script type="text/javascript">
jQuery( document ).ready( function( $ ){
	var $spinner = $( new Image() ).attr( 'src', '<?php echo admin_url( "images/wpspin_light.gif" ); ?>' );

	$( ':submit' ).click( function( ev ){
		var $submit = $( this );
		var $form = $submit.parents( 'form' );

		if ( $submit.hasClass( 'no-ajax' ) || $form.attr( 'method' ).toLowerCase() != 'post' )
			return true;

		var $this_spinner = $spinner.clone();

		$submit.before( $this_spinner ).hide();

		var data = $form.serializeArray();
		data.push( {name: $submit.attr( 'name' ), value: $submit.val()} );
		data.push( {name: '_ajax_submit', value: '<?php echo $this->pagehook; ?>'} );

		$.post( location.href, data, function( response ){
			var $prev = $( '.wrap > .updated, .wrap > .error' );
			var $msg = $( response ).hide().insertAfter( $( '.wrap h2' ) );
			if ( $prev.length > 0 )
				$prev.fadeOut( 'slow', function(){ $msg.fadeIn( 'slow' ); } );
			else
				$msg.fadeIn( 'slow' );

			$this_spinner.hide();
			$submit.show();
		} );

		ev.stopPropagation();
		ev.preventDefault();
	} );
} );
</script>
<?php
	}

	function _page_content_hook() {
		$this->form_handler();

		$this->page_header();
		$this->page_content();
		$this->page_footer();
	}

	function _action_link( $links ) {
		$url = add_query_arg( 'page', $this->args['page_slug'], admin_url( $this->args['parent'] ) );

		$links[] = html_link( $url, $this->args['action_link'] );

		return $links;
	}
}

