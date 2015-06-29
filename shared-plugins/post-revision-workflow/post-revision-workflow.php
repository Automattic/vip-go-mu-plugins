<?php
/*
Plugin Name: Post Revision Workflow
Plugin URI: http://plugins.ten-321.com/post-revision-workflow/
Description: This plug-in adds new options to the "Publish" metabox on the Post and Page editor screens. Anyone editing a page or post has the option to publish the changes normally, publish the changes with a notification email sent to a reviewer or to hold back the changes until a reviewer approves them.
Version: 0.2a
Author: Curtiss Grymala
Author URI: http://ten-321.com/
License: GPL2
*/
/*  Copyright 2011  Curtiss Grymala  (email : cgrymala@umw.edu)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if( ! class_exists( 'post_revision_workflow' ) ) {
	require_once( dirname( __FILE__ ) . '/class-post-revision-workflow.php' );
}

if( class_exists( 'post_revision_workflow' ) ) {
	function init_post_revision_workflow() {
		return new post_revision_workflow;
	}

	if ( ! did_action( 'plugins_loaded' ) ) {
		add_action( 'plugins_loaded', 'init_post_revision_workflow' );
	} else {
		init_post_revision_workflow();
	}
}
?>