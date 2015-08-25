<?php

class O2O_Query_Modifier_Taxonomy extends O2O_Query_Modifier {

	/**
	 * 
	 * @param WP_Query $wp_query
	 * @param O2O_Connection_Taxonomy $connection
	 * @param array $o2o_query 
	 */
	public static function parse_query( $wp_query, $connection, $o2o_query ) {
		if ( $o2o_query['direction'] == 'to' ) {
			parent::parse_query( $wp_query, $connection, $o2o_query );
		} else {
			$object_term = $connection->get_object_termID( $o2o_query['id'], $connection->get_taxonomy(), false );
			$tax_query = isset( $wp_query->query_vars['tax_query'] ) ? $wp_query->query_vars['tax_query'] : array( );

			$new_tax_query = array(
				'taxonomy' => $connection->get_taxonomy(),
				'field' => 'id',
				'terms' => $object_term
			);

			if ( !in_array( $new_tax_query, $tax_query ) ) {
				$tax_query[] = $new_tax_query;
			}

			$wp_query->query_vars['tax_query'] = $tax_query;
			$wp_query->parse_tax_query( $wp_query->query_vars );
		}
	}

}