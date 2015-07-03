/* thePlatform Video Manager Wordpress Plugin
 Copyright (C) 2013-2014  thePlatform for Media Inc.
 
 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.
 
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA. */

var mpxHelper = {
	getVideos: function( range, callback ) {

		var data = {
			_wpnonce: mpxhelper_local.tp_nonce['get_videos'],
			action: 'get_videos',
			range: range,
			query: tpHelper.queryString,
			isEmbed: tpHelper.isEmbed,
			myContent: jQuery( '#my-content-cb' ).prop( 'checked' )
		};

		jQuery.post( mpxhelper_local.ajaxurl, data, function( resp ) {
			viewLoading = false;
			resp = JSON.parse( resp );
			if ( resp.isException ) {
				displayMessage( resp.description );				
			}				
			else {
				callback( resp );
			}
		} );
	},
	buildMediaQuery: function( data ) {

		var queryParams = '';
		if ( data.category )
			queryParams = queryParams.appendParams( { byCategories: data.category } );

		if ( data.search ) {
			queryParams = queryParams.appendParams( { q: encodeURIComponent( data.search ) } );
			data.sort = ''; //Workaround because solr hates sorts.
		}

		if ( data.sort ) {
			var sortValue = data.sort + ( data.desc ? '|desc' : '' );
			queryParams = queryParams.appendParams( { sort: sortValue } );
		}

		if ( data.selectedGuids )
			queryParams = queryParams.appendParams( { byGuid: data.selectedGuids } );

		return queryParams;
	},
	getCategoryList: function( callback ) {
		var data = {
			_wpnonce: mpxhelper_local.tp_nonce['get_categories'],
			action: 'get_categories',
			sort: 'order',
			fields: 'title'
		};

		jQuery.post( mpxhelper_local.ajaxurl, data,
				function( resp ) {
					callback( JSON.parse( resp ) );
				} );
	},
	//Retrieve parameters from the original request.
	getParameters: function( str ) {
		var searchString = '';
		if ( str && str.length > 0 ) {
			if ( str.indexOf( '?' ) < 0 )
				return { };
			else
				searchString = str.substring( str.indexOf( '?' ) + 1 );
		} else
			searchString = window.location.search.substring( 1 );

		var params = searchString.split( "&" )
				, hash = { };

		if ( searchString == "" )
			return { };
		for ( var i = 0; i < params.length; i++ ) {
			var val = params[i].split( "=" );
			hash[decodeURIComponent( val[0] )] = decodeURIComponent( val[1] );
		}
		return hash;
	},
	//Get a list of release URls
	extractVideoUrlfromMedia: function( media ) {
		var res = [ ];

		if ( media.entries )
			media = media['entries'].shift(); //We always only grab the first media in the list THIS SHOULD BE THE ONLY MEDIA.

		if ( media && media.content )
			media = media.content;
		else
			return res;

		for ( var contentIdx in media ) {
			var content = media[contentIdx];
			if ( ( content.contentType == "video" || content.contentType == "audio" ) && content.releases ) {
				for ( var releaseIndex in content.releases ) {
					if ( content.releases[releaseIndex].delivery == "streaming" )
						res.push( content.releases[releaseIndex].pid );
				}
			}

		}

		return res;
	}
};

//Make my life easier by prototyping this into the string.
String.prototype.appendParams = function( params ) {
	var updatedString = this;
	for ( var key in params ) {
		if ( updatedString.indexOf( key + '=' ) > -1 )
			continue;

		// if (updatedString.indexOf('?') > -1)
		updatedString += '&' + key + '=' + params[key];
		// else
		//     updatedString += '?'+key+'='+params[key];
	}
	return updatedString;
};