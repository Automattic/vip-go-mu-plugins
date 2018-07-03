<?php

class WPCOM_VIP_QM_Collector_DB_Queries extends QM_Collector_DB_Queries
{
	public function process_db_object($id, wpdb $db)
	{
		global $wp_the_query;
		
		$rows = array();
		$types = array();
		$total_time = 0;
		$has_result = false;
		$has_trace = false;
		$request    = trim( $wp_the_query->request );
		if ( method_exists( $db, 'remove_placeholder_escape' ) ) {
			$request = $db->remove_placeholder_escape( $request );
		}

		foreach ((array)$db->queries as $query) {

			# @TODO: decide what I want to do with this:
			if ( ( isset( $query['trace'] ) && false !== strpos($query['trace'], 'wp_admin_bar') ) and !isset($_REQUEST['qm_display_admin_bar']) ) {
				continue;
			}

			$sql = $query['query'];
			$ltime = $query['elapsed'];
			$stack = $query['debug'];

			$result = null;

			$total_time += $ltime;

			$trace = null;
			$component = null;
			$callers = explode(',', $stack);
			$caller = trim(end($callers));

			if (false !== strpos($caller, '(')) {
				$caller_name = substr($caller, 0, strpos($caller, '(')) . '()';
			} else {
				$caller_name = $caller;
			}

			$sql = $type = trim($sql);

			if (0 === strpos($sql, '/*')) {
				// Strip out leading comments such as `/*NO_SELECT_FOUND_ROWS*/` before calculating the query type
				$type = preg_replace('|^/\*[^\*/]+\*/|', '', $sql);
			}

			$type = preg_split('/\b/', trim($type), 2, PREG_SPLIT_NO_EMPTY);
			$type = strtoupper($type[0]);

			$this->log_type($type);
			$this->log_caller($caller_name, $ltime, $type);

			if ($component) {
				$this->log_component($component, $ltime, $type);
			}

			if (!isset($types[ $type ]['total'])) {
				$types[ $type ]['total'] = 1;
			} else {
				$types[ $type ]['total']++;
			}

			if (!isset($types[ $type ]['callers'][ $caller ])) {
				$types[ $type ]['callers'][ $caller ] = 1;
			} else {
				$types[ $type ]['callers'][ $caller ]++;
			}


			$is_main_query = ( $request === $sql && ( false !== strpos( $stack, ' WP->main,' ) ) );
			$row = compact('caller', 'caller_name', 'stack', 'sql', 'ltime', 'result', 'type', 'component', 'trace', 'has_main_query', 'is_main_query');

			if (is_wp_error($result)) {
				$this->data['errors'][] = $row;
			}

			if (self::is_expensive($row)) {
				$this->data['expensive'][] = $row;
			}

			$rows[] = $row;

		}

		$total_qs = count($rows);

		$this->data['total_qs'] += $total_qs;
		$this->data['total_time'] += $total_time;
		
		$has_main_query = wp_list_filter( $rows, array(
			'is_main_query' => true,
		) );

		# @TODO put errors in here too:
		# @TODO proper class instead of (object)
		$this->data['dbs'][ $id ] = (object)compact('rows', 'types', 'has_result', 'has_trace', 'total_time', 'total_qs', 'has_main_query');

	}
}
