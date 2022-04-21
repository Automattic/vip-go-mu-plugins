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
	<h1 class="wp-heading-inline"><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<span id="wp-parsely_version"><?php echo esc_html( $parsely_version_string ); ?></span>
	<p><em><?php esc_html_e( 'More settings can be enabled via Screen Options (button on the top of the page).', 'wp-parsely' ); ?></em></p>
<?php
if ( is_multisite() && is_main_site() ) {
	?>
		<div class="notice notice-info">
			<p><?php esc_html_e( 'Attention: this is the main site of your Multisite Network.', 'wp-parsely' ); ?></p>
		</div>
	<?php
}
?>
	<form name="parsely" method="post" action="options.php" novalidate>
		<?php
		settings_fields( Parsely::OPTIONS_KEY );
		do_settings_sections( Parsely::OPTIONS_KEY );
		submit_button();
		?>
	</form>
</div>
