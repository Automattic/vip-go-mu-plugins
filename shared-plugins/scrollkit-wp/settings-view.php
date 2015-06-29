<?php

	$options = get_option( 'scroll_wp_options' );

	$settings_updated = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'];
	$api_key_error = isset( $_GET['api-key-error'] ) && $_GET['api-key-error'];
?>
<div class="wrap">

	<div class="icon32" id="icon-options-general"><br></div>
	<h2>Scroll Kit</h2>

	<?php if ( $api_key_error && !$settings_updated): ?>
		<div class="error">
			<p>
				There was an error with your API key. <a href="<?php echo esc_url( SCROLL_WP_SK_URL ); ?>/api/wp" target="_blank">Get yours here</a>
			</p>
		</div>
	<?php endif ?>

	<form method="post" action="options.php">
		<?php settings_fields( 'scroll_wp_plugin_options' ) ?>

		<?php //TODO make this more pretty, and with support links ?>


		<table class="form-table">
			<tr>
				<th scope="row">Scroll Kit API Key</th>
				<td>
					<input type="text" size="57" name="scroll_wp_options[scrollkit_api_key]" value="<?php echo esc_attr($options['scrollkit_api_key']); ?>" autocomplete="off" />
					<br>
					<a href="<?php echo esc_url( SCROLL_WP_SK_URL ); ?>/api/wp" target="_blank">Get an api key</a>
				</td>
			</tr>
			<tr>
				<td>
					<p>
						HTML Header
					</p>
					<em>
						Add your own HTML tags to this section and they will be printed
						above the Scroll Kit content on each page. Note that these must
						be tags <a href="http://en.support.wordpress.com/code/#html-tags" target="_blank">allowed by wordpress</a>.
					</em>
				</td>
				<td>
					<textarea rows="10" cols="100"
						id="header-input"
						name="scroll_wp_options[template_header]"
						placeholder='<div class="header"><img src="http://example.com/logo.png" /></div>'><?php
						echo esc_textarea( stripslashes( $options['template_header'] ) );
					?></textarea>
				</td>
			</tr>
			<tr>
				<td>
					<p>
						HTML Footer
					</p>
					<em>
						Add your own HTML tags to this section and they will be printed
						below the Scroll Kit content on each page. Note that these must
						be tags <a href="http://en.support.wordpress.com/code/#html-tags" target="_blank">allowed by wordpress</a>.
					</em>
				</td>
				<td>
					<textarea rows="10" cols="100"
						id="footer-input"
						name="scroll_wp_options[template_footer]"><?php
						echo esc_textarea( stripslashes( $options['template_footer'] ) )
					?></textarea>
				</td>
			</tr>
			<tr>
				<td>
					<p>
						CSS Rules
					</p>
					<em>
						Add CSS to style the header and footer content on
						Scroll Kit pages.
					</em>
				</td>
				<td>
					<textarea rows="10" cols="100"
						id="style-input"
						name="scroll_wp_options[template_style]"
						placeholder=".header { position: fixed; top: 0; }"><?php
						echo esc_textarea( stripslashes($options['template_style'] ) )
					?></textarea>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
</div>
