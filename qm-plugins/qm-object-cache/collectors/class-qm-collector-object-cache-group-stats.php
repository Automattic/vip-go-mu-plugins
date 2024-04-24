<?php
/**
 * Data collector class
 */
class QM_Collector_Object_Cache_Group_Stats extends QM_Collector {

	public $id = 'object_cache_group_stats';

	public function name() {
		return __( 'Group Stats', 'qm-object-cache' );
	}

	/**
	 * @return QM_Data
	 */
	public function get_storage(): QM_Data {
		return new QM_Data_Object_Cache();
	}

	/**
	 * Processes data from the global object cache variable to gather group stats.
	 *
	 * @return void
	 */
	public function process() {
		global $wp_object_cache;
		if ( ! method_exists( $wp_object_cache, 'get_stats' ) ) {
			return;
		}

		$stats       = $wp_object_cache->get_stats();
		$group_stats = array();

		foreach ( (array) $stats['operations'] as $operation_type => $operations ) {
			// We don't care about flush numbers, skip.
			if ( 'get_flush_number' === $operation_type ) {
				continue;
			}

			foreach ( $operations as $operation ) {
				// Set the group or use '[unknown]' if not set; should never happen, but might as well be safe.
				$group = $operation['group'] ?? '[unknown]';

				// Initialize and increment count for each group.
				$group_stats[ $operation_type ][ $group ]['count'] ??= 0;
				++$group_stats[ $operation_type ][ $group ]['count'];

				// Add the time and size to the group's stats.
				$group_stats[ $operation_type ][ $group ]['time'] ??= 0;
				$group_stats[ $operation_type ][ $group ]['size'] ??= 0;
				$group_stats[ $operation_type ][ $group ]['time']  += $operation['time'];
				$group_stats[ $operation_type ][ $group ]['size']  += $operation['size'];
			}
		}

		// Assign totals, operation counts and group stats to class data.
		$this->data->totals           = $stats['totals'] ?? null;
		$this->data->operation_counts = $stats['operation_counts'] ?? null;
		$this->data->group_stats      = $group_stats ?? null;
	}
}
