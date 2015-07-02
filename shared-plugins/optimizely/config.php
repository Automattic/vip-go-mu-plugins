<script type="text/template" id="optimizely_results">
	<div name="<%- id %>" id="exp_<%- id %>" data-exp-id="<%- id %>" class="opt_results" data-exp-title="<%- description %>">
		<div class="header">
			<div class="title"><%- title %></div>
			<div class="results_toolbar">
				<span><?php esc_html_e( 'Goals', 'optimizely' ) ?>: </span>
				<select class="goalSelector" id="goal_<%- id %>">
					<% _.each( goalOptions, function( goalOption ) { %>
					<option value="<%- goalOption.id %>" <%- goalOption.selected %>><%- goalOption.name %></option>
 					<% }); %>
				</select>
				<div title="<?php esc_html_e( 'Start Experiment', 'optimizely' ) ?>" class="<%- statusClass %> button">
					<i class="fa fa-<%- statusClass %> fa-fw"></i>
				</div>
				<div title="<?php esc_html_e( 'Edit on Optimizely', 'optimizely' ) ?>" class="edit button">
					<i class="fa fa-edit fa-fw"></i>
				</div>
				<div title="<?php esc_html_e( 'Full Results', 'optimizely' ) ?>" class="fullresults button">
					<i class="fa fa-line-chart fa=fw"></i>
				</div>
				<div title="<?php esc_html_e( 'Archive Experiment', 'optimizely' ) ?>" class="archive button">
					<i class="fa fa-archive fa=fw"></i>
				</div>
			</div>
		</div>
		<div class="variations">
			<table>
				<tr class="first">
					<th class="first"><?php esc_html_e( 'VARIATION', 'optimizely' ) ?></th>
					<th><?php esc_html_e( 'VISITORS', 'optimizely' ) ?></th>
					<th><?php esc_html_e( 'CONVERSIONS', 'optimizely' ) ?></th>
					<th><?php esc_html_e( 'CONVERSION RATE', 'optimizely' ) ?></th>
					<th><?php esc_html_e( 'IMPROVEMENT', 'optimizely' ) ?></th>
					<th><?php esc_html_e( 'CONFIDENCE', 'optimizely' ) ?></th>
					<th><?php esc_html_e( 'LAUNCH', 'optimizely' ) ?></th>
				</tr>
				<% _.each( variations, function( variation ) { %>
				<tr class="variationrow <%- variation.status %> <%- variation.goalId %>" id="variation_<%- variation.variationId %>" data-var-id="<%- variation.variationId %>">
					<td class="first"><a target="_blank" href="<%- variation.editUrl %>?optimizely_x<%- variation.expID %>=<%- variation.variationId %>"><%- variation.variationName %></a></td>
					<td><%- variation.visitors %></td>
					<td><%- variation.conversions %></td>
					<td><%- variation.conversionRate %></td>
					<td><%- variation.improvement %></td>
					<td><%- variation.confidence %></td>
					<td>
						<% if ( 'baseline' != variation.status ) { %>
						<div class="button launch <%- variation.status %>" title="<?php esc_html_e( 'Launch', 'optimizely' ) ?>"><i class="fa fa-rocket fa-fw"></i></div>
						<% } %>
					</td>
				</tr>
				<% }); %>
			</table>
		</div>
		<div class="footer">
			<div class="progressbar"></div>
		</div>
	</div>
</script>

