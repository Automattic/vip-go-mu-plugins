<?php

$sranalytics_boolean_settings = array(
	'sranalytics_show_on_tac_pages',
	'sranalytics_show_on_wp_pages',
	'sranalytics_show_on_attachment_pages',
	'sranalytics_show_everywhere',
	'sranalytics_force_http',
	'sranalytics_disable_iframe_loading',
);
$message = '';
if ( !empty( $_POST[ 'sranalytics_submitted' ] ) && current_user_can( 'manage_options' ) ) {
	//validate pid
	check_admin_referer( 'update_sranalytics_options' );
	if ( !empty( $_POST['sranalytics_pid'] ) ) {
		update_option( 'sranalytics_pid', sanitize_text_field( $_POST[ 'sranalytics_pid' ] ) );
		$message = __( 'Settings updated', 'sranalytics' );
	} else {
		$message = __( 'ERROR:	You must enter a value for pid!', 'sranalytics' );
	}

	//Loop through all boolean seetings and coerce string to true or false
	foreach ( $sranalytics_boolean_settings as $setting ) {
		$setting_string = ( !empty( $_POST[ $setting ] ) ) ? $_POST[ $setting ] : '';
		if ( $setting_string === 'true' ) {
			$options_updated = update_option( $setting, 1 );
		} else {
			$options_updated = update_option( $setting, 0 );
		}
		if ( $options_updated ) {
			$message = __( 'Settings updated', 'sranalytics' );
		}
	}
}
if ($message) {
	print '<div id="message" class="updated below-h2">'. esc_html( $message ) . '</div>';
}

// Set the variables
$sranalytics_pid = get_option( 'sranalytics_pid' );
$sranalytics_show_on_tac_pages = get_option( 'sranalytics_show_on_tac_pages' );
$sranalytics_show_everywhere = get_option( 'sranalytics_show_everywhere' );
$sranalytics_show_on_wp_pages = get_option( 'sranalytics_show_on_wp_pages' );
$sranalytics_show_on_attachment_pages = get_option( 'sranalytics_show_on_attachment_pages' );
$sranalytics_force_http = get_option( 'sranalytics_force_http' );
$sranalytics_disable_iframe_loading = get_option( 'sranalytics_disable_iframe_loading' );
?>

<div class='overview'>
	<h2><?php esc_html_e( 'SimpleReach Analytics', 'sranalytics' ); ?></h2>
</div>

<form name="sranalytics_form" method="post" action="<?php echo esc_url( admin_url( 'options-general.php?page=SimpleReach-Analytics' ) ); ?>">

