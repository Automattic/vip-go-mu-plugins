(function( $ ) {

	function optimizelyEditPage() {
		// Initialize data from the input fields
		var projectId = $( '#optimizely_project_id' ).val();
		var optly = new OptimizelyAPI( $( '#optimizely_token' ).val() ); 
		
		if ( !! $( '#optimizely_experiment_id' ).val() ) {
			optly.get( 'experiments/' + $( '#optimizely_experiment_id' ).val(), function( response ) {
				optly.experiment = response;
				showExperiment( optly.experiment );
			});
		} else {
			optly.experiment = {
				id: $( '#optimizely_experiment_id' ).val(),
				status: $( '#optimizely_experiment_status' ).val()
			}
			$( '#optimizely_not_created' ).show();
			$( '#optimizely_created' ).hide();  
		}

		// On click, run the createExperiment function
		$( '#optimizely_create' ).click(function() {
			createExperiment();
		});

		// Then, handle starts and pauses on the experiment
		$( '#optimizely_toggle_running' ).click(function() {
			if ( optly.experiment.status == 'Running' ) {
				pauseExperiment( optly.experiment );
			} else {
				startExperiment( optly.experiment );
			}
		});

		// Render the experiment's state on the page
		function showExperiment( experiment ) {
			// ID and links
			$( '#optimizely_experiment_id' ).val( experiment.id );
			$( '#optimizely_view' ).attr( 'href', 'https://app.optimizely.com/edit?experiment_id=' + experiment.id );

			// Status and buttons
			$( '#optimizely_experiment_status' ).val( experiment.status );
			$( '#optimizely_experiment_status_text' ).text( experiment.status );
			if ( experiment.status == 'Running' ) {
				$( '#optimizely_toggle_running' ).text( 'Pause Experiment' );
			} else {
				$( '#optimizely_toggle_running' ).text( 'Start Experiment' );
			}

			// Hide create button, show status
			$( '#optimizely_not_created' ).hide();
			$( '#optimizely_created' ).show();  

			// Update Wordpress backend w/ experiment data
			var data = {
				action: 'update_experiment_meta',
				post_id: $( '#post_ID' ).val(),
				optimizely_experiment_id: experiment.id,
				optimizely_experiment_status: experiment.status
			};

			$( '.optimizely_variation' ).each(function( index, input ) {
				data[ $( input ).attr( 'name' ) ] = $( input ).val();
			});
				$.post( wpAjaxUrl, data );
		}

		/* 
		Replace all dynamic place holders with the values of the post and variation
		*/

		function replacePlaceholderVariables (template, newTitle){
			var postId = $( '#post_ID' ).val();
			var originalTitle = $( '#title' ).val();
			var code = template
				.replace( /\$OLD_TITLE/g, originalTitle )
				.replace( /\$NEW_TITLE/g, newTitle )
				.replace( /\$POST_ID/g, postId );

			return code;
		}

		// This function creates an experiment by providing a description based on the post's title and an edit_url based on the permalink of the Wordpress post. We send these as a POST request and register a callback to run the onExperimentCreated function when it completes.
		function createExperiment() {
			$( '#optimizely_create' ).text( 'Creating...' );
			experiment = {};
			post_id = $( '#post_ID' ).val();
			experiment.description = 'Wordpress [' + post_id + ']: ' + $( '#title' ).val();
			
			// Activation Mode
			experiment.activation_mode = $( '#optimizely_activation_mode' ).val();
			if('conditional' === experiment.activation_mode ){
				experiment.conditional_code = replacePlaceholderVariables( $( '#optimizely_conditional_activation_code' ).val() , "" );
			}
			experiment.edit_url = $( '#optimizely_experiment_url' ).val();

			// Setup url targeting
			var loc = document.createElement( 'a' );
			loc.href = experiment.edit_url;
			var urlTargetdomain = loc.hostname;
			var urlTargetType = 'substring';
			if ( "" != $( '#optimizely_url_targeting' ).val() &&  "" != $( '#optimizely_url_targeting_type' ).val() ){
				urlTargetdomain = $( '#optimizely_url_targeting' ).val();
				urlTargetType = $( '#optimizely_url_targeting_type' ).val();
			}
			experiment.url_conditions = [
				{
					'match_type': urlTargetType,
					'value': urlTargetdomain
				}
			];

			optly.post( 'projects/' + projectId + '/experiments', experiment, onExperimentCreated );
		}
		
		/*
		The experiment we created has two built-in variations, but now we need to add a third and update the content. 
		Since we're adding a variation, we also need to calculate the traffic weight to use for each one. 
		Once we've done this, we'll call the createVariation function explained below.
		Our experiment comes with an Engagement goal, but we'll also add one to measure views to the post.
		*/
		function onExperimentCreated( experiment ) {
			// Pause for 200ms so that the experiment is guarenteed to be created before editing and adding variations
			setTimeout(function() {
				optly.experiment = experiment;
				var variations = $( '.optimizely_variation' ).filter(function() {
					return $( this ).val().length > 0
				});

				// Set variation weights
				var numVariations = variations.length + 1;
				var variationWeight = Math.floor( 10000 / numVariations );
				var leftoverWeight = 10000 - ( variationWeight * numVariations );

				// Create variations
				variations.each(function( index, input ) {
					var weight = variationWeight;
					setTimeout(function() {
						createVariation( experiment, index + 1, $( input ).val(), weight );
					}, 200 );
				});

				// Update original with correct traffic allocation
				var origVariationWeight = { 'weight': variationWeight + ( leftoverWeight > 0 ? leftoverWeight : 0 ) };
				optly.patch( 'variations/' + experiment.variation_ids[0], origVariationWeight, checkExperimentReady );

				// Create goal
				createGoal( experiment );
			}, 1000 );
		}
		
		/*
		We create a pageview goal that measures how many times the post is viewed.
		We add one url, the permalink, and use the substring match type.
		We also set 'addable' to false so that the goal won't clog up the list of goals for other experiments.
		Finally, we associate the goal with the experiment we just created by adding the experiment's id to experiment_ids. 
		We POST the goal to the projects/{id}/goals endpoint to create it.
		*/
		function createGoal( experiment ) {
			var goal = {
				goal_type: 3, // pageview goal
				title: 'Views to page',
				urls: [ $( '#optimizely_experiment_url' ).val() ],
				url_match_types: [4], // substring
				addable: false, // don't clog up the goal list
				experiment_ids: [ experiment.id ]
			}

			optly.post( 'projects/' + experiment.project_id + '/goals/', goal, checkExperimentReady );
		}
		
		
		/*
		To create a variation, we first generate the variation code.
		We use a template based on the Wordpress theme, and then we drop in the values for our variation. The result would be:
		$( '.post-27 .entry-title a' ).text( 'Alternate Title #1' );
		Once we've generated this variation code, we include it in the js_component parameter of our API request. 
		We also add a variation title and weight.
		In this example, we have two alternate headlines plus an original. 
		When we created the experiment, it also came with two variations that were created automatically. 
		We'll leave variation 0 alone as the original, update variation 1 to use the first alternate headline, and create a new variation 2 with the second alternate headline.
		*/
		function createVariation( experiment, index, newTitle, weight ) {
			// Generate variation code
			var variationTemplate = $( '#optimizely_variation_template' ).val();
			var postId = $( '#post_ID' ).val();
			var originalTitle = $( '#title' ).val();
			var code = replacePlaceholderVariables( variationTemplate , newTitle );

			// Request data
			var variation = {
				'description': newTitle,
				'js_component': code,
				'weight': weight,
			}

			// Update variation #1, create the others
			if ( index == 1 ) {
				optly.patch( 'variations/' + experiment.variation_ids[1], variation, checkExperimentReady );
			} else {
				optly.post( 'experiments/' + experiment.id + '/variations', variation, checkExperimentReady );
			}
		}

		/*
		Once all the PUT and POST requests have returned, we're done!
		At this point, we can let the user know that the experiment is created and ready.
		*/
		function checkExperimentReady( response ) {
			if ( 0 == optly.outstandingRequests ) {
				showExperiment( optly.experiment );
			}
		}

		/*
		To start a pause an experiment, we just need to change it's status to running. 
		The patch method GETs the experiment metadata, changes the specified fields, and PUTs the object back to Optimizely.
		*/
		function startExperiment( experiment ) {
			$( '#optimizely_toggle_running' ).text( 'Starting...' );
				optly.patch( 'experiments/' + experiment.id, { 'status': 'Running' }, function( response ) {
				optly.experiment = response;
				showExperiment( optly.experiment );
			});
		}

		function pauseExperiment( experiment ) {
			$( '#optimizely_toggle_running' ).text( 'Pausing...' );
				optly.patch( 'experiments/' + experiment.id, { 'status': 'Paused' }, function( response ) {
				optly.experiment = response;
				showExperiment( optly.experiment );
			});
		}

	}

	$( document ).ready(function() {
		optimizelyEditPage();
	});

})( jQuery );
