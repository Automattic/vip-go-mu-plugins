<?php
/**
 * Post Revision Workflow class
 * Adds all of the functions and variables to introduce the post revision workflow features to WordPress
 * @version 0.2a
 */
if( !class_exists( 'post_revision_workflow' ) ) {
	class post_revision_workflow {
		var $reviewers = array();
		private $done = false;
		var $text_domain = 'post_revision_workflow';
		
		function __construct() {
			if( !is_admin() )
				return;
			
			add_action( 'post_submitbox_misc_actions', array( &$this, 'add_to_pub_mb' )	 );
			add_action( 'do_meta_boxes',    array( &$this, 'do_meta_boxes'		), 20, 2 );
			add_action( 'save_post',        array( &$this, 'save_post'			), 1, 2 );
			add_action( 'plugins_loaded',	array( &$this, 'get_reviewers'		), 10, 0 );
			add_action( 'admin_init',		array( &$this, 'setup_settings'		), 10, 0 );
			add_action( 'after_setup_theme', 	array( &$this, 'init_scripts'	) 	 	 );
			add_action( 'init', 			array( &$this, 'init'				)		 );
		}
		
		/**
		 * Perform any normal init operations
		 */
		function init() {
			load_plugin_textdomain( $this->text_domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
		
		/**
		 * Locate, register and enqueue the JavaScript needed for the plugin
		 */
		function init_scripts() {
			$curpath = str_replace( basename( __FILE__ ), '', realpath( __FILE__ ) );
			if( file_exists( $curpath . '/post-revision-workflow.admin.js' ) )
				$script_loc = plugins_url( '/post-revision-workflow.admin.js', __FILE__ );
			elseif( file_exists( $curpath . '/post-revision-workflow/post-revision-workflow.admin.js' ) )
				$script_loc = plugins_url( '/post-revision-workflow/post-revision-workflow.admin.js', __FILE__ );
			if( !empty( $script_loc ) ) {
				wp_register_script( 'post-revision-workflow', $script_loc, array( 'post' ), '0.2a', true );
				wp_enqueue_script( 'post-revision-workflow' );
				wp_localize_script( 'post-revision-workflow', 'post_revision_workflow', array(
					'no_notifications'	=> __( 'No notifications', $this->text_domain ),
					'draft_notify' 		=> __( 'Draft &amp; notify', $this->text_domain ),
					'publish_notify'	=> __( 'Publish &amp; notify', $this->text_domain ),
					'draft_only'		=> __( 'Draft - no notifications', $this->text_domain )
				) );
			/*} else {
				wp_die( 'The post-revision-workflow script could not be located in ' . $curpath . '/post-revision-workflow.admin.js' . ' or ' . $curpath . '/post-revision-workflow/post-revision-workflow.admin.js' );*/
			}
		}
		
		/**
		 * Register and configure the settings for the plugin
		 */
		function setup_settings() {
			$page = is_network_admin() ? 'settings.php' : 'writing';
			$section = 'post_revision_workflow';
			
			if( is_network_admin() ) {
				add_action( 'wpmu_options', array( &$this, 'ms_settings_section' ) );
				add_action( 'update_wpmu_options', array( &$this, 'update_ms_settings' ) );
			} else {
				register_setting( $page, 'dpn_reviewers', array( &$this, 'sanitize_settings' ) );
				add_settings_section( $section, __( 'Post Revision Workflow', $this->text_domain ), array( &$this, 'settings_section' ), $page );
				add_settings_field( 'dpn_reviewers', __( 'Default email address for post revision workflow notification:', $this->text_domain ), array( &$this, 'settings_field' ), $page, $section, array( 'label_for' => 'dpn_reviewers' ) );
			}
		}
		
		/**
		 * Validate, sanitize and return the settings options
		 * Splits the input string at semi-colons and returns an array of valid email addresses
		 * @uses is_email()
		 */
		function sanitize_settings( $input ) {
			if( empty( $input ) )
				return false;
			
			if( !is_array( $input ) )
				/**
				 * For some reason, the sanitize callback runs twice, so we don't want to 
				 * 		explode something that's already an array 
				 */
				$input = explode( ';', $input );
			
			/* Split the list and trim whitespace around each item */
			$input = array_map( 'trim', $input );
			
			/* Turn it into an array if it's not already */
			if( !is_array( $input ) )
				$input = array( $input );
			
			/* Make sure all of the addresses are valid emails */
			$input = array_filter( $input, 'is_email' );
			
			if( empty( $input ) )
				return false;
			
			return $input;
		}
		
		/**
		 * Output any HTML that needs to go at the top of the new settings section
		 */
		function settings_section() {
		}
		
		/**
		 * Output the HTML for the Multisite network settings page
		 */
		function ms_settings_section() {
?>
<h3><?php _e( 'Post Revision Workflow', $this->text_domain ) ?></h3>
<table class="form-table">
	<tbody>
    	<tr valign="top">
        	<th scope="row">
            	<label for="dpn_reviewers"><?php _e( 'Default email address for post revision workflow notification:', $this->text_domain ) ?></label>
            </th>
            <td>
            	<?php $this->settings_field() ?>
            </td>
        </tr>
    </tbody>
</table>
<?php
		}
		
		/**
		 * Sanitize and save settings in multisite network options
		 */
		function update_ms_settings() {
			/*print( "\n<pre><code>\n" );*/
			
			if( !array_key_exists( 'dpn_reviewers', $_POST ) )
				return false;
			$r = $this->sanitize_settings( $_POST['dpn_reviewers'] );
			
			/*var_dump( $r );
			die( "\n</code></pre>\n" );*/
			
			if( false === $r )
				return delete_site_option( 'dpn_reviewers' );
			else
				return update_site_option( 'dpn_reviewers', $r );
		}
		
		/**
		 * Output the HTML of the input field
		 */
		function settings_field( $args=array() ) {
			$id = array_key_exists( 'label_for', $args ) && !empty( $args['label_for'] ) ? $args['label_for'] : 'dpn_reviewers';
?>
	<input class="widefat" type="text" name="<?php echo $id ?>" id="<?php echo $id ?>" value="<?php echo esc_attr( implode( ';', $this->get_reviewers(false) ) ); ?>"/>
<?php
			if( is_network_admin() ) {
?>
	<br/><p class="note"><?php _e( 'Any email addresses entered here can be overridden by setting the same option on an individual site, or by entering email address(es) into the input field for a specific post or page.', $this->text_domain ) ?></p>
<?php
			} else {
?>
	<br/><p class="note"><?php _e( 'Any email addresses entered here can be overridden by entering email address(es) into the input field for a specific post or page.', $this->text_domain ) ?></p>
<?php
			}
		}
		
		/**
		 * Retrieve the email addresses of this site's reviewers
		 * @param bool $fallback=true whether to retrieve the fallback values or not
		 */
		function get_reviewers($fallback=true) {
			if( is_multisite() && $fallback ) {
				if( '' == ( $r = get_option( 'dpn_reviewers', '' ) ) )
					if( '' == ( $r = get_site_option( 'dpn_reviewers', '' ) ) )
						if( function_exists( 'get_mnetwork_option' ) )
							$r = get_mnetwork_option( 'dpn_reviewers', '' );
			} elseif( !$fallback && is_network_admin() ) {
				$r = get_site_option( 'dpn_reviewers', array() );
			} else {
				$r = get_option( 'dpn_reviewers', array() );
			}
			
			if( !$fallback )
				return empty( $r ) ? array() : $r;
			
			$this->reviewers = empty( $r ) ? get_bloginfo( 'admin_email' ) : maybe_unserialize( $r );
			return $this->reviewers;
		}
		
		/**
		 * Instantiates the function that builds the meta box for our admin item
		 * @deprecated - used when a separate meta box was used for this information
		 */
		function do_meta_boxes( $page, $context ) {
			return;
			if ( ( 'page' === $page || 'post' === $page ) && 'side' === $context )
				add_meta_box( 'draft-publish-notify', __( 'Revision Workflow', $this->text_domain ), array( &$this, 'meta_box' ), $page, 'side', 'high' );
		}
		
		/**
		 * Builds the meta box that allows authors to choose whether or not to publish their changes
		 * @deprecated - used when a separate meta box was used for this information
		 */
		function meta_box() {
			global $post;
			if( $post->post_status != 'publish' )
				return;
			if( $post->post_type != 'post' && $post->post_type != 'page' )
				return;
?>
		<p>
			<?php wp_nonce_field( 'dpn-nonce', '_dpn_nonce', false, true ); ?>
            <input type="radio" name="dpn_notify" id="dpn_notify_<?php echo $post->ID ?>_no" value="0" checked="checked"/> 
            <label for="dpn_notify_<?php echo $post->ID ?>_no"><?php _e( 'Publish these modifications normally', $this->text_domain ) ?></label>
            <br/>
			<input type="radio" name="dpn_notify" id="dpn_notify_<?php echo $post->ID ?>_rev" value="3"/> 
			<label for="dpn_notify_<?php echo $post->ID ?>_draft"><?php _e( 'Save these changes as a revision (do not publish them), but don\'t notify anyone', $this->text_domain ) ?></label>
            <br/>
			<input type="radio" name="dpn_notify" id="dpn_notify_<?php echo $post->ID ?>_draft" value="1"/> 
			<label for="dpn_notify_<?php echo $post->ID ?>_draft"><?php _e( 'Save these changes as a revision (do not publish them) and notify reviewer', $this->text_domain ) ?></label>
            <br/>
			<input type="radio" name="dpn_notify" id="dpn_notify_<?php echo $post->ID ?>_pub" value="2"/> 
			<label for="dpn_notify_<?php echo $post->ID ?>_pub"><?php _e( 'Publish these modifications, but notify a reviewer that changes were made', $this->text_domain ) ?></label>
		</p>
        <p>
            <label for="dpn_notify_address_<?php echo $post->ID ?>"><?php _e( 'Please enter the email address(es) for the reviewer(s) you would like to notify:', $this->text_domain ) ?> </label>
            <input type="text" name="dpn_notify_address" id="dpn_notify_address_<?php echo $post->ID ?>" value=""/>
            <br/><em><?php _e( 'Please separate multiple addresses with commas.', $this->text_domain ) ?></em>
        </p>
<?php
		}
		
		/**
		 * Add the options to the "Publish" meta box
		 */
		function add_to_pub_mb() {
			global $post;
			if( 'publish' != $post->post_status )
				return;
			if( $post->post_type != 'post' && $post->post_type != 'page' )
				return;
?>
		<div class="misc-pub-section misc-pub-section-last">
        	<?php _e( 'Post change reviews:', $this->text_domain ) ?> <strong id="post-modification-notification-status"><?php _e( 'No notifications', $this->text_domain ) ?></strong> <a href="#post-modification-notification" class="edit-post-modification-notification hide-if-no-js"><?php _e( 'Edit', $this->text_domain ) ?></a>
			<div id="post-modification-notification" class="hide-if-js">
                <p>
                    <?php wp_nonce_field( 'dpn-nonce', '_dpn_nonce', false, true ); ?>
                    <input type="radio" name="dpn_notify" id="dpn_notify_<?php echo $post->ID ?>_no" value="0" checked="checked"/> 
                    <label for="dpn_notify_<?php echo $post->ID ?>_no"><?php _e( 'Publish these modifications normally', $this->text_domain ) ?></label>
                    <br/>
                    <input type="radio" name="dpn_notify" id="dpn_notify_<?php echo $post->ID ?>_rev" value="3"/> 
                    <label for="dpn_notify_<?php echo $post->ID ?>_rev"><?php _e( 'Save these changes as a revision (do not publish them), but don\'t notify anyone', $this->text_domain ) ?></label>
                    <br/>
                    <input type="radio" name="dpn_notify" id="dpn_notify_<?php echo $post->ID ?>_draft" value="1"/> 
                    <label for="dpn_notify_<?php echo $post->ID ?>_draft"><?php _e( 'Save these changes as a revision (do not publish them) and notify reviewer', $this->text_domain ) ?></label>
                    <br/>
                    <input type="radio" name="dpn_notify" id="dpn_notify_<?php echo $post->ID ?>_pub" value="2"/> 
                    <label for="dpn_notify_<?php echo $post->ID ?>_pub"><?php _e( 'Publish these modifications, but notify a reviewer that changes were made', $this->text_domain ) ?></label>
                </p>
                <p id="dpn-address-field" class="hide-if-js">
                    <label for="dpn_notify_address_<?php echo $post->ID ?>"><?php _e( 'Please enter the email address(es) for the reviewer(s) you would like to notify:', $this->text_domain ) ?></label> 
                    <input type="text" name="dpn_notify_address" id="dpn_notify_address_<?php echo $post->ID ?>" value=""/>
                    <br/><em><?php _e( 'Please separate multiple addresses with commas.', $this->text_domain ) ?></em>
                </p>
                <p><a href="#post-modification-notification" class="button save-post-modification-notification"><?php _e( 'Ok', $this->text_domain ) ?></a></p>
			</div>
		</div>
<?php
		}
		
		/**
		 * Revert to the previous revision and notify the reviewers if necessary
		 * @param int $post_ID the ID of the post being saved
		 * @param stdClass $post the post object
		 */
		function save_post( $post_ID, $post ) {
			if( $this->done )
				return $post_ID;
			
			if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				return $post_ID;
			if( 'auto-draft' == $post->post_status || 'inherit' == $post->post_status )
				return $post_ID;
			
			$this->done = true;
			
			if( ! isset( $_REQUEST['_dpn_nonce'] ) || !wp_verify_nonce( $_REQUEST['_dpn_nonce'], 'dpn-nonce' ) )
				return $post_ID;
			if( !isset( $_REQUEST['dpn_notify'] ) || 0 == $_REQUEST['dpn_notify'] )
				return $post_ID;
			
			if( 1 == $_REQUEST['dpn_notify'] || 3 == $_REQUEST['dpn_notify'] )
				$dowhat = 'draft';
			else
				$dowhat = 'publish';
			
			if( 1 == $_REQUEST['dpn_notify'] || 2 == $_REQUEST['dpn_notify'] )
				$notify = true;
			else
				$notify = false;
			
			if( isset( $_REQUEST['dpn_notify_address'] ) && strstr( $_REQUEST['dpn_notify_address'], ',' ) )
				$_REQUEST['dpn_notify_address'] = explode( ',', str_replace( ' ', '', $_REQUEST['dpn_notify_address'] ) );
			
			$revs = isset( $_REQUEST['dpn_notify_address'] ) && !empty( $_REQUEST['dpn_notify_address'] ) ? $_REQUEST['dpn_notify_address'] : array();
			if( !is_array( $revs ) )
				$revs = array_map( 'trim', explode( ',', $revs ) );
			$revs = array_filter( $revs, 'is_email' );
			
			$this->reviewers = !empty( $revs ) ? $revs : $this->get_reviewers();
			
			global $wpdb;
			if( 'draft' == $dowhat ) {
				$last_revision = array_pop( wp_get_post_revisions( $post_ID, array( 'posts_per_page' => 1 ) ) );
				if( empty( $last_revision ) || empty( $last_revision->post_content ) )
					return $post_ID;
				$last_revision = $last_revision->ID;
				
				wp_restore_post_revision( $last_revision );
			}
			
			if( $notify )
				$this->notify_reviewer( $post_ID, $post, $dowhat );
			
			return $post_ID;
		}
		
		/**
		 * Notify the appropriate reviewer(s)
		 * @param int $post_ID the ID of the post being saved
		 * @param stdClass $post the post object of the post being saved
		 * @param string $dowhat the action being taken
		 */
		function notify_reviewer( $post_ID, $post, $dowhat='publish' ) {
			global $wpdb, $current_user;
			$last_revision = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_parent=%d AND post_type=%s ORDER BY post_date DESC LIMIT 1", $post_ID, 'revision' ) );
			$post_content = 'draft' == $dowhat ? get_post( $last_revision ) : get_post( $post_ID );
			$last_mod = $post_content->post_date;
			$post_content = $post_content->post_content;
			$revision_compare_link = admin_url( 'revision.php?action=diff&post_type=' . $post->post_type . '&right=' . $post->ID . '&left=' . $last_revision );
			$body = sprintf( __( "New changes have been made to \"%s\" at <%s>. ", $this->text_domain ), $post->post_title, get_permalink( $post->ID ) );
			if( 'draft' == $dowhat ) {
				$body .= __( "The author has requested that you review the new changes and determine whether to remove or approve them. These changes will not appear on the public website until you approve them.\n\n", $this->text_domain );
			} else {
				$body .= __( "The modifications have been published, but the author of the page has requested you be notified of them.\n\n", $this->text_domain );
			}
			$body .= sprintf( __( "The new content of the page is shown below if you would like to review it. You can also review %s the changes at %s. Thank you. \n\n======================================================= \nRevisions made at %s \n======================================================= \n\n%s", $this->text_domain ), ( 'draft' == $dowhat ? __( " and approve/reject ", $this->text_domain ) : '' ), $revision_compare_link, $last_mod, $post_content );
			
			$headers = "From: {$current_user->display_name} <{$current_user->user_email}>\r\n";
			
			wp_mail( $this->reviewers, sprintf( __( '[%s] New modifications to %s' ), get_bloginfo('name'), $post->post_title ), $body, $headers );
		}
	}
}

?>