<?php wp_nonce_field( 'update_sranalytics_options' ); ?>
<div id='poststuff' class='wrap'>
<div id='post-body' class='metabox-holder colums-2'>
<div id='post-body-content'>

	<div class='postbox'>
		<h3 class='hndle'><span><?php esc_html_e( 'Publisher ID', 'sranalytics' ); ?></span></h3>
		<div class='inside'>
			<ul>
					<li>
							<div id="sranalytics_controls">
									<input type="hidden" name="sranalytics_submitted" value="1" />
									<label for="sranalytics_submitted"><?php esc_html_e( 'Enter your Publisher ID (PID): ', 'sranalytics' ); ?></label>
									<input type="text" name="sranalytics_pid" value="<?php print esc_attr( $sranalytics_pid ); ?>" style="width:200px;" />
									<?php if ( !empty( $sranalytics_pid ) && isset( $sranalytics_pid ) ) { ?>
											<br />
											<span style="color:red;font-size:10px;">
													* <?php esc_html_e( 'You only need to set this once. Do not change this unless you are absolutely sure you know what you are doing!', 'sranalytics' ); ?>
											</span>
									<?php } ?>
							</div>
					</li>
					<li><input class='button-primary' type="submit" name="Submit" value="<?php esc_attr_e( 'Save', 'sranalytics' ); ?>" /></li>
			</ul>
		</div>
	</div>

	<div class='postbox'>
		<h3 class='hndle'><span><?php esc_html_e( 'iFrame Settings', 'sranalytics' ); ?></span></h3>
		<div class='inside'>
			<ul>
					<li>
							<input type="checkbox" id='sranalytics_disable_iframe_loading' name="sranalytics_disable_iframe_loading" value="true"  <?php checked( $sranalytics_disable_iframe_loading, true ); ?>  />
							<label for='sranalytics_disable_iframe_loading'><?php esc_html_e( 'Disable iFrame loading of the SimpleReach code', 'sranalytics' ); ?> (<span style='color:red;font-size:10px;'><strong>WARNING</strong>: disabling will make your analytics less accurate</span>)</label>
					</li>
					<li><input class='button-primary' type="submit" name="Submit" value="<?php esc_attr_e( 'Save', 'sranalytics' ); ?>" /></li>
			</ul>
		</div>
	</div>

	<div class='postbox'>
		<h3 class='hndle'><span><?php esc_html_e( 'Advanced Tracking Settings', 'sranalytics' ); ?></span></h3>
		<div class='inside'>
			<p><?php esc_html_e( "WordPress posts are tracked by default. If you'd like to track additional parts of your site, please use the settings below.", 'sranalytics' ); ?></p>
			<ul>
					<li>
							<input type="checkbox" id='sranalytics_show_on_wp_pages' name="sranalytics_show_on_wp_pages" value="true" <?php checked( $sranalytics_show_on_wp_pages, true ); ?> />
							<label for='sranalytics_show_on_wp_pages'><?php esc_html_e( "Track pages (these are the pages you create from the 'Pages' link in the left sidebar)", 'sranalytics' ); ?></label>
					</li>

					<li>
							<input type="checkbox" id='sranalytics_show_on_attachment_pages' name="sranalytics_show_on_attachment_pages" value="true" <?php checked( $sranalytics_show_on_attachment_pages, true ); ?>  />
							<label for='sranalytics_show_on_attachment_pages'><?php esc_html_e( "Track attachment pages (these are the pages created in the 'media' link on the left sidebar)", 'sranalytics' ); ?></label>
					</li>

					<li>
							<input type="checkbox" id='sranalytics_show_on_tac_pages' name="sranalytics_show_on_tac_pages" value="true" <?php checked( $sranalytics_show_on_tac_pages, true ); ?>  />
							<label for='sranalytics_show_on_tac_pages'><?php esc_html_e( 'Track author, category, and tag pages', 'sranalytics' ); ?></label>
					</li>

					<li>
							<input type="checkbox" id='sranalytics_show_everywhere' name="sranalytics_show_everywhere" value="true"  <?php checked( $sranalytics_show_everywhere, true ); ?>  />
							<label for='sranalytics_show_everywhere'><?php esc_html_e( 'Track everything, including the home page (includes WordPress, author, category, tag, attachment, and search results pages)', 'sranalytics' ); ?></label>
					</li>

					<li>
							<input type="checkbox" id='sranalytics_force_http' name="sranalytics_force_http" value="true"  <?php checked( $sranalytics_force_http, true ); ?>  />
							<label for='sranalytics_force_http'><?php esc_html_e( 'Send urls as HTTP. If your site uses a combination of both HTTP and HTTPS, enable this option.', 'sranalytics' ); ?></label>
					</li>
					<li><input class='button-primary' type="submit" name="Submit" value="<?php esc_html_e( 'Save', 'sranalytics' ); ?>" /></li>
			</ul>
		</div>
	</div>
</div>

<div id='postbox-container-1' class='postbox-container'>
<div class='meta-box-sortables ui-sortable'>
	<div class='postbox'>
		<h3 class='hndle'><?php esc_html_e( 'Support', 'sranalytics' ); ?></h3>
		<div class='inside'>
			<p>
			<a href='mailto:support@simplereach.com'><?php esc_html_e( 'Questions? Comments? We can be contacted via SimpleReach Support', 'sranalytics' ); ?></a>.
			</p>
		</div>
	</div>
</div>
</div>

</div>
</div>
</form>
