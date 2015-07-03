<?php
/*
Plugin Name: AJAX Comment Loading
Description: Loads comment threads asynchronously. Makes for smaller, faster pages. Originally commissioned by Google.
Version: 1.0
Author: Mark Jaquith
Author URI: http://coveredwebservices.com/
*/

class Google_AJAX_Comment_Loading_Plugin {
	static $instance;

	public function __construct() {
		self::$instance = $this;
		if ( !$this->handle_ajax_crawler() )
//			add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
			add_action( 'init', array( $this, 'plugins_loaded' ) );

	}

	private function handle_ajax_crawler() {
		if ( $_POST )
			return false;

		if ( isset( $_GET['_escaped_fragment_'] ) ) {
			unset( $_GET['_escaped_fragment_'] );
			$_SERVER['REQUEST_URI'] = remove_query_arg( '_escaped_fragment_', $_SERVER['REQUEST_URI'] );
			return true;
		}
		return false;
	}

	public function plugins_loaded() {
		add_filter( 'template_redirect', array( $this, 'template_redirect' ) );
		add_action( 'wp_ajax_nopriv_google-get-comments', array( $this, 'ajax_handler' ) );
		add_action( 'wp_ajax_google-get-comments', array( $this, 'ajax_handler' ) );
	}

	public function template_redirect() {
		if ( is_singular() ) {
			add_action( 'wp_head', array( $this, 'add_js_vars' ) );
			wp_enqueue_script( 'google-ajax-comment-loading', plugin_dir_url( __FILE__ ) . 'js/google-acl.js', array( 'jquery' ), 1 );
			add_filter( 'option_require_name_email', array( $this, 'require_name_email' ) );
		}
	}

	/*
		This filter is fired in the comments_template() function,
		which lacks a suitable way of hooking in early and aborting the comments query
	*/
	public function require_name_email( $value ) {
		if ( !defined( 'DOING_AJAX' ) || !DOING_AJAX ) {
			add_filter( 'query', array( $this, 'comments_query_filter' ) );
			add_filter( 'comments_template', array( $this, 'comments_template' ) );
		}
		return $value;
	}

	/*
		Neuter the comments query, to prevent doing double work. Yes, this is super janky.
		Ergo, the comment about what is neutering it, so someone doesn't lose their mind trying to debug this.
	*/
	public function comments_query_filter( $query ) {
		global $wpdb;
		$pattern = '#^\s*SELECT\s*\*\s*FROM\s*' . preg_quote( $wpdb->comments, '#' ) .'\s*WHERE\s*comment_post_ID\s*=\s*([0-9]+)\s*#i';
		if ( preg_match( $pattern, $query ) ) {
			// Neuter the query, while leaving a clue as to what happened
			$query = preg_replace( $pattern, 'SELECT * FROM ' . $wpdb->comments . ' WHERE \'neutered\' = \'by AJAX Comment Loading Plugin\' AND comment_post_ID = $1 ', $query );
			// And now, we self-remove, to make this a run-once filter
			remove_filter( 'query', array( $this, 'comments_query_filter' ) );
		}
		return $query;
	}

	public function comments_template( $template ) {
		return plugin_dir_path( __FILE__ ) . 'templates/comments.php';
	}

	public function add_js_vars() {
		global $wp_query;
?>
<meta name="fragment" content="!" />
<script type="text/javascript">
	ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
	gpid = '<?php echo intval( $wp_query->get_queried_object_id() ); ?>';
	cpage = '<?php echo esc_js( get_query_var( 'cpage' ) ); ?>'
</script>
<?php
	}

	public function ajax_handler() {
		global $post, $id, $cpage;
		query_posts( array( 'p' => $_REQUEST['postid'], 'post_type' => 'any' ) );
		if ( have_posts() ) {
			set_query_var( 'cpage', intval( $_REQUEST['cpage'] ) );
			$_SERVER['REQUEST_URI'] = $_SERVER['HTTP_REFERER'];
			the_post();
			comments_template();
			exit();
		}
		exit( 0 );
	}

}

new Google_AJAX_Comment_Loading_Plugin;
