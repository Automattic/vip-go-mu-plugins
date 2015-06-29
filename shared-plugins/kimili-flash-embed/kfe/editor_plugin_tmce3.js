(function() {
	// Load plugin specific language pack
	//tinymce.PluginManager.requireLangPack('kfe');

	tinymce.create('tinymce.plugins.kfePlugin', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */
		init : function(ed, url) {
			// Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mceExample');
			ed.addCommand('setRequiredKFEAtts', function() {
				var n = prompt("What is the absolute URL to your .SWF?");
				var h = prompt("How tall is your SWF?\n(In pixels or a percentage - i.e. 250 or 100%)");
				var w = prompt("How wide is your SWF?\n(In pixels or a percentage - i.e. 125 or 75%)");
				var content = prompt('Enter the content that you\'d like to show to users without Flash.') || ' '; // need to use an single space otherwise the shortcode parser chokes with multiple tags on the page
				if (n && h && w) {
					var text = '[kml_flashembed movie="' + n + '" height="' + h + '" width="' + w + '"]' + content + '[/kml_flashembed]';
					tinyMCE.execInstanceCommand('content', 'mceInsertContent', false, text);
					return true;	
				}
			});

			// Register example button
			ed.addButton('kfe', {
				title : 'Kimili Flash Embed',
				cmd : 'setRequiredKFEAtts',
				image : url + '/images/flash.gif'
			});

			// Add a node change handler, selects the button in the UI when a image is selected
			ed.onNodeChange.add(function(ed, cm, n) {
				cm.setActive('kfe', n.nodeName == 'IMG');
			});
		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : 'Kimili Flash Embed',  
				author : 'Michael Bester',  
				authorurl : 'http://kimili.com',  
				infourl : 'http://kimili.com/plugins/kml_flashembed',  
				version : "1.4.1"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add('kfe', tinymce.plugins.kfePlugin);
})();