<div class="wrap">
	<div id="optimizely-tabs">
		<ul class="tabs-header" id="tabs-header">
			<li><a href="#tabs-1"><?php echo esc_html_e( 'Results', 'optimizely' ) ?></a></li>
			<li><a href="#tabs-2"><?php echo esc_html_e( 'Configuration', 'optimizely' ) ?></a></li>
		</ul>
		<div id="tabs-1">
			<h2><?php echo esc_html_e( 'Wordpress Headline Results', 'optimizely' ) ?> <span><?php echo esc_html_e( 'This is a list of all of the experiments that are running headline tests.', 'optimizely' ) ?></span></h2>
			<div id="successMessage"></div>

			<div id="results_list">
				<div id="ready">
					<h2><?php echo esc_html_e( 'Ready to launch!', 'optimizely' ) ?> <span><?php echo esc_html_e( 'These experiments experiments are ready to launch!', 'optimizely' ) ?></span></h2>
				</div>
				<div id="stillwaiting">
					<h2><?php echo esc_html_e( 'Not ready to launch', 'optimizely' ) ?> <span><?php echo esc_html_e( 'These experiments still need a few more visitors before we can declare a winner', 'optimizely' ) ?></span></h2> 
				</div>
				<div class="loading" id="loading"><?php echo esc_html_e( 'Loading Results.....', 'optimizely' ) ?><br><img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) ).'images/ajax-loader.gif' ?>" /></div>
				<div id="noresults">
					<h3><?php echo esc_html_e( 'No results!', 'optimizely' ) ?></h3>
					<p><?php echo esc_html_e( 'A headline experiement must be created in Wordpress before any results will be displayed here. Please create a new post with multiple headlines, publish the post, and start the experiment. Once the experiment is created and running it will display the results here. <strong>Please Note:</strong> Only experiements created through Wordpress will be displayed here. Experiments created directly in Optimizely will not be displayed here.', 'optimizely' ) ?></p>
				</div>
			</div>  
		</div>
		<div id="tabs-2">
			<h2><?php esc_html_e( 'Optimizely Configuration', 'optimizely' ); ?></h2>
			<div class="narrow">
				<form action="" method="post" id="optimizely-conf">
					<?php 
						wp_nonce_field( OPTIMIZELY_NONCE );
						$project_name = get_option( 'optimizely_project_name' );	
					?>
					<h3><?php esc_html_e( 'About Optimizely', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'Simple, fast, and powerful.', 'optimizely' ); ?> <a href="http://www.optimizely.com" target="_blank">Optimizely</a> <?php esc_html_e( 'is a dramatically easier way for you to improve your website through A/B testing. Create an experiment in minutes with absolutely no coding or engineering required. Convert your website visitors into customers and earn more revenue: create an account at', 'optimizely' ) ?> <a href="http://www.optimizely.com" target="_blank">optimizely.com</a> <?php esc_html_e( 'and start A/B testing today!', 'optimizely' ) ?></p>
					<h3><?php esc_html_e( 'Optimizely API tokens', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'Once you create an account, you can find your API Token on your account page at', 'optimizely' ); ?> <a href="https://www.optimizely.com/account">optimizely.com/account</a>.</p>
					<p>
						<label for="token"><strong><?php esc_html_e( 'API Token', 'optimizely' ); ?></strong></label>
						<br />
						<input id="token" name="token" type="text" maxlength="80" value="<?php echo esc_attr( get_option( 'optimizely_token' ) ) ?>" class="code" />
					</p>
					<button id="connect_optimizely" class="button"><?php esc_html_e( 'Connect Optimizely', 'optimizely' ); ?></button>

					<h3><?php esc_html_e( 'Choose a Project', 'optimizely' ); ?></h3>
					<input type="hidden" id="project_name" name="project_name" value="<?php echo esc_attr( $project_name ) ?>" />
					<select id="project_id" name="project_id">
						<?php 
							$project_id = get_option( 'optimizely_project_id' );
							if ( ! empty( $project_id ) ): 
								?>
								<option value="<?php echo esc_attr( $project_id ) ?>" selected><?php echo esc_html( $project_name ) ?></option>
								<?php 
							endif;
						?>
						<option value=""><?php esc_html_e( 'Connect Optimizely to choose a project...', 'optimizely' ); ?></option>
					</select>
					<p><?php esc_html_e( 'Optimizely will add the following project code to your page automatically:', 'optimizely' ); ?></p>
					<h3 id="project_code"><?php echo esc_html( optimizely_generate_script( $project_id ) ); ?></h3>
					<br/>

					<h3><?php esc_html_e( 'Post Types', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'Please choose the post types you would like to conduct A/B testing on', 'optimizely' ); ?></p> 
					<?php
						$args = array(
							'show_ui' => true
						);
						
						$selected_post_types_str = get_option( 'optimizely_post_types', 'post' );
						$selected_post_types = ( ! empty( $selected_post_types_str ) ) ? explode( ',', $selected_post_types_str ) : array();
						$post_types = get_post_types( $args, 'objects' ); 
						foreach( $post_types as $post_type ) {
							if ( 'page' != $post_type->name && 'attachment' != $post_type->name ) {
								if ( in_array( $post_type->name, $selected_post_types ) ) {
									echo '<input type="checkbox" name="optimizely_post_types[]" value="'. esc_attr( $post_type->name ) .'" checked/>&nbsp;' . esc_attr( $post_type->label ) . '</br>';
								} else {
									echo '<input type="checkbox" name="optimizely_post_types[]" value="'. esc_attr( $post_type->name ) .'"/>&nbsp;' . esc_attr( $post_type->label ) . '</br>';
								}
							}
						}
					?>

					<h3><?php esc_html_e( 'Variation Code', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( "Optimizely will use this variation code to change headlines on your site. We've provided code that works with the default theme, but you might want to add or change it to work with your themes and plugins.", 'optimizely' ); ?></p>  

					<textarea class="code" rows="5" name="variation_template" id="variation_template"><?php echo esc_html( get_option( 'optimizely_variation_template', OPTIMIZELY_DEFAULT_VARIATION_TEMPLATE ) ) ?></textarea>

					<p><?php esc_html_e( 'You can use the variables $POST_ID, $OLD_TITLE, and $NEW_TITLE in your code.', 'optimizely' ); ?></p>

					<h3><?php esc_html_e( 'Number of Variations to test', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'Place a number in the text box below. This will be the additional number of variations a user can test per post.', 'optimizely' ); ?></p>  

					<input id="optimizely_num_variations" name="optimizely_num_variations" type="number" maxlength="1" value="<?php echo absint( get_option( 'optimizely_num_variations', OPTIMIZELY_NUM_VARIATIONS ) ) ?>" class="code" />

					<h3><?php esc_html_e( 'Powered Testing', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'By default we use a sample size of 10,316 per variation to be considered powered. This is based on a baseline conversion rate of 3%, a minimum relative change of 20%, 80% statistical power, 95% statistical significance and 1-tailed test. If you need to change this number use the', 'optimizely' ); ?> <a href="https://www.optimizely.com/resources/sample-size-calculator"><?php esc_html_e( 'Sample Size Calculator', 'optimizely' ); ?></a> <?php esc_html_e( 'to adjust to your needs', 'optimizely' ); ?></p>
					<?php esc_html_e( 'Visitors Per Variation', 'optimizely' ); ?>
					<br />
					<input id="powered_number" name="optimizely_visitor_count" type="text" maxlength="80" value="<?php echo absint( get_option( 'optimizely_visitor_count', OPTIMIZELY_DEFAULT_VISITOR_COUNT ) ) ?>" class="code" />

					<p class="submit"><input type="submit" name="submit" value="<?php esc_html_e( 'Submit &raquo;', 'optimizely' ); ?>" class="button-primary" /></p>
				</form>
			</div>
		</div>
	</div>
</div>
