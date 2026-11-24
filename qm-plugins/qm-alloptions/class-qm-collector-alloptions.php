<?php
/**
 * Data collector class
 */
class QM_Collector_AllOptions extends QM_Collector {

	public $id = 'alloptions';

	public function name() {
		return __( 'AllOptions', 'query-monitor' );
	}

	/**
	 * @return QM_Data
	 */
	public function get_storage(): QM_Data {
		return new QM_Data_AllOptions();
	}

	public function process() {
		// Only the HTML dispatcher shows this panel, but the REST, redirect and Ajax
		// dispatchers process every collector too. Skip refetching and compressing alloptions there.
		$html_dispatcher = QM_Dispatchers::get( 'html' );
		if ( ! $html_dispatcher || ! $html_dispatcher->should_dispatch() ) {
			return;
		}

		$alloptions = wp_load_alloptions( true );
		$total_size = 0;
		$options    = [];

		foreach ( $alloptions as $name => $val ) {
			$size        = is_null( $val ) ? 0 : mb_strlen( $val );
			$total_size += $size;

			$option = new stdClass();

			$option->name = $name;
			$option->size = $size;

			$options[] = $option;
		}

		// sort by size
		usort( $options, function ( $arr1, $arr2 ) {
			if ( $arr1->size === $arr2->size ) {
				return 0;
			}

			return ( $arr1->size < $arr2->size ) ? -1 : 1;
		});

		$options = array_reverse( $options );

		$this->data->options    = $options;
		$this->data->total_size = $total_size;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$this->data->total_size_comp = strlen( gzdeflate( serialize( $alloptions ) ) );
	}
}
