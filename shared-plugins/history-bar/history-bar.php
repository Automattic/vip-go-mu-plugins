<?php
/*
Plugin Name: History Bar
Description: Keeps track of recently viewed posts and lists them in the help tab for easy access.
Author: Imran Nathani
Version : 1.0
*/
class history_help {
	public $tabs = array(
		// The assoc key represents the ID
		// It is NOT allowed to contain spaces
		 'this_history' => array(
		 	 'title'   => 'History',
			 'content' => '<span id="draw_history">History data not available</span>'
		 )
	);
	public $history_hooks = array( "post.php", "edit.php", "post-new.php" );
	public function __construct() {
		add_action( "wp_ajax_user_cookie_history",  array( $this, "user_cookie_history_callback" ) );
		if(! in_array( $GLOBALS['pagenow'], $this->history_hooks ) )
			return;
		add_action( "admin_enqueue_scripts",  array( $this, "mec_user_cookie_history_enqueue" )  );
		add_action( "load-{$GLOBALS['pagenow']}", array( $this, "add_tabs" ), 20 );
		if( $GLOBALS['pagenow'] == "post.php" || $GLOBALS['pagenow'] == "post-new.php" ) {
			add_action( "admin_print_footer_scripts",  array( $this, "mec_user_history_js_print" ) );
		}
	}

	public function add_tabs() {
		foreach ( $this->tabs as $id => $data ) {
			get_current_screen()->add_help_tab( array(
				'id'       => $id,
				'title'    => __( $data['title'], 'some_textdomain' ),
				'content'  => '',
				'callback' => array( $this, 'prepare' )
			) );
		}
	}

	public function prepare( $screen, $tab ) {
			printf(
			 '<p>%s</p>',
			 __(
				$tab['callback'][0]->tabs[ $tab['id'] ]['content'],
				'dmb_textdomain'
			 )
		);
	}

	public function mec_user_cookie_history_enqueue( $hook ) {
		if( in_array( $hook , $this->history_hooks ) ) {
			wp_enqueue_script('history_bar-js', plugins_url( 'history-bar.js', __FILE__ ));
			wp_localize_script('history_bar-js', 'history_bar_vars', array(
				'image_src' => plugins_url( 'history-bar.gif', __FILE__ ),
				'nonce' => wp_create_nonce( "history_nonce" )
			));
			wp_enqueue_style('history_bar-css', plugins_url( 'history-bar.css', __FILE__ ));
		}
	}
	public function user_cookie_history_callback( ) {
		if( !isset( $_GET["id_list"] ) || !isset( $_GET["history_nonce"] ) ) {
			die();
		}
		if( ! wp_verify_nonce( $_GET["history_nonce"], "history_nonce" ) ) {
			die();
		}
		if( isset( $_GET["id_list"] ) ){
			$id_collection = trim( $_GET["id_list"], ' |' );
			if( strstr( $id_collection, "|" ) ) {
				$id_collection = str_replace( "||", "|", $id_collection );
				$id_collection = explode( "|", $id_collection );
			}
			if( is_array( $id_collection ) ) {
					$id_collection = array_reverse( $id_collection );
					$id_collection = array_map( 'intval', $id_collection );
			} else {
				$id_collection = array( intval( $id_collection ) );
			}
		}
		$args = array( "post__in" => $id_collection, "post_status" => "any" );
		if( isset( $_GET["post_type"] ) ) {
			$args["post_type"] = $_GET["post_type"];
		} else {
			$args["post_type"] = "post";
		}
		$qry = new WP_Query( $args );
		if( $qry->have_posts() ) {
			$post_collection = array();
			foreach( $id_collection as $this_id ) {
				foreach( $qry->posts as $this_post ) {
					if( $this_post->ID == $this_id ) {
						array_push( $post_collection, $this_post );
					}
				}
			}
			if( ! empty( $post_collection ) ) {
				$qry->posts = $post_collection;
			}
			$post_type_obj = get_post_type_object( $args["post_type"] );
			echo "<h3> " . $post_type_obj->labels->name . " history</h3>";
			echo "<ul>";
			while( $qry->have_posts() ) {
				$qry->the_post();
				$post_id = get_the_ID();
				if( ! current_user_can( 'edit_post', $post_id ) )
					continue;
				$post_edit_link = get_edit_post_link( $post_id );
				echo "<li>";
				if( has_post_thumbnail() ){
					echo "<a href='". esc_url( $post_edit_link ) . "' >";
					the_post_thumbnail( array(40,40) , array( 'class' => 'history_post_thumbnail' ) );
					echo "</a>";
				} else {
					echo "<div class='block'>&nbsp;</div>";
				}
				echo "<strong><a href='";
				echo esc_url( $post_edit_link );
				echo "' >";
				the_title();
				echo "</a> - <small>". ucfirst( get_post_status( $post_id ) ) ."</small></strong></li>";
			}
			echo "</ul>";
		}
		die();
	}
	public function mec_user_history_js_print( ) {
		if( isset( $_GET["post"] ) ) {
		?>
			<script language="javascript" type="text/javascript" defer="defer"> AddPost_ID( '<?php echo esc_js( $_GET["post"] ); ?>' ); </script>
		<?php
		}
	}
}
global $history_bar;
$history_bar = new history_help();

?>
