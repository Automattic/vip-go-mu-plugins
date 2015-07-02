( function( $ ) {

	function optimizelyResultsPage( apiToken, projectId ) {
		var optly = new OptimizelyAPI( apiToken );	

		// Fetch only Wordpress experiments from project
		optly.get( 'projects/' + projectId + '/experiments/', function( response ) {
			optly.wordpressExps = [];
			var resultsArray = [];

			for ( i = 0; i < response.length; i++ ) {
				if ( response[ i ].description.indexOf( 'Wordpress' ) > -1 
					&& 'Archived' != response[ i ].status
					&& 'Not started' != response[ i ].status
					&& 'Draft' != response[ i ].status ) {
					resultsArray.push( response[ i ] );
				}
			}
		
			if ( resultsArray.length > 0 ) {
				for ( i = 0; i < resultsArray.length; i++ ) {
					getWPExpResults( resultsArray[ i ],function( exp ) {
						displayResultsList( exp,i,function() {
							showGoalSelected( exp.id );
							addSelectChange( exp.id );
						});
					});
				}
			} else {
				$( '#noresults' ).show();
				$( '#loading' ).hide();
				$( '#ready' ).hide();
			}
		});


		// Pause experiment when pause button is pressed
		$( 'html' ).delegate( '.pause', 'click', function() {
			var expID = $( this ).parents( '.opt_results' ).attr( 'data-exp-id' );
			pauseExperiment( expID );
		});
	
		// Start experiment when play button is pressed
		$( 'html' ).delegate( '.play', 'click', function() {
			var expID = $( this ).parents( '.opt_results' ).attr( 'data-exp-id' );
			startExperiment( expID );
		});
	
		// Archive experiment when archive button is pressed
		$( 'html' ).delegate( '.archive', 'click', function() {
			var expID = $( this ).parents( '.opt_results' ).attr( 'data-exp-id' );
			archiveExperiment( expID );
		});

		$( 'html' ).delegate( '.edit', 'click', function() {
			var expID = $( this ).parents( '.opt_results' ).attr( 'data-exp-id' );
			window.open( 'https://app.optimizely.com/edit?experiment_id=' + parseInt( expID ) );
		});

		$( 'html' ).delegate( '.fullresults', 'click', function() {
			var expID = $( this ).parents( '.opt_results' ).attr( 'data-exp-id' );
			window.open( 'https://app.optimizely.com/results2?experiment_id=' + parseInt( expID ) );
		});

		// Launch winning variation when Launch button is clicked
		$( 'html' ).delegate( '.launch', 'click', function() {
			var winningVarName = $( this ).parents( 'td' ).siblings( '.first' ).children( 'a' ).text();
			var expID = $( this ).parents( '.opt_results' ).attr( 'data-exp-id' );
			var expTitle = $( this ).parents( '.opt_results' ).attr( 'data-exp-title' );
			launchWinner( expID, expTitle, winningVarName );
		});

		// Changes the results for the goal selected
		function addSelectChange( expId ) { 
			$( '#goal_' + expId ).bind( 'change', function() { 
				showGoalSelected( expId );
			});
		} 

		// Simple compare function to sort goals by name
		function compare( a,b ) {
			if ( a.goal_name < b.goal_name ) {
				 return -1;
			}
		
			if ( a.goal_name > b.goal_name ) {
				return 1;
			}
		
			return 0;
		}

		// Gets the results for the experiment
		function getWPExpResults( expObj,cb ) {
			expObj.results = [];
			optly.get( 'experiments/' + expObj.id + '/stats', function( response ) { 
				var goalNameArray = [];
				response.sort( compare );
				expObj.results = response;
				expObj.avgVisitorCount = getAverageVisitor( expObj.results );
				cb( expObj );
			});
		}

		// AJAX function that updates the title in Wordpress
		function launchWinner( expID, expTitle, winningVarName ) {
			// Get the ID of the Wordpress post
			var wpPostID = expTitle.substring( 11, expTitle.indexOf( ']' ) );
			var data = {
				action: 'update_post_title',
				post_id: wpPostID,
				title: winningVarName
			};

			$.post( wpAjaxUrl, data, function() {
				$( '#exp_' + expID ).fadeOut( 1000, function() {
					$message = $( '<h3>' ).text( 'You have succesfully launched the new headline' );
					$old = $( '<p>' ).text( 'Old Headline: ' + expTitle );
					$new = $( '<p>' ).text( 'New Headline: ' + winningVarName );
				
					$( '#successMessage' )
						.html( '' )
						.append( $message )
						.append( $old )
						.append( $new )
						.show();
				
					// Archive the Experiement
					archiveExperiment( expID );
				});
			});
		}

		// Pause Experiment
		function pauseExperiment( experimentID ) {
			optly.patch( 'experiments/' + experimentID, { 'status': 'Paused' }, function( response ) {
				$( '.opt_results[data-exp-id=' + experimentID + ']' )
					.find( '.pause' )
					.removeClass( 'pause' )
					.addClass( 'play' );
				
				$( '.opt_results[data-exp-id=' + experimentID + ']' )
					.find( '.fa-pause' )
					.removeClass( 'fa-pause' )
					.addClass( 'fa-play' );
			});
		}

		// Start Experiment
		function startExperiment( experimentID ) {
			optly.patch( 'experiments/' + experimentID, { 'status': 'Running' }, function( response ) {
				$( '.opt_results[data-exp-id=' + experimentID + ']' )
					.find( '.play' )
					.removeClass( 'play' )
					.addClass( 'pause' );
				
				$( '.opt_results[data-exp-id=' + experimentID + ']' )
					.find( '.fa-play' )
					.removeClass( 'fa-play' )
					.addClass( 'fa-pause' );
			});
		}

		// Archive Experiment
		function archiveExperiment( experimentID ) {
			optly.patch( 'experiments/'+ experimentID, { 'status': 'Archived' }, function( response ) {
				$( '.opt_results[data-exp-id=' + experimentID + ']' ).hide();
			});
		}

		// Will display the resutls and build the HTML
		function displayResultsList( exp, i, cb ) {
			$( '.loading' ).hide();
			var data = buildResultsModuleData( exp );
			var tpl = _.template( $( '#optimizely_results' ).html() );
			if ( data.isSignificant ) {
				$( '#ready' ).append( tpl( data ) );
				$( '#ready' ).show();
			} else {
				$( '#stillwaiting' ).append( tpl( data ) );
				$( '#stillwaiting' ).show();
			}
			cb();
		}

		// Loops through the variations and gets an average of the visitor count for powered testing
		function getAverageVisitor( results ) {
			var totalVisitors = 0;
			for ( var i=0; i < results.length; i++ ) {
				totalVisitors += results[ i ].visitors;
			}

			return totalVisitors/results.length;
		}

		// Used to convert the value returned by the results API to a rounded percentage
		function getRoundedPercentage( num ) {
			return ( num*100 ).toFixed( 2 ) + '%';
		}

		// Changes the goal results based on what is selcted
		function showGoalSelected( expID ) {
			$( '#exp_' + expID ).find( '.variationrow' ).hide();
			var goalClass = $( '#goal_' + expID ).val();
			$( '#exp_' + expID ).find( '.'+goalClass ).show();

		}
		
		// Main function that builds the HTML for each results block
		function buildResultsModuleData( exp ) {
			// Set the checkbox html
			var statusClass = 'play';
		
			if ( 'Running' == exp.status ) {
				statusClass = 'pause';
			}
		
			var expTitle = exp.description;
			expTitle = expTitle.substring( expTitle.indexOf( ']:' ) + 3 );
			if ( expTitle.length > 73 ) {
				expTitle = expTitle.substring( 0, 72 ) + '...';
			}
			
			var goalIdArray = [];
			var goalOptions = [];
			for ( var i = 0; i < exp.results.length; i++ ) {
				var result = exp.results[ i ];
				var selected = '';
				if ( goalIdArray.indexOf( result.goal_id ) == -1 ) {
					if ( result.goal_name == 'Views to page' ) {
						selected = 'selected';
					} else {
						selected = '';
					}
					
					goalOptions.push({
						id: result.goal_id,
						selected: selected,
						name: result.goal_name
					});

					goalIdArray.push( result.goal_id );
				} 
			}
			
			var variations = [];
			var isSignificant = false;
			for ( i = exp.results.length -1; i >= 0; i-- ) {
				var result = exp.results[ i ],
					improvement,
					conversion_rate,
					avgVisitors = getAverageVisitor( exp.results ),
					confidence,
					vistitorsRemaining,
					status,
					rowColor;
				
				if ( 'baseline' == result.status ) {
					improvement = 'baseline';
					confidence = '-';
					vistitorsRemaining = '-';
					status = 'baseline';
				} else {
					confidence = getRoundedPercentage( result.statistical_significance );
					improvement = getRoundedPercentage( result.improvement );
					vistitorsRemaining = result.visitors_until_statistically_significant;
					if(result.status == 'inconclusive') {
						// results are inconclusive determine if the variation is winning or loosing
						if(result.improvement > 0) {
							status = 'winning';
						} else {
							status = 'losing';
						}
					} else {
						isSignificant = true;
						status = result.status;
					}
				}
				
				var conversionRate = getRoundedPercentage( result.conversion_rate );
				
				variations.push({
					expID: exp.id,
					status: status,
					goalId: result.goal_id,
					variationId: result.variation_id,
					variationName: result.variation_name,
					editUrl: exp.edit_url,
					visitors: result.visitors,
					conversions: result.conversions,
					conversionRate: conversionRate,
					improvement: improvement,
					confidence: confidence,
					vistitorsRemaining: vistitorsRemaining
				});
			}

			data = {
				id: exp.id,
				isSignificant: isSignificant,
				description: exp.description,
				title: expTitle,
				goalOptions: goalOptions,
				statusClass: statusClass,
				variations: variations
			};
			
			return data;
		}
	}

	$( document ).ready(function() {
		if (typeof pagenow != 'undefined' && 'toplevel_page_optimizely-config' == pagenow && '' != optimizelySettings.token && '' != optimizelySettings.projectId ) {
			optimizelyResultsPage( optimizelySettings.token, optimizelySettings.projectId );
		} else {
			$( '#loading' ).hide();
		}
	});
})(	jQuery	);
