<?php

abstract class aLiftFormFilter {

	/**
	 *
	 * @var LiftField
	 */
	public $field;
	public $label;
	public $args;

	/**
	 *
	 * @param LiftField $field
	 * @param string $label
	 * @param array $args
	 */
	public function __construct( $field, $label, $args = array( ) ) {
		$defaults = array(
			'control' => 'LiftLinkControl'
		);
		$this->label = $label;
		$this->args = wp_parse_args( $args, $defaults );
		$this->field = $field;
		add_filter( 'lift_form_filters', array( $this, '_addFormFilter' ), 10, 2 );
		add_filter( 'lift_form_field_' . $this->field->getName(), array( $this, 'getHTML' ), 10, 3 );
	}

	/**
	 *
	 * @param array $filter_fields
	 * @param Lift_Search_Form $lift_search_form
	 * @return array
	 */
	public function _addFormFilter( $filter_fields, $search_form ) {
		if ( $search_form->lift_query->wp_query->is_search() )
			return array_merge( $filter_fields, array( $this->field->getName() ) );
		return $filter_fields;
	}

	/**
	 * @param string $filterHTML the unfiltered html to be overwritten
	 * @param Lift_Search_Form $lift_search_form
	 * @param array $args
	 * @return string the resulting control
	 */
	public function getHTML( $filterHTML, $lift_search_form, $args ) {
		extract( $args );
		$control_items = $this->getControlItems( $lift_search_form->lift_query );
		if ( empty( $control_items ) ) {
			return $filterHTML;
		}

		$control = new $this->args['control']( $lift_search_form, $this->label, $control_items, $this->args );
		return $before_field . $control->toHTML() . $after_field;
	}

	/**
	 * Returns the selectable items for the filter.  All items should be objects with the following fields:
	 * 	-selected : boolean whether that value is currently selected
	 *  -value : mixed, the value that will be added to the request vars when selected
	 *  -label : the label applied to the selectable item
	 * @param Lift_WP_Query $lift_query
	 * @return array of items
	 */
	abstract protected function getControlItems( $lift_query );
}

class LiftSingleSelectFilter extends aLiftFormFilter {

	/**
	 * Array of items to show as options for the filter.  Each item should be an
	 * array of WP_Query formatted query_vars.
	 * @var array
	 */
	protected $item_values;
	private $_filtered_item_values;

	/**
	 *
	 * @param LiftField $field
	 * @param string $label
	 * @param array $item_values  Array of items to show as options for the filter.  Each item should be an
	 * array of WP_Query formatted query_vars.
	 * @param array $args
	 */
	public function __construct( $field, $label, $item_values = array( ), $args = array( ) ) {
		parent::__construct( $field, $label, $args );
		$this->item_values = $item_values;
	}

	protected function getItems() {
		if ( is_null( $this->_filtered_item_values ) ) {
			$this->_filtered_item_values = apply_filters( 'lift_filter_items_' . $this->field->getName(), $this->item_values );
		}
		return $this->_filtered_item_values;
	}

	/**
	 * Returns an array of selectable filter items
	 * @param Lift_WP_Query $lift_query
	 * @return array
	 */
	protected function getControlItems( $lift_query ) {
		$items = array( );

		$items[] = $allItem = ( object ) array(
				'selected' => false,
				'value' => $this->field->bqToRequest( '' ),
				'label' => $this->field->wpToLabel( array( ) )
		);

		$selectedFound = false;
		foreach ( $this->getItems() as $wp_vars ) {
			$bq = $this->field->wpToBooleanQuery( $wp_vars );
			$facet_request_vars = $this->field->bqToRequest( $bq );
			//determine if this item is selected by comparing the relative wp vars to this query
			$selected = 0 === count( array_diff_semi_assoc_recursive( $wp_vars, $lift_query->wp_query->query_vars ) );
			if ( $selected ) {
				$selectedFound = true;
			}

			$label = $this->field->wpToLabel( $wp_vars );
			$item = ( object ) array(
					'selected' => $selected,
					'value' => $facet_request_vars,
					'label' => $label
			);
			$items[] = $item;
		}
		if ( !$selectedFound ) {
			$selectedBq = $this->field->wpToBooleanQuery( $lift_query->wp_query->query_vars );
			$selectedRequest = $this->field->bqToRequest( $selectedBq );
			if ( $selectedRequest !== $allItem->value ) {
				$items[] = ( object ) array(
						'selected' => true,
						'value' => $this->field->bqToRequest( $selectedBq ),
						'label' => $this->field->wpToLabel( $lift_query->wp_query->query_vars )
				);
			} else {
				//since there was no bq, we know the all/any item is selected
				$allItem->selected = true;
			}
		}
		return $items;
	}

}

