<?php

/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package    GUM
 * @subpackage Views
 * @author     Phil Derksen <pderksen@gmail.com>, Nick Young <mycorgumeb@gmail.com>, Gumroad <maxwell@gumroad.com>
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap">

	<div id="gum-settings">
		<div id="gum-settings-content">

			<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

			<h3 class="title"><?php esc_html_e( 'Important SSL Requirement', 'gum' ); ?></h3>

			<p>
				<b><em>
					<?php esc_html_e( 'The address of the page hosting the Gumroad overlay or embed should start with https, ' .
						'so you\'ll need to setup an SSL certificate for your site if you haven\'t already.', 'gum' ); ?>
				</b></em>
			</p>

			<p>
				If you need help setting up HTTPS on your Wordpress site here is <a href="http://wordpress.org/plugins/wordpress-https/" target="_blank">a Wordpress plugin</a> that can help.
			</p>

			<p>
				<?php esc_html_e( 'Please refer to the Overlay and Embed documentation for additional help:', 'gum' ); ?>
				<a href="https://gumroad.com/overlay" target="_blank">Overlay Documentation</a>, <a href="https://gumroad.com/embed" target="_blank">Embed Documentation</a>
			</p>
			
			<!-- Add a Gumroad product help -->

			<h3 class="title"><?php esc_html_e( 'Adding a product page', 'gum' ); ?></h3>

			<p>
				<?php esc_html_e( 'Use the shortcode', 'gum' ); ?> <code>[gumroad id="DviQY"]</code> <?php esc_html_e( 'to add a product link that will popup in an overlay', 'gum' ); ?>:
			</p>

			<p>
				<img src="https://s3.amazonaws.com/gumroad/assets/wordpress_docs/overlaydemo.gif">
			</p>

			<p>
				<?php esc_html_e( 'Use the shortcode', 'gum' ); ?> <code>[gumroad id="GAPdj" type="embed"]</code> <?php esc_html_e( 'to add an embedded Gumroad product.', 'gum' ); ?>
			</p>

			<p>
				<img src="https://s3.amazonaws.com/gumroad/assets/wordpress_docs/embeddemo.png">
			</p>

			<h4><?php esc_html_e( 'Available Attributes', 'gum' ); ?></h4>

			<table class="widefat importers" cellspacing="0">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Attribute', 'gum' ); ?></th>
						<th><?php esc_html_e( 'Description', 'gum' ); ?></th>
						<th><?php esc_html_e( 'Options', 'gum' ); ?></th>
						<th><?php esc_html_e( 'Default', 'gum' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>id</td>
						<td><?php esc_html_e( 'The Gumroad product ID', 'gum' ); ?></td>
						<td>Any valid Gumroad product ID, <a href="https://www.youtube.com/watch?v=IZl4lAnai50" target="_blank">What is my Gumroad product ID?</a></td>
						<td>none</td>
					</tr>
					<tr>
						<td>type</td>
						<td><?php esc_html_e( 'The type of product link you want to show.', 'gum' ); ?></td>
						<td>overlay, embed</td>
						<td>overlay</td>
					</tr>
					<tr>
						<td>text</td>
						<td><?php esc_html_e( 'Text that shows on the overlay button (applies to overlay only).', 'gum' ); ?></td>
						<td>Any text</td>
						<td>Buy Now</td>
					</tr>
					<tr>
						<td>wanted</td>
						<td><?php esc_html_e( 'If true, user will be redirected directly to the checkout page (applies to overlay only).', 'gum' ); ?></td>
						<td>true, false</td>
						<td>false</td>
					</tr>
					<tr>
						<td>locale</td>
						<td><?php esc_html_e( 'Auto-set a locale (applies to overlay only).', 'gum' ); ?></td>
						<td>true, false</td>
						<td>false</td>
					</tr>
				</tbody>
			</table>
			
			<h4><?php esc_html_e( 'More examples', 'gum' ); ?></h4>

			<ul class="ul-disc">
				<li><code>[gumroad id="DviQY" text="Purchase Item" wanted="true"]</code></li>
				<p><img src="https://s3.amazonaws.com/gumroad/assets/wordpress_docs/wantedoverlaydemo.gif"></p>
				<li><code>[gumroad id="DviQY" text="Comprar articulo" wanted="true" locale="true"]</code></li>
				<p><img src="https://s3.amazonaws.com/gumroad/assets/wordpress_docs/wantedoverlaydemolocalized.gif"></p>
			</ul>

		</div><!-- #gum-settings-content -->

		<div id="gum-settings-sidebar">
		</div>
	</div>

</div><!-- .wrap -->
