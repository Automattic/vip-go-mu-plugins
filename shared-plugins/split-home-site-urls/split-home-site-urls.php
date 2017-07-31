<?php
/*
 Plugin name: Support Split Home and Site Hosts
 Description: Provide a consistent user experience and enforce domain consistency when the home and site URLs are on different hosts.
 Version: 1.0
 Author: Erick Hitter, Automattic
 Author URI: https://vip.wordpress.com/
 License: GPLv2
*/

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; version 2 of the License.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
 * Disable when plugin's conditions aren't met
 *
 * URLs must differ by more than just protocol for this plugin to be useful
 */
if ( preg_replace( '#https?://#i', '', home_url( '/' ) ) === preg_replace( '#https?://#i', '', site_url( '/' ) ) ) {
	_doing_it_wrong( __NAMESPACE__, 'Called with identical site and home URls. Plugin functionality not loaded.', '1.0' );
	return;
}

/**
 * Generic utilities
 */
require_once __DIR__ . '/inc/utils.php';

/**
 * Rewrite static asset URLs back to the home URL, as Core normally relies on site URL
 */
require_once __DIR__ . '/inc/asset-urls.php';

/**
 * Canonical and other redirect handling
 */
require_once __DIR__ . '/inc/redirects.php';

/**
 * Login screen handling
 */
require_once __DIR__ . '/inc/login.php';

/**
 * Other URL fixes
 */
require_once __DIR__ . '/inc/misc.php';
