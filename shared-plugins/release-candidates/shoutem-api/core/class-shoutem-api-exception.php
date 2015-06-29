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
class ShoutemApiException extends Exception {

	var $shoutem_error_messages = array (
		'comment_duplicate_trigger' => 'Comment is duplicate!',
		'comment_flood_trigger' => 'Slow down, your comments are comming too fast',
		'comment_delete_error' => 'Comment delete error',
		'comment_create_error' => 'Error while creating comment'
	);

	/**
	 * message is required param
	 */
	public function __construct($message, $code = 0, Exception $previous = null) {
         parent::__construct($message, $code);
    }

    public function get_error_message() {
    	if(isset($this->shoutem_error_messages[$this->message])) {
    		return $this->shoutem_error_messages[$this->message];
    	}
    	return $this->message;
    }
}