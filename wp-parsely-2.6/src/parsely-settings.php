<?php
/**
 * Settings file
 *
 * Create the settings page for parse.ly
 *
 * @category   Components
 * @package    WordPress
 * @subpackage Parse.ly
 */

/* translators: %s: Plugin version */
$parsely_version_string = sprintf( __( 'Version %s', 'wp-parsely' ), $this::VERSION );
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1> <span id="wp-parsely_version"><?php echo esc_html( $parsely_version_string ); ?></span>
	<form name="parsely" method="post" action="options.php">
		<?php settings_fields( $this::OPTIONS_KEY ); ?>
		<?php do_settings_sections( $this::OPTIONS_KEY ); ?>
		<p class="submit">
			<input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'wp-parsely' ); ?>"/>
		</p>
	</form>
</div>
