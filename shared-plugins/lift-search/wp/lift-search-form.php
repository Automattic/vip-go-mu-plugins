<?php
require_once __DIR__ . '/form-controls.php';

// Make sure class name doesn't exist
if ( !class_exists( 'Lift_Search_Form' ) ) {

	/**
	 * Lift_Search_Form is a class for building the fields and html used in the Lift Search plugin.
	 * This class uses the singleton design pattern, and shouldn't be called more than once on a document.
	 *
	 * There are three filters within the class that can be used to modify the output:
	 *
	 * 'lift_filters_default_fields' can be used to remove default search fields on the form.
	 *
	 * 'lift_filters_form_field_objects' can be used to add or remove Voce Search Field objects.
	 *
	 * 'lift_search_form' can be used to modify the form html output
	 */
	class Lift_Search_Form {

		private static $instances;

		/**
		 * Returns an instance of the search form based on the given WP_Query instance
		 * @global WP_Query $wp_query
		 * @param WP_Query $a_wp_query
		 * @return Lift_Search_Form
		 */
		public static function GetInstance( $a_wp_query = null ) {
			global $wp_query;
			if ( is_null( $a_wp_query ) ) {
				$a_wp_query = $wp_query;
			}

			$query_id = spl_object_hash( $a_wp_query );
			if ( !isset( self::$instances ) ) {
				self::$instances = array( );
			}

			if ( !isset( self::$instances[$query_id] ) ) {
				self::$instances[$query_id] = new Lift_Search_Form( $a_wp_query );
			}
			return self::$instances[$query_id];
		}

		private $fields = array( );

		/**
		 * WP_Query instance reference for search
		 * @var Lift_WP_Query
		 */
		public $lift_query;

		/**
		 * Lift_Search_Form constructor.
		 */
		private function __construct( $wp_query ) {
			$this->lift_query = Lift_WP_Query::GetInstance( $wp_query );
			$this->fields = apply_filters( 'lift_form_filters', array( ), $this );
		}

		/**
		 * Returns the base url for the search.
		 *
		 * @todo allow the instance to be detached from the global site search
		 * @return string
		 */
		public function getSearchBaseURL() {
			return user_trailingslashit( site_url() );
		}

		/**
		 * Returns the current state of the search form.
		 *
		 * @todo allow instances to be detached from the global request vars
		 * @return array
		 */
		public function getStateVars() {
			return array_merge( $_GET, $_POST );
		}

		/**
		 * Builds the sort by dropdown/select field.
		 */
		public function add_sort_field() {
			if ( !$selected = $this->lift_query->wp_query->get( 'orderby' ) ) {
				$selected = 'relevancy';
			}
			$options = array(
				'label' => ($selected) ? ucwords( $selected ) : 'Sort By',
				'value' => array(
					'Date' => 'date',
					'Relevancy' => 'relevancy'
				),
				'selected' => $selected,
			);
			$this->add_field( 'orderby', 'select', $options );
		}

		/**
		 * Get custom query var from the wp_query
		 * @param string $var query variable
		 * @return array|boolean query variable if it exists, else false
		 */
		public function get_query_var( $var ) {
			return ( $val = get_query_var( $var ) ) ? $val : false;
		}

		/**
		 * Builds the html form using all fields in $this->fields.
		 * @return string search form
		 */
		public function form() {
			$search_term = (is_search()) ? get_search_query( false ) : "";
			$html = '<form role="search" class="lift-search no-js" id="searchform" action="' . esc_url( $this->getSearchBaseURL() ) . '"><div>';
			$html .= sprintf( "<input type='text' name='s' id='s' value='%s' />", esc_attr( $search_term ) );
			$html .= ' <input type="submit" id="searchsubmit" value="' . esc_attr__( 'Search' ) . '" />';
			$html .= '<fieldset class="lift-search-form-filters"><ul>';
			if ( count( $this->getStateVars() ) > 1 ) {
				$html .= sprintf( '<li class="reset"><a href="%s">Reset</a></li>', esc_url( add_query_arg( array( 's' => $search_term ), $this->getSearchBaseURL() ) ) );
			}

			$html .= $this->form_filters();

			$html .= "</ul></fieldset>";
			$html .= "</div></form>";
			return apply_filters( 'lift_search_form', $html );
		}

		public function loop() {
			$path = dirname( __DIR__ ) . '/templates/lift-loop.php';
			$path = apply_filters( 'lift_search_get_template_loop', $path );
			include_once $path;
		}

		/**
		 * Renders the filters
		 * @return string, The HTML output for the rendered filters
		 */
		public function form_filters() {
			$html = '';
			$args = array( 'before_field' => '<li>', 'after_field' => '</li>' );
			foreach ( $this->fields as $field ) {
				$html .= apply_filters( 'lift_form_field_' . $field, '', $this, $args );
			}
			return apply_filters( 'lift_form_fields_html', $html, $this,  $args );
		}

	}

}

/**
 * Template Tags
 */
if ( !function_exists( 'lift_search_form' ) ) {

	function lift_search_form( $a_wp_query = null ) {
		echo Lift_Search_Form::GetInstance( $a_wp_query )->form();
	}

}

if ( !function_exists( 'lift_search_filters' ) ) {

	function lift_search_filters( $a_wp_query = null ) {
		echo Lift_Search_Form::GetInstance( $a_wp_query )->form_filters();
	}

}

if ( !function_exists( 'lift_loop' ) ) {

	function lift_loop() {
		echo Lift_Search_Form::GetInstance()->loop();
	}

}

/**
 * Embed the Lift Search form in a sidebar
 * @class Lift_Form_Widget
 */
class Lift_Form_Widget extends WP_Widget {

	/**
	 * @constructor
	 */
	public function __construct() {
		parent::__construct(
			'lift_form_widget', "Lift Search Form", array( 'description' => "Add a Lift search form" )
		);
	}

	/**
	 * Output the widget
	 *
	 * @method widget
	 */
	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );

		echo $before_widget;
		if ( $title )
			echo $before_title . esc_html( $title ) . $after_title;

		if ( class_exists( 'Lift_Search_Form' ) ) {
			echo Lift_Search_Form::GetInstance()->form();
		}
		echo $after_widget;
	}

	function form( $instance ) {
		$instance = wp_parse_args( ( array ) $instance, array( 'title' => '' ) );
		$title = $instance['title'];
		?>
		<p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
		<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args( ( array ) $new_instance, array( 'title' => '' ) );
		$instance['title'] = strip_tags( $new_instance['title'] );
		return $instance;
	}

}

add_action( 'widgets_init', function() {
		register_widget( 'Lift_Form_Widget' );
	} );

