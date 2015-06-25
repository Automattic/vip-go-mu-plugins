<?php
/*
Plugin Name: MediaPass Subscriptions
Plugin URI: http://www.mediapass.com/
Description: Integrate your MediaPass Account to manage Premium Subscriptions to your WordPress site.
Author: MediaPass Inc.
Version: v2.1
Author URI: http://www.mediapass.com/
*/
/*
    Copyright (C) 2012 Media Pass Inc.
 
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

require_once( dirname( __FILE__ ) . "/shortcodes.php");
require_once( dirname( __FILE__ ) . "/mediapass_plugin.php");

$mediapass_instance = new MediaPass_Plugin();

?>
