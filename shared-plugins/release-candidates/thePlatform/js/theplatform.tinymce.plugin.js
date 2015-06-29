/* thePlatform Video Manager Wordpress Plugin
 Copyright (C) 2013-2015 thePlatform, LLC

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

tinymce.PluginManager.add('theplatform', function (editor, url) {
    // Add a button that opens a window
    editor.addButton('theplatform', {
        tooltip: 'Embed MPX Media',
        image: url.substring(0, url.lastIndexOf('/js')) + '/images/embed_button.png',
        onclick: function () {
            wp.media({
                frame: 'post',
                state: 'iframe:theplatform'
            }).open();
        }
    });
});

tinymce.init({
    plugins: 'theplatform'
});
