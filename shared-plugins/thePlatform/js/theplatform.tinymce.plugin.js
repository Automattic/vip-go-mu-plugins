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

tinymce.PluginManager.add( 'theplatform', function( editor, url ) {
	// Add a button that opens a window
	editor.addButton( 'theplatform', {
		tooltip: 'Embed MPX Media',
		image: url.substring( 0, url.lastIndexOf( '/js' ) ) + '/images/embed_button.png',
		onclick: function() {
			// Open window         

			var iframeUrl = ajaxurl + "?action=theplatform_media&embed=true&_wpnonce=" + editor.settings.theplatform_media_nonce;
			tinyMCE.activeEditor = editor;			

			if ( window.innerHeight < 1200 )
				height = window.innerHeight - 50;
			else
				height = 1024;

			if ( tinyMCE.majorVersion > 3 ) {
				editor.windowManager.open( {
					width: 1220,
					height: height,
					url: iframeUrl
				} );
			}
			else {
				if ( jQuery( "#tp-embed-dialog" ).length == 0 ) {
					jQuery( 'body' ).append( '<div id="tp-embed-dialog"></div>' );
				}
				jQuery( "#tp-embed-dialog" ).html( '<iframe src="' + iframeUrl + '" height="100%" width="100%">' ).dialog( { dialogClass: "wp-dialog", modal: true, resizable: true, minWidth: 1024, width: 1220, height: height } ).css( "overflow-y", "hidden" );
			}
		}
	} );

} );

tinymce.init( {
	plugins: 'theplatform'
} );