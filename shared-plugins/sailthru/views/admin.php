 		<!-- Create a header in the default WordPress 'wrap' container -->
		<div class="wrap" id="sailthru-admin">

			<div id="icon-sailthru" class="icon32"></div>
			<h2><?php _e( 'Sailthru for WordPress', 'sailthru-for-wordpress' ); ?></h2>
			<?php


				if ( isset( $_GET[ 'page' ] ) ) {
					$active_tab = $_GET[ 'page' ];
				} else if ( $active_tab == 'concierge_configuration_page' ) {
					$active_tab = 'concierge_configuration_page';
				} else if ( $active_tab == 'scout_configuration_page' ) {
					$active_tab = 'scout_configuration_page';
				} else if ( $active_tab == 'settings_configuration_page') {
					$active_tab = 'settings_configuration_page';
				}
				else if ( $active_tab == 'customforms_configuration_page') {
					$active_tab = 'customforms_configuration_page';
				}
				else {
					$active_tab = 'customforms_configuration_page';
				} // end if/else



				// display errors from form submissions at the top
				settings_errors();

				// Sailthru setup options.
				$sailthru = get_option( 'sailthru_setup_options' );

				// Setup
				$setup = get_option( 'sailthru_setup_options' );


				// we have an api key, secret, and sitewide template
				if ( ! empty ( $sailthru['sailthru_api_key'] )
						&& ! empty( $sailthru['sailthru_api_secret'] ) ){


					// sitewide template is picked
					if ( ! empty( $setup['sailthru_setup_email_template'] ) )	{

						/*
						 *
						 * This is pretty important.
						 * If we're done setting up the user. Set this flag so
						 * we can start injecting our js to the public side.
						 *
						 * This also indicates that sitewide options have
						 * been chosen. So that means it's ok to start
						 * overriding WP email.
						 *
						 */
						if ( false == get_option( 'sailthru_setup_complete' ) ) {
							add_option( 'sailthru_setup_complete', 1 );
						} // end if
 						?>

						<h2 class="nav-tab-wrapper">
							<a href="?page=sailthru_configuration_page" class="nav-tab <?php echo $active_tab == 'sailthru_configuration_page' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Welcome', 'sailthru-for-wordpress' ); ?></a>
							<a href="?page=settings_configuration_page" class="nav-tab <?php echo $active_tab == 'settings_configuration_page' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Settings', 'sailthru-for-wordpress' ); ?></a>
							<a href="?page=concierge_configuration_page" class="nav-tab <?php echo $active_tab == 'concierge_configuration_page' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Concierge', 'sailthru-for-wordpress' ); ?></a>
							<a href="?page=scout_configuration_page" class="nav-tab <?php echo $active_tab == 'scout_configuration_page' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Scout', 'sailthru-for-wordpress' ); ?></a>
							<a href="?page=custom_fields_configuration_page" class="nav-tab <?php echo $active_tab == 'custom_fields_configuration_page' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Subscribe Widget Fields', 'sailthru-for-wordpress' ); ?></a>

						</h2>

						<form method="post" action="options.php">
							<?php
								if ( $active_tab == 'sailthru_configuration_page' ) {

									require( SAILTHRU_PLUGIN_PATH . 'views/welcome.html.php' );

								// general settings
								}elseif ( $active_tab == 'settings_configuration_page' ) {

									require( SAILTHRU_PLUGIN_PATH . '/views/settings.html.php' );

								// concierge settings
								} elseif ( $active_tab == 'concierge_configuration_page' ) {

									settings_fields( 'sailthru_concierge_options' );
									do_settings_sections( 'sailthru_concierge_options' );

								// email scout settings
								} elseif ( $active_tab == 'scout_configuration_page') {

									settings_fields( 'sailthru_scout_options' );
									do_settings_sections( 'sailthru_scout_options' );

								// show custom forms page
								} elseif ( $active_tab == 'custom_fields_configuration_page') {
										settings_fields( 'sailthru_forms_options' );
										do_settings_sections( 'sailthru_forms_options' );
										echo '</div>'; // ends the half column begun in delete_field()
								// show welcome page
								}
								else {

									require( SAILTHRU_PLUGIN_PATH . 'views/welcome.html.php' );

								} // end if/else

								echo '<div style="clear:both;">';
								submit_button();
								echo '</div>';


							?>
						</form>

					<?php } else { /* if no sitewide template is chosen */ ?>


						<h2 class="nav-tab-wrapper">
							<a href="?page=sailthru_configuration_page" class="nav-tab <?php echo $active_tab == 'sailthru_configuration_page' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Welcome', 'sailthru-for-wordpress' ); ?></a>
							<a href="?page=settings_configuration_page" class="nav-tab <?php echo $active_tab == 'settings_configuration_page' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Settings', 'sailthru-for-wordpress' ); ?></a>
						</h2>

						<form method="post" action="options.php">
							<?php
								if ( $active_tab == 'sailthru_configuration_page' ) {

									require( SAILTHRU_PLUGIN_PATH . 'views/welcome.html.php' );

								// site wide settings
								}elseif ( $active_tab == 'settings_configuration_page' ) {

									require( SAILTHRU_PLUGIN_PATH . '/views/settings.html.php' );

								// scout settings
								} else {

									require( SAILTHRU_PLUGIN_PATH . 'views/welcome.html.php' );

								} // end if/else

								submit_button();

							?>
						</form>


					<?php } /* end if no sitewide template is chosen */ ?>

				<?php } else { /* if no api key and secret */  ?>


					<div id="sailthru-welcome-panel" class="welcome-panel">
						<div class="welcome-panel-content">
						<h3><img src="<?php echo SAILTHRU_PLUGIN_URL ?>/img/sailthru-logo.png" /> &nbsp;Sailthru Configuration</h3>
						<p class="about-description">Before we get started, let's make sure you've got everything set up.</p>
							<div class="welcome-panel-column-container">
								<div class="welcome-panel-column">
									<h4>Get Started</h4>
									<p>Your API key is available in your <a href="https://my.sailthru.com/" target="_blank">Sailthru account settings</a>.</p>
									<a class="button button-primary button-hero" href="#" id="sailthru-add-api-key">Add Your Sailthru API &amp; Key</a>
									<p>&nbsp;</p>
									<form method="post" action="options.php" id="sailthru-add-api-key-form">
										<?php

											settings_fields( 'sailthru_setup_options' );
											do_settings_sections( 'sailthru_setup_options' );
											submit_button();

										?>
									</form>
								</div>
								<div class="welcome-panel-column welcome-panel-last">
									<h4>Next Steps</h4>
									<ul>
										<li>Once you've added your key, you'll need to select a default email template to use.</li>
										<li>For more information, read our <a href="http://getstarted.sailthru.com/developers/client-libraries/wordpress-plugin">WordPress plugin documentation.</a></li>
									</ul>
								</div>
							</div>
						</div>
					</div><!-- /.welcome-panel -->

				<?php } /* end if no api key */ ?>

		</div><!-- /.wrap -->
