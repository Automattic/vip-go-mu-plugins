<?php
/**
 * @package nc-plugin
 * @author  Md Imranur Rahman <imranur@newscred.com>
 *
 *
 *  Copyright 2012 NewsCred, Inc.  (email : sales@newscred.com)
 *
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 2, as
 *  published by the Free Software Foundation.
 *
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/**
 *  NC_Template Class
 *  its render the template file
 *  for this plugin
 */

class NC_Template {
    /**
     * Assigns a variable which can be referenced by the displayed
     * template during output.
     *
     * @param   string      Name of the variable
     * @param   mixed       Value
     * @return  void
     */
    public function assign ( $name, $value ) {
        $this->$name = $value;
    }

    /**
     * Loads a template (usually a file ending in .tpl.php) which can
     * include valid XHTML and optional embedded PHP inline source code.
     *
     * The path for templates is located in the framework directory's
     * "templates" sub-directory. As an alternative (and to prevent the
     * templates from being overwritten), templates can be saved in
     * the mytemplates sub-directory. If they exist in mytemplates,
     * they will be used instead of their templates counterpart.
     *
     * @param   string      Path to file
     * @return  void
     */
    public function display ( $file ) {
        /* Check for custom template */
        $flies_array = array(
            "null",
            "metabox/index.php",
            "metabox/addimage.php",
            "metabox/remove-image.php",
            "myfeeds/index.php",
            "myfeeds/includes/api-form.php",
            "myfeeds/includes/myfeed-form.php",
            "myfeeds/includes/myfeed-list.php",
            "settings/index.php",
            "messages/active_mu_sites.php",
            "messages/openssl.php"
        );

        if (  array_search($file, $flies_array) > 0 && file_exists( $path = sprintf( NC_VIEW_PATH . '/%s', $file ) )  ) {
            include( $path );
        }
        /* Template does not exist */
        else {
            throw new NC_E_NOTEMPLATE();
        }


    }

}
