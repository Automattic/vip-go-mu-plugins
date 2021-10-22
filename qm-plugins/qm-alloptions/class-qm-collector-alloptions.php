<?php
/**
 * Data collector class
 */
class QM_Collector_AllOptions extends QM_Collector {

	public $id = 'alloptions';

	public function name() {
		return __( 'AllOptions', 'query-monitor' );
	}

	public function process() {

		$alloptions = wp_load_alloptions( true );
		$total_size = 0;


		foreach ( $alloptions as $name => $val ) {
			$size        = mb_strlen( $val );
			$total_size += $size;

			$option = new stdClass();

			$option->name = $name;
			$option->size = $size;

			$options[] = $option;
		}

		// sort by size
		usort( $options, function( $arr1, $arr2 ) {
			if ( $arr1->size === $arr2->size ) {
				return 0;
			}

			return ( $arr1->size < $arr2->size ) ? -1 : 1;
		});

		$options = array_reverse( $options );

		$this->data['options']    = $options;
		$this->data['total_size'] = $total_size;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->data['total_size_comp'] = strlen( gzdeflate( serialize( $alloptions ) ) );
	}
}
