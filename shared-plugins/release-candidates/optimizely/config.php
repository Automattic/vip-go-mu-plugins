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
				<div title="<?php esc_html_e( 'Start/Pause Experiment', 'optimizely' ) ?>" class="<%- statusClass %> button">
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
					<th><div title="<?php esc_html_e( 'The total number of visitors for this variation', 'optimizely' ) ?>"><?php esc_html_e( 'VISITORS', 'optimizely' ) ?><i class="fa fa-question-circle fa-fw"></i></div></th>
					<th><div title="<?php esc_html_e( 'The total number of visitors that converted on the goal', 'optimizely' ) ?>"><?php esc_html_e( 'CONVERSIONS', 'optimizely' ) ?><i class="fa fa-question-circle fa-fw"></i></div></th>
					<th><div title="<?php esc_html_e( 'The percentage of unique visitors who saw this variation and triggered this goal', 'optimizely' ) ?>"><?php esc_html_e( 'RATE', 'optimizely' ) ?><i class="fa fa-question-circle fa-fw"></i></div></th>
					<th><div title="<?php esc_html_e( 'The relative improvement in Conversion Rate for this variation over the baseline', 'optimizely' ) ?>"><?php esc_html_e( 'IMPROVEMENT', 'optimizely' ) ?><i class="fa fa-question-circle fa-fw"></i></div></th>
					<th><div title="<?php esc_html_e( 'The confidence percentage that the Optimizely stats engine believes the variation is a winner or loser', 'optimizely' ) ?>"><?php esc_html_e( 'CONFIDENCE', 'optimizely' ) ?><i class="fa fa-question-circle fa-fw"></i></div></th>
					<th><div title="<?php esc_html_e( 'The estimated number of visitors this variation needs to become statistically significant', 'optimizely' ) ?>"><?php esc_html_e( '~VISITORS REMAINING', 'optimizely' ) ?><i class="fa fa-question-circle fa-fw"></i></div></th>
					<th><div title="<?php esc_html_e( 'When clicked the variation headline will be updated and the experiment will be archived', 'optimizely' ) ?>"><?php esc_html_e( 'LAUNCH', 'optimizely' ) ?><i class="fa fa-question-circle fa-fw"></i></div></th>
				</tr>
				<% _.each( variations, function( variation ) { %>
				<tr class="variationrow <%- variation.status %> <%- variation.goalId %>" id="variation_<%- variation.variationId %>" data-var-id="<%- variation.variationId %>">
					<td class="first"><a target="_blank" href="<%- variation.editUrl %>?optimizely_x<%- variation.expID %>=<%- variation.variationId %>"><%- variation.variationName %></a></td>
					<td><%- variation.visitors %></td>
					<td><%- variation.conversions %></td>
					<td><%- variation.conversionRate %></td>
					<td><%- variation.improvement %></td>
					<td><%- variation.confidence %></td>
					<td>~<%- variation.vistitorsRemaining %></td>
					<td>
						<% if ( 'baseline' != variation.status ) { %>
						<div class="button launch <%- variation.status %>" title="<?php esc_html_e( 'Launch', 'optimizely' ) ?>"><i class="fa fa-rocket fa-fw"></i></div>
						<% } %>
					</td>
				</tr>
				<% }); %>
			</table>
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
					<h2><?php echo esc_html_e( 'Ready for review', 'optimizely' ) ?> <span><?php echo esc_html_e( 'These experiments have at least one statistically significant variation.', 'optimizely' ) ?></span></h2>
				</div>
				<div id="stillwaiting">
					<h2><?php echo esc_html_e( 'Not ready yet', 'optimizely' ) ?> <span><?php echo esc_html_e( 'These experiments still need a few more visitors before we can declare a winner', 'optimizely' ) ?></span></h2> 
				</div>
				<div class="loading" id="loading"><?php echo esc_html_e( 'Loading Results.....', 'optimizely' ) ?><br><img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) ).'images/ajax-loader.gif' ?>" /></div>
				<div id="noresults">
					<h3><?php echo esc_html_e( 'No results!', 'optimizely' ) ?></h3>
					<p><?php echo esc_html_e( 'A headline experiement must be created in Wordpress before any results will be displayed here. Please create a new post with multiple headlines, publish the post, and start the experiment. Once the experiment is created and running it will display the results here. Please Note: Only experiements created through Wordpress will be displayed here. Experiments created directly in Optimizely will not be displayed here.', 'optimizely' ) ?></p>
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
					<h3><?php esc_html_e( 'Installation Instructions', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'For full instructions on how to configure the settings and use the Optimizely plugin, please', 'optimizely' ) ?> <a href="#" target="_blank"><?php esc_html_e( 'visit our knowledge base article', 'optimizely' ) ?></a></p>

					<h3><?php esc_html_e( 'About Optimizely', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'Simple, fast, and powerful.', 'optimizely' ); ?> <a href="http://www.optimizely.com" target="_blank">Optimizely</a> <?php esc_html_e( 'is a dramatically easier way for you to improve your website through A/B testing. Create an experiment in minutes with absolutely no coding or engineering required. Convert your website visitors into customers and earn more revenue: create an account at', 'optimizely' ) ?> <a href="http://www.optimizely.com" target="_blank">optimizely.com</a> <?php esc_html_e( 'and start A/B testing today!', 'optimizely' ) ?></p>

					<h3><?php esc_html_e( 'Optimizely API tokens', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'Once you create an account, you can find your API Token on your account page at', 'optimizely' ); ?> <a href="https://app.optimizely.com/tokens">optimizely.com/tokens</a>.</p>
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

					<?php

						if ( get_option( 'optimizely_url_targeting' ) && get_option( 'optimizely_url_targeting_type' ) ){
							$optimizely_url_targeting = get_option( 'optimizely_url_targeting' );
							$optimizely_url_targeting_type = get_option( 'optimizely_url_targeting_type' );
						} else {
							// Set the default to the current host and substring
							$optimizely_url_targeting = get_site_url();
							$optimizely_url_targeting_type = 'substring';
						}	
					?>

					<h3><?php esc_html_e( 'Default URL Targeting', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'This is the default location on your site you would like to run experiments on. By default we use your domain and a substring match so that the experiment will run anywhere on your site. Used with conditional activation this will assure you change the headline no matter where it is. For more info on URL targeting ', 'optimizely' ); ?><a href="https://help.optimizely.com/hc/en-us/articles/200040835" target="_blank"><?php esc_html_e( 'please visit our knowledge base article located here','optimizely' ); ?></a></p>  
					<input id="optimizely_url_targeting" name="optimizely_url_targeting" type="text" value="<?php echo esc_attr( $optimizely_url_targeting )  ?>" />
					<select id="optimizely_url_targeting_type" name="optimizely_url_targeting_type">
						<?php 
							$url_type_array = array(
								"simple",
								"exact",
								"substring",
								"regex"
							);
							foreach ( $url_type_array as $type ) {
								if ( 0 !== strcmp( $type, $optimizely_url_targeting_type ) ) {
									echo ( '<option value="'. esc_attr( $type ) .'">'. esc_html( $type, 'optimizely' ) .'</option>' );
								} else {
									echo ( '<option value="'. esc_attr( $type ) .'" selected>'. esc_html( $type, 'optimizely' ) .'</option>' );
								}
							}
						?>
					</select>

					<h3><?php esc_html_e( 'Variation Code', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( "Optimizely will use this variation code to change headlines on your site. We've provided code that works if you have changed your headlines to have a class with the format optimizely-$POST_ID, but you might want to add or change it to work with your themes and plugins. For more information on how to update your HTML or write custom variation code please visit ", 'optimizely' ); ?><a href="#" target="_blank"><?php esc_html_e( 'this article on our knowledge base','optimizely' ); ?></a></p>  
					<p><?php esc_html_e( 'You can use the variables $POST_ID, $OLD_TITLE, and $NEW_TITLE in your code.', 'optimizely' ); ?></p>
					<textarea class="code" rows="5" name="variation_template" id="variation_template"><?php echo esc_html( get_option( 'optimizely_variation_template', OPTIMIZELY_DEFAULT_VARIATION_TEMPLATE ) ) ?></textarea>
					


					<h3><?php esc_html_e( 'Activation Mode', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'You can choose between Immediate Activation Mode or Conditional Activation Mode. If you choose immediate, the experiment will run on every page of your site reguardless if the headline is on the page or not. Conditional Activation will only run the experiment if the headline is on the page. However this does require additional coding. For more information about activation modes please visit ', 'optimizely' ); ?><a href="https://help.optimizely.com/hc/en-us/articles/200040225" target="_blank"><?php esc_html_e( 'this article on our knowledge base','optimizely' ); ?></a></p>
					<?php if( !get_option( 'optimizely_activation_mode' ) || 'conditional' == get_option( 'optimizely_activation_mode' ) ): ?>
						<input type="radio" name="optimizely_activation_mode" value="immediate"> <?php esc_html_e( 'Immediate', 'optimizely' ); ?>
						<input type="radio" name="optimizely_activation_mode" value="conditional" checked> <?php esc_html_e( 'Conditional', 'optimizely' ); ?>
						<div id="optimizely_conditional_activation_code_block">
					<?php else:	?>
						<input type="radio" name="optimizely_activation_mode" value="immediate" checked> <?php esc_html_e( 'Immediate', 'optimizely' ); ?>
						<input type="radio" name="optimizely_activation_mode" value="conditional"> <?php esc_html_e( 'Conditional', 'optimizely' ); ?>
						<div id="optimizely_conditional_activation_code_block" style="display:none;">
					<?php endif;	?> 
					
						<p><?php esc_html_e( 'You can use the variables $POST_ID and $OLD_TITLE in your code.', 'optimizely' ); ?></p>
						<textarea class="code" rows="5" name="conditional_activation_code" id="conditional_activation_code"><?php echo esc_html( get_option( 'optimizely_conditional_activation_code', OPTIMIZELY_DEFAULT_CONDITIONAL_TEMPLATE ) ) ?></textarea>
					</div>
					


					<h3><?php esc_html_e( 'Maximum number of variations to test', 'optimizely' ); ?></h3>
					<p><?php esc_html_e( 'Place a number in the text box below. This will be the maximum additional number of variations a user can test per post.', 'optimizely' ); ?></p>  

					<input id="optimizely_num_variations" name="optimizely_num_variations" type="number" maxlength="1" value="<?php echo absint( get_option( 'optimizely_num_variations', OPTIMIZELY_NUM_VARIATIONS ) ) ?>" class="code" />

					<p class="submit"><input type="submit" name="submit" value="<?php esc_html_e( 'Submit &raquo;', 'optimizely' ); ?>" class="button-primary" /></p>
				</form>
			</div>
		</div>
	</div>
</div>