class LiftUnionSelectFilter extends LiftSingleSelectFilter {

	/**
	 *
	 * @param LiftField $field
	 * @param string $label
	 * @param array $item_values  Array of items to show as options for the filter.  Each item should be an
	 * array of WP_Query formatted query_vars.
	 * @param array $args
	 */
	public function __construct( $field, $label, $item_values = array( ), $args = array( ) ) {
		$args = wp_parse_args( $args, array(
			'control' => 'LiftMultiSelectControl'
			) );
		parent::__construct( $field, $label, $item_values, $args );
	}

}

class LiftIntersectFilter extends LiftUnionSelectFilter {

	/**
	 * Array of items to show as options for the filter.  Each item should be an
	 * array of WP_Query formatted query_vars.
	 * @var array
	 */
	protected $item_values;

	/**
	 *
	 * @param LiftField $field
	 * @param string $label
	 * @param array $item_values  Array of items to show as options for the filter.  Each item should be an
	 * array of WP_Query formatted query_vars.
	 * @param array $args
	 */
	public function __construct( $field, $label, $item_values = array( ), $args = array( ) ) {
		parent::__construct( $field, $label, $item_values, $args );
		add_action( 'get_cs_query', array( $this, 'setFacetOptions' ) );
	}

	/**
	 * Adds the facet data to the query
	 * @param Cloud_Search_Query $cs_query
	 */
	public function setFacetOptions( $cs_query ) {
		$facets = array( );
		$cs_query->add_facet( $this->field->getName() );
	}

	/**
	 * Returns an array of selectable filter items
	 * @param Lift_WP_Query $lift_query
	 * @return array
	 */
	protected function getControlItems( $lift_query ) {
		$facets = $lift_query->get_facets();
		if ( empty( $facets[$this->field->getName()] ) )
			return array( );

		$my_facets = $facets[$this->field->getName()];

		$items = array( );

		$selectedFound = false;

		$current_request = $this->field->bqToRequest( $this->field->wpToBooleanQuery( $lift_query->wp_query->query_vars ) );

		//$current_request = array_map( 'arrayify', $current_request );
		foreach ( $my_facets as $bq_value => $count ) {
			$facet_request_vars = $this->field->bqToRequest( $this->field->getName() . ':' . $bq_value );
			$facet_wp_vars = $this->field->requestToWP( $facet_request_vars );

			//determine if this item is selected by comparing the relative request vars to this query
			//we're assuming that these don't go further than 1 level deep
			$selected = 0 === count( array_diff_semi_assoc_recursive( $facet_request_vars, $current_request ) );
			if ( $selected ) {
				$selectedFound = true;
			}

			$label = $this->field->wpToLabel( $facet_wp_vars );
			if ( $count ) {
				$label = sprintf( '%1$s (%2$d)', $label, $count );
			}
			$item = ( object ) array(
					'selected' => $selected,
					'value' => $facet_request_vars,
					'label' => $label
			);
			$items[] = $item;
		}

		if ( !$selectedFound ) {
			$selectedBq = $this->field->wpToBooleanQuery( $lift_query->wp_query->query_vars );
			if ( $selectedBq ) {
				$items[] = ( object ) array(
						'selected' => true,
						'value' => $this->field->bqToRequest( $selectedBq ),
						'label' => sprintf( '%1$s (%2$d)', $this->field->wpToLabel( $lift_query->wp_query->query_vars ), $lift_query->wp_query->found_posts )
				);
			}
		}
		return $items;
	}

}
