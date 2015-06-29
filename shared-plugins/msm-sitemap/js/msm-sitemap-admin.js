jQuery( document ).ready( function() {
	// Setup the blank flot plot
	var placeholder = jQuery( '.stats-placeholder' );

	// The largest value shown on the graph
	var max = { date: '', count: -1 };

	// Move the tooltip to body
	jQuery( '#tooltip' ).appendTo( 'body' );
	
	var plot_options = {
		xaxis: {
			mode: 'time',
			timeformat: '%b %e',
			color: '#ddd',
			tickColor: 'rgba(255, 255, 255, 0)',
		},
		yaxis: {
			color: '#ddd',
			tickColor: '#ddd',
			tickDecimals: 0,
			tickFormatter: function ( v, a ) { return parseInt( v ); },
		},
		grid: {
			show: true,
			color: '#ddd',
			borderWidth: 1,
			margin: 15,
			labelMargin: 10,
			hoverable: true,
			autoHighlight: true,
		},
		colors: [ '#ececec' ],

	};

	var data = [ 
		{
			data: [],
			bars: {
				align: "center",
				barWidth: 12 * 60 * 60 * 1000,
				borderColor: '#ececec',
				fill: true,
				fillColor: '#ececec',
				show: true,
			},
			highlightColor: '#2EA2CC',
		}
	];

	jQuery.plot( placeholder, data, plot_options );

	// Start querying the server for sitemap counts
	msm_query_sitemap_counts();

	function msm_query_sitemap_counts() {
		var data = {
			action: 'msm-sitemap-get-sitemap-counts',
			_wpnonce: jQuery( '#_wpnonce' ).val(),
		};

		jQuery.ajax( {
			url: ajaxurl,
			type: 'GET',
			data: data,
			dataType: 'json',
			success: msm_sitemap_counts_received
		} );

		// Refresh to get update counts
		setTimeout( msm_query_sitemap_counts, 60000 );
	}

	function msm_sitemap_counts_received( response ) {
		// Update the top stats
		jQuery( '#sitemap-count' ).html( response['total_sitemaps'] );
		jQuery( '#sitemap-indexed-url-count' ).html( response['total_indexed_urls'] );

		// Make the maximum get calculated from the current dataset
		max.count = -1;

		var formatted_data = [];
		for ( var e in response['sitemap_indexed_urls'] ) {
			formatted_data.push( [ new Date(e).getTime(), response['sitemap_indexed_urls'][e], e ] );

			// Check for a maximum
			if ( response['sitemap_indexed_urls'][e] > max.count ) {
				max.count = response['sitemap_indexed_urls'][e];
				max.date = e;
			}
		}

		// Update the plot
		data[0].data = formatted_data;
		jQuery.plot( placeholder, data, plot_options );
		
		// Update the maxima information
		update_stats_maximum();
	}

	function update_stats_maximum() {
		jQuery( '#stats-graph-max' ).html( max.count );
		jQuery( '#stats-graph-max-date' ).html( max.date );
		jQuery( '#stats-graph-num-days' ).html( data[0].data.length );

		jQuery( '#stats-graph-summary' ).fadeIn();
	}

	function show_tooltip( x, y, contents ) {
		jQuery( '#tooltip' ).stop();
		jQuery( '#tooltip .content' ).html( contents );
        jQuery( '#tooltip' ).css( {
            top: y + plot_options.grid.margin,
            left: x + plot_options.grid.margin,
        } ).fadeIn( 'fast' );
    }

    var previous_point = null;
    placeholder.bind( 'plothover', function ( event, pos, item ) {
		if ( item ) {
			if ( previous_point != item.dataIndex ) {
				previous_point = item.dataIndex;

				// Get and format the data values
				var x = item.series.xaxis.tickFormatter( item.datapoint[0], item.series.xaxis );
				var y = item.series.yaxis.tickFormatter( item.datapoint[1], item.series.yaxis );

				show_tooltip( item.pageX, item.pageY, x + ': ' + y );
			}
		} else {
			jQuery( '#tooltip' ).stop();
			jQuery( '#tooltip' ).fadeOut( 'fast' );
			previous_point = null;
		}
    });

} );