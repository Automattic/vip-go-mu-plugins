<?php

class Cloud_Schemas {

	public static function GetSchema( ) {
		return array(
			array(
				'field_name' => 'id',
				'field_type' => 'uint',
			),
			array(
				'field_name' => 'site_id',
				'field_type' => 'uint',
			),
			array(
				'field_name' => 'blog_id',
				'field_type' => 'uint',
			),
			array(
				'field_name' => 'post_author',
				'field_type' => 'uint',
			),
			array(
				'field_name' => 'post_author_name',
				'field_type' => 'text',
				'options' => array(
					'result' => 'true'
				),
			),
			array(
				'field_name' => 'taxonomy_category_id',
				'field_type' => 'literal',
				'options' => array(
					'facet' => 'true'
				),
			),
			array(
				'field_name' => 'taxonomy_category_label',
				'field_type' => 'text',
			),
			array(
				'field_name' => 'post_content',
				'field_type' => 'text',
			),
			array(
				'field_name' => 'post_date_gmt',
				'field_type' => 'uint',
			),
			array(
				'field_name' => 'post_status',
				'field_type' => 'literal',
				'options' => array(
					'facet' => 'true',
				),
			),
			array(
				'field_name' => 'post_title',
				'field_type' => 'text',
				'options' => array(
					'result' => 'true',
				),
			),
			array(
				'field_name' => 'post_type',
				'field_type' => 'literal',
				'options' => array(
					'facet' => 'true',
					'search' => 'true',
				),
			),
			array(
				'field_name' => 'taxonomy_post_tag_id',
				'field_type' => 'literal',
				'options' => array(
					'facet' => 'true',
				),
			),
			array(
				'field_name' => 'taxonomy_post_tag_label',
				'field_type' => 'text',
			),
		);
	}

}