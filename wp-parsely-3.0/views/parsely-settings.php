<?php
/**
 * Settings file
 *
 * Create the settings page for parse.ly
 *
 * @package      Parsely\wp-parsely
 * @author       Parse.ly
 * @copyright    2012 Parse.ly
 * @license      GPL-2.0-or-later
 */

declare(strict_types=1);

namespace Parsely;

/* translators: %s: Plugin version */
$parsely_version_string = sprintf( __( 'Version %s', 'wp-parsely' ), Parsely::VERSION );
?>
<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1> <span id="wp-parsely_version"><?php echo esc_html( $parsely_version_string ); ?></span>
	<form name="parsely" method="post" action="options.php">
		<?php settings_fields( Parsely::OPTIONS_KEY ); ?>
		<?php do_settings_sections( Parsely::OPTIONS_KEY ); ?>
		<p class="submit">
			<input name="submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes', 'wp-parsely' ); ?>"/>
		</p>
	</form>
</div>
