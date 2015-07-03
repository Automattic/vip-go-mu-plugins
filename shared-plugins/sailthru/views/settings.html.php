<?php
					/* Check to see if everything is set up correctly */
					$verify_setup = sailthru_verify_setup();
					?>

					<?php if ( $verify_setup['error'] ): ?>
					  <?php if ( $verify_setup['errormessage'] == 'template not configured' ):?>
					  <div class="error settings-error">
					    <p>The template you have selected is not configured correctly. Please check the <a href="http://docs.sailthru.com/developers/client-libraries/wordpress-plugin">documentation<a/> for instructions.</p>
					  </div>
					   <?php elseif ( $verify_setup['errormessage'] == 'select a template' ): ?>
					   <div class="error settings-error">
					    <p><a href="?page=settings_configuration_page#sailthru_setup_email_template">Select a Sailthru template</a> to use for all WordPress emails.</p>
					  </div>
					  <?php else: ?>
					  <div class="error settings-error">
					    <p>Sailthru is not correctly configured, please check your API key and template settings.</p>
					  </div>
					  <?php endif; ?>
					<?php endif; ?>
					<div id="dashboard-widgets-wrap">

						<div id="sailthru-template-choices" class="metabox-holder columns-2">

							<div class="postbox-container">
								<div class="meta-box-sortables">
									<div id="sailthru-choose-template" class="postbox">


										<div class="inside">
											<?php
												settings_fields( 'sailthru_setup_options' );
												do_settings_sections( 'sailthru_setup_options' );

											?>
										</div>

									</div>
								</div>
							</div>


							<?php /*
							<div class="postbox-container last">
								<div  class="meta-box-sortables">
									<div id="sailthru-choose-tags" class="postbox">

										<div class="inside">

										</div>

									</div>
								</div>
							</div>
							*/ ?>



						</div>

						<div class="clear"></div>
					</div>
