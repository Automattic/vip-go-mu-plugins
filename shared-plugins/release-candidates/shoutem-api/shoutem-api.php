<?php
/*
Plugin Name: ShoutEm API
Plugin URI: http://wordpress.org/extend/plugins/shoutem-api/
Description: Exposes REST API for accessing blog posts and post comments, as well as adding and deleting comments. For more information, take a look at <a href="http://www.shoutem.com">www.shoutem.com</a>.
Version: 1.3.14
Author URI: http://www.shoutem.com
*/

/*
  Copyright 2011 by ShoutEm, Inc. (www.shoutem.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action( 'init', function() {
    global $shoutem_api;

    $shoutem_api_dir = dirname(__FILE__);
    require_once "$shoutem_api_dir/core/class-shoutem-api-exception.php";
    require_once "$shoutem_api_dir/core/class-shoutem-api.php";
    require_once "$shoutem_api_dir/core/class-shoutem-api-encryption.php";
    require_once "$shoutem_api_dir/core/class-shoutem-api-authentication.php";
    require_once "$shoutem_api_dir/core/class-shoutem-api-caching.php";
    require_once "$shoutem_api_dir/model/class-shoutem-dao.php";
    require_once "$shoutem_api_dir/model/class-shoutem-standard-dao-factory.php";
    require_once "$shoutem_api_dir/core/class-shoutem-api-options.php";
    require_once "$shoutem_api_dir/core/class-shoutem-api-response.php";
    require_once "$shoutem_api_dir/core/class-shoutem-api-request.php";
    require_once "$shoutem_api_dir/controllers/class-shoutem-controller.php";
    require_once "$shoutem_api_dir/controllers/class-shoutem-controller-view.php";
    require_once "$shoutem_api_dir/library/aes128.php";
    require_once "$shoutem_api_dir/library/JSON.php";
    require_once "$shoutem_api_dir/library/shoutem-sanitizer.php";
    $shoutem_api = new ShoutemApi($shoutem_api_dir, __FILE__);
    $shoutem_api->init();
});
