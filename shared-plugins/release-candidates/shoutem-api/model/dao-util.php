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
function dao_util_get_summary($content) {
	$sumary_size = 20;
	if(strlen($content) > $sumary_size) {
		return substr($content, 0, 20).'...';
	}
	return $content;
}


function dao_util_paginate($unpaginated_data,$options) {

	$paginated_data = array();
	$offset = (int)$options['offset'];
	$limit = (int)$options['limit'];

	$pagination_meta = array();

	if($offset > 0) {
		$previous_offset = max(0, $offset-$limit);
		$previous_limit = $limit;
		// Add pagigantion data  to buld previos request link, current request can be GET/POST depends on API client app
		// For that reason we use $_REQUEST
		$pagination_meta['previous'] = http_build_query(
			array_merge($_POST,array('limit'=>$previous_limit,'offset'=>$previous_offset))
			);
	}

	$last_element_index = $offset + $limit;
	if($last_element_index < count($unpaginated_data)) {
		$next_offset = $offset + $limit;
		$next_limit = $limit;
		// Add pagigantion data to buld next request link, current request can be GET/POST depends on API client app
		// For that reason we use $_REQUEST
		$pagination_meta['next'] = http_build_query(
			array_merge($_REQUEST,array('limit'=>$next_limit,'offset'=>$next_offset))
			);
	}
	$paginated_data['data'] = array_slice($unpaginated_data, $offset, $limit);
	$paginated_data['paging'] = $pagination_meta;
	return $paginated_data;

}

?>