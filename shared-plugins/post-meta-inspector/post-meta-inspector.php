<?php
/**
 * Plugin Name: Post Meta Inspector
 * Plugin URI: http://wordpress.org/extend/plugins/post-meta-inspector/
 * Description: Peer inside your post meta. Admins can view post meta for any post from a simple meta box.
 * Author: Daniel Bachhuber, Automattic
 * Version: 1.1.1
 * Author URI: http://automattic.com/
 */

define( 'POST_META_INSPECTOR_VERSION', '1.1.1' );

class Post_Meta_Inspector
{

	private static $instance;

	public $view_cap;

	public static function instance() {

		if ( ! isset( self::$instance ) ) {
			self::$instance = new Post_Meta_Inspector;
			self::setup_actions();
		}
		return self::$instance;
	}

	private function __construct() {
		/** Do nothing **/
	}

	private static function setup_actions() {

		add_action( 'init', array( self::$instance, 'action_init') );
		add_action( 'add_meta_boxes', array( self::$instance, 'action_add_meta_boxes' ) );
	}

	/**
	 * Init i18n files
	 */
	public function action_init() {
		load_plugin_textdomain( 'post-meta-inspector', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add the post meta box to view post meta if the user has permissions to
	 */
	public function action_add_meta_boxes() {

		$this->view_cap = apply_filters( 'pmi_view_cap', 'manage_options' );
		if ( ! current_user_can( $this->view_cap ) || ! apply_filters( 'pmi_show_post_type', '__return_true', get_post_type() ) )
			return;

		add_meta_box( 'post-meta-inspector', __( 'Post Meta Inspector', 'post-meta-inspector' ), array( self::$instance, 'post_meta_inspector' ), get_post_type() );
	}

	public function post_meta_inspector() {
		$toggle_length = apply_filters( 'pmi_toggle_long_value_length', 0 );
		$toggle_length = max( intval($toggle_length), 0);
		$toggle_el = '<a href="javascript:void(0);" class="pmi_toggle">' . __( 'Click to show&hellip;', 'post-meta-inspector' ) . '</a>';
		?>
		<style>
			#post-meta-inspector table {
				text-align: left;
				width: 100%;
			}
			#post-meta-inspector table .key-column {
				display: inline-block;
				width: 20%;
				border-bottom: 1px #e5e5e5 dotted;
				padding: 0.5%;
				margin: 0;
				word-wrap: break-word;
			}
			#post-meta-inspector table .value-column {
				display: inline-block;
				width: 77%;
				border-bottom: 1px #e5e5e5 dotted;
				padding: 0.5%;
				margin: 0;
				
			}
			#post-meta-inspector code {
				word-wrap: break-word;
			}
		</style>

		<?php $custom_fields = get_post_meta( get_the_ID() ); ?>
		<table>
			<thead>
				<tr>
					<th class="key-column"><?php _e( 'Key', 'post-meta-inspector' ); ?></th>
					<th class="value-column"><?php _e( 'Value', 'post-meta-inspector' ); ?></th>
				</tr>
			</thead>
			<tbody>
		<?php foreach( $custom_fields as $key => $values ) :
				if ( apply_filters( 'pmi_ignore_post_meta_key', false, $key ) )
					continue;
		?>
			<?php foreach( $values as $value ) : ?>
			<?php
				$value = apply_filters( 'pmi_post_meta_value', var_export( maybe_unserialize( $value ), true ), $key, $value );
				$toggled = $toggle_length && strlen($value) > $toggle_length;
			?>
			<tr>
				<td class="key-column"><?php echo esc_html( $key ); ?></td>
				<td class="value-column"><?php if( $toggled ) echo $toggle_el; ?><code <?php if( $toggled ) echo ' style="display: none;"'; ?>><?php echo esc_html( $value ); ?></code></td>
			</tr>
			<?php endforeach; ?>
		<?php endforeach; ?>
			</tbody>
		</table>
		<script>
		jQuery(document).ready(function() {
			jQuery('.pmi_toggle').click( function(e){
				jQuery('+ code', this).show();
				jQuery(this).hide();
			});
		});
		</script>
		<?php
	}

}

function Post_Meta_Inspector() {
	return Post_Meta_Inspector::instance();
}
add_action( 'plugins_loaded', 'Post_Meta_Inspector' );
