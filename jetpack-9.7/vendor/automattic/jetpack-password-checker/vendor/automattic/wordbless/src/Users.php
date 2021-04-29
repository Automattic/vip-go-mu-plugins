<?php

namespace WorDBless;

class Users {

	use Singleton, ClearCacheGroup;

	public $users       = array();
	public $cache_group = 'users';

	private function __construct() {
		require_once ABSPATH . 'wp-admin/includes/schema.php';
		populate_roles();

		add_filter( 'wordbless_wpdb_insert', array( $this, 'insert' ), 10, 4 );
		add_filter( 'wordbless_wpdb_update', array( $this, 'update' ), 10, 6 );

		add_filter( 'wordbless_wpdb_query_results', array( $this, 'filter_query' ), 10, 2 );

		add_action( 'delete_user', array( $this, 'delete' ), 10, 2 );
	}

	/**
	 * Filters the results from $wpdb->insert.
	 *
	 * This filter will handle the call for insert() done from wp_insert_post
	 *
	 */
	public function insert( $result, $table, $data, $format ) {
		global $wpdb;

		if ( $wpdb->users !== $table ) {
			return $result;
		}

		$new_id          = InsertId::bump_and_get();
		$wpdb->insert_id = $new_id;
		$data['ID']      = $new_id;

		$this->users[ $new_id ] = $data;

		return true;
	}

	/**
	 * Filters the results from $wpdb->update.
	 *
	 * This filter will handle the call for insert() done from wp_insert_post
	 *
	 */
	public function update( $result, $table, $data, $where, $format, $where_format ) {
		global $wpdb;

		if ( $wpdb->users !== $table ) {
			return $result;
		}

		if ( is_array( $where ) && 1 === count( $where ) && isset( $where['ID'] ) ) {
			if ( isset( $this->users[ $where['ID'] ] ) ) {
				$result                      = 1;
				$this->users[ $where['ID'] ] = array_merge(
					$this->users[ $where['ID'] ],
					$data
				);
			}
		}

		return $result;

	}

	public function delete( $id, $reassign ) {

		if ( $reassign ) {
			Posts::init()->transfer_posts_authorship( $id, $reassign );
		} else {
			Posts::init()->clear_all_posts_from_author( $id );
		}

		UserMeta::init()->clear_all_meta_for_object( $id );

		unset( $this->users[ $id ] );

	}

	/**
	 * Filters the Query used in WP_User::get_data_by
	 *
	 * @param array  $query_results
	 * @param string $query
	 * @return array
	 */
	public function filter_query( $query_results, $query ) {
		global $wpdb;
		$pattern = '/^SELECT \* FROM ' . preg_quote( $wpdb->users ) . ' WHERE (ID|user_nicename|user_email|user_login) = \'([^ ]+)\' LIMIT 1$/';
		if ( 1 === preg_match( $pattern, $query, $matches ) ) {
			$field = $matches[1];
			$value = $matches[2];

			$query_results = array( $this->get_user_by( $field, $value ) );
		}

		return $query_results;
	}

	public function get_user_by( $field, $value ) {
		$user = false;
		if ( 'ID' === $field && isset( $this->users[ $value ] ) ) {
			$user = (object) $this->users[ $value ];
		} elseif ( 'ID' !== $field ) {
			$filtered = array_filter(
				$this->users,
				function( $user ) use ( $field, $value ) {
					return isset( $user[ $field ] ) && $user[ $field ] === $value;
				}
			);

			if ( ! empty( $filtered ) ) {
				$user = (object) current( $filtered );
			}
		}
		return $user;
	}

	public function clear_all_users() {
		$this->clear_cache_group();
		$this->users = array();
	}

}
