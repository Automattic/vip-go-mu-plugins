<?php
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

require_once "class-shoutem-posts-comments-dao.php";
require_once "class-shoutem-posts-dao.php";
require_once "class-shoutem-users-dao.php";
require_once "class-shoutem-events-dao.php";
require_once "class-shoutem-photos-dao.php";
require_once "class-shoutem-ngg-dao.php";
require_once "class-shoutem-flagallery-dao.php";
require_once "class-shoutem-podpress-dao.php";
require_once "class-shoutem-powerpress-dao.php";
require_once "class-shoutem-viper-dao.php";

class ShoutemStandardDaoFactory {

	function __construct() {
		$this->posts_dao = new ShoutemPostsDao();
		$this->posts_comments_dao = new ShoutemPostsCommentsDao();
		$this->users_dao = new ShoutemUsersDao();
		$this->events_dao = new ShoutemEventsDao();
		$this->photos_dao = new ShoutemPhotosDao();

		$this->ngg_dao = new ShoutemNGGDao();
		$this->flagallery_dao = new ShoutemFlaGalleryDao();
		$this->podpress_dao = new ShoutemPodpressDao();
		$this->powerpress_dao = new ShoutemPowerpressDao();
		$this->viper_dao = new ShoutemViperDao();
		$this->plugin_integration_daos = array(
			$this->ngg_dao,
			$this->flagallery_dao,
			$this->podpress_dao,
			$this->powerpress_dao,
			$this->viper_dao
		);

	}

	static public function instance() {
		static $instance = null;
		if ($instance == null) {
			$instance = new ShoutemStandardDaoFactory();
		}
		return $instance;
	}

	function get_external_plugin_integration_daos() {
		return $this->plugin_integration_daos;
	}

	function get_photos_dao() {
		return $this->photos_dao;
	}

	function get_events_dao() {
		return $this->events_dao;
	}

	function get_posts_dao() {
		return $this->posts_dao;
	}

	function get_posts_comments_dao() {
		return $this->posts_comments_dao;
	}

	function get_users_dao() {
		return $this->users_dao;
	}
}

?>