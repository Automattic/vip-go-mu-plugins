(function() {
        // Load plugin specific language pack
        tinymce.PluginManager.requireLangPack('storify');

        tinymce.create('tinymce.plugins.storifyPlugin', {
                /**
                 * Initializes the plugin, this will be executed after the plugin has been created.
                 * This call is done before the editor instance has finished it's initialization so use the onInit event
                 * of the editor instance to intercept that event.
                 *
                 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
                 * @param {string} url Absolute URL to where the plugin is located.
                 */
                init : function(ed, url) {
                        // Register the command so that it can be invoked by using tinyMCE.activeEditor.execCommand('mcestorify');
                        ed.addCommand('mcestorify', function() {
                                ed.windowManager.open({
										// file is modified for VIP, we use admin-ajax.php instead of plugin's dialog.php
                                        file : ajaxurl + '?action=storify_dialog',
                                        width : 640,
                                        height : 450,
                                        inline : 1,
                                        mce_auto_focus: 1
                                }, {
                                });
                        });

                        // Register storify button
                        ed.addButton('storify', {
                                title : storify.desc,
                                cmd : 'mcestorify',
                                image : storify.pluginUrl + 'img/logo.png'
                        });

                        // Add a node change handler, selects the button in the UI when a image is selected
                        ed.onNodeChange.add(function(ed, cm, n) {
                                cm.setActive('storify', n.nodeName == 'IMG');
                        });
                                               						
                },

                /**
                 * Creates control instances based in the incomming name. This method is normally not
                 * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
                 * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
                 * method can be used to create those.
                 *
                 * @param {String} n Name of the control to create.
                 * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
                 * @return {tinymce.ui.Control} New control instance or null if no control was created.
                 */
                createControl : function(n, cm) {
                        return null;
                },

                /**
                 * Returns information about the plugin as a name/value array.
                 * The current keys are longname, author, authorurl, infourl and version.
                 *
                 * @return {Object} Name/value array containing information about the plugin.
                 */
                getInfo : function() {
                        return {
                                longname : 'Storify',
                                author : 'Storify',
                                authorurl : 'https://storify.com',
                                infourl : '',
                                version : "1.0"
                        };
                }
        });

        // Register plugin
        tinymce.PluginManager.add('storify', tinymce.plugins.storifyPlugin);
})();
