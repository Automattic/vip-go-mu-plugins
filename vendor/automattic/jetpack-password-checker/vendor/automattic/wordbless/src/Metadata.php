<?php

namespace WorDBless;

class Metadata {

	public $meta = array();

	public $meta_type;

	public function __construct( $meta_type ) {

		$this->meta_type = $meta_type;

		add_filter( "add_{$this->meta_type}_metadata", array( $this, 'add' ), 10, 5 );

		add_filter( "get_{$this->meta_type}_metadata", array( $this, 'get' ), 10, 4 );
		add_filter( "get_{$this->meta_type}_metadata_by_mid", array( $this, 'get_by_mid' ), 10, 2 );

		add_filter( "delete_{$this->meta_type}_metadata", array( $this, 'delete' ), 10, 5 );
		add_filter( "delete_{$this->meta_type}_metadata_by_mid", array( $this, 'delete_by_mid' ), 10, 2 );

		add_filter( "update_{$this->meta_type}_metadata", array( $this, 'update' ), 10, 5 );
		add_filter( "update_{$this->meta_type}_metadata_by_mid", array( $this, 'update_by_mid' ), 10, 4 );

	}

	public function key_exists_for_object( $meta_key, $object_id ) {
		if ( ! isset( $this->meta[ $object_id ] ) ) {
			return false;
		}

		foreach ( $this->meta[ $object_id ] as $meta ) {
			if ( isset( $meta['meta_key'] ) && $meta_key === $meta['meta_key'] ) {
				return true;
			}
		}

		return false;

	}

	public function add( $check, $object_id, $meta_key, $meta_value, $unique ) {

		if ( $unique && $this->key_exists_for_object( $meta_key, $object_id ) ) {
			return false;
		}

		$_meta_value = $meta_value;
		$meta_value  = maybe_serialize( $meta_value );

		if ( ! isset( $this->meta[ $object_id ] ) ) {
			$this->meta[ $object_id ] = array();
		}

		do_action( "add_{$this->meta_type}_meta", $object_id, $meta_key, $_meta_value );

		$mid = InsertId::bump_and_get();

		$this->meta[ $object_id ][] = array(
			'mid'        => $mid,
			'meta_key'   => $meta_key,
			'meta_value' => $meta_value,
		);

		do_action( "added_{$this->meta_type}_meta", $mid, $object_id, $meta_key, $_meta_value );

		return $mid;

	}

	public function get( $check, $object_id, $meta_key, $single ) {
		$check = array();
		if ( isset( $this->meta[ $object_id ] ) ) {
			foreach ( $this->meta[ $object_id ] as $meta ) {
				if ( isset( $meta['meta_key'] ) && $meta_key === $meta['meta_key'] ) {
					$check[] = maybe_unserialize( $meta['meta_value'] );
					if ( $single ) {
						break;
					}
				}
			}
		}
		if ( empty( $check ) && $single ) {
			$check = array( '' ); // Ensure an empty string is returned when meta is not found.
		}
		return $check;
	}

	protected function find_by_mid( $mid ) {

		foreach ( $this->meta as $object_id => $object_meta ) {
			foreach ( $object_meta as $index => $meta ) {
				if ( isset( $meta['mid'] ) && $mid === $meta['mid'] ) {
					return array(
						'object_id' => $object_id,
						'index'     => $index,
						'key'       => $meta['meta_key'],
						'value'     => $meta['meta_value'],
					);
				}
			}
		}

		return false;

	}

	public function get_by_mid( $check, $mid ) {
		$check = false;
		$meta  = $this->find_by_mid( $mid );
		if ( $meta ) {
			$check = maybe_unserialize( $meta['value'] );
		}
		return $check;
	}

	public function delete( $check, $object_id, $meta_key, $meta_value, $delete_all ) {

		$object_ids = $delete_all ? array_keys( $this->meta ) : array( $object_id );
		$check      = false;

		foreach ( $object_ids as $id ) {
			if ( $this->delete_for_object( $id, $meta_key, $meta_value ) ) {
				$check = true;
			}
		}

		return $check;

	}

	public function delete_for_object( $object_id, $meta_key, $meta_value ) {
		if ( ! isset( $this->meta[ $object_id ] ) ) {
			return false;
		}

		$meta_value          = maybe_serialize( $meta_value );
		$consider_meta_value = '' !== $meta_value && null !== $meta_value && false !== $meta_value;
		$found               = false;

		foreach ( $this->meta[ $object_id ] as $index => $meta ) {
			if (
				isset( $meta['meta_key'] ) &&
				$meta_key === $meta['meta_key'] &&
				(
					! $consider_meta_value ||
					$meta_value === $meta['meta_value']
				)
			) {
				unset( $this->meta[ $object_id ][ $index ] );
				$found = true;
			}
		}

		$this->meta[ $object_id ] = array_values( $this->meta[ $object_id ] );

		return $found;

	}

	public function delete_by_mid( $check, $mid ) {
		$check = false;
		$meta  = $this->find_by_mid( $mid );
		if ( $meta ) {
			unset( $this->meta[ $meta['object_id'] ][ $meta['index'] ] );
			$this->meta[ $meta['object_id'] ] = array_values( $this->meta[ $meta['object_id'] ] );
			$check                            = true;
		}
		return $check;
	}

	public function update( $check, $object_id, $meta_key, $meta_value, $prev_value ) {

		if ( ! $this->key_exists_for_object( $meta_key, $object_id ) ) {
			// todo: in the original method, raw values are passade to add_metadata. The values below have been through wp_unslash.
			return add_metadata( $this->meta_type, $object_id, $meta_key, $meta_value );
		}

		// Compare existing value to new value if no prev value given and the key exists only once.
		if ( empty( $prev_value ) ) {
			$old_value = get_metadata( $this->meta_type, $object_id, $meta_key );
			if ( 1 === count( $old_value ) ) {
				if ( $old_value[0] === $meta_value ) {
					return false;
				}
			}
		}

		$_meta_value = $meta_value;
		$meta_value  = maybe_serialize( $meta_value );

		$consider_meta_value = '' !== $prev_value && null !== $prev_value && false !== $prev_value;

		$check = false;

		foreach ( $this->meta[ $object_id ] as $index => $meta ) {
			if (
				isset( $meta['meta_key'] ) &&
				$meta_key === $meta['meta_key'] &&
				(
					! $consider_meta_value ||
					$prev_value === $meta['meta_value']
				)
			) {

				do_action( "update_{$this->meta_type}_meta", $meta['mid'], $object_id, $meta_key, $_meta_value );
				if ( 'post' === $this->meta_type ) {
					do_action( 'update_postmeta', $meta['mid'], $object_id, $meta_key, $meta_value );
				}

				$this->meta[ $object_id ][ $index ]['meta_value'] = $meta_value;

				do_action( "updated_{$this->meta_type}_meta", $meta['mid'], $object_id, $meta_key, $_meta_value );
				if ( 'post' === $this->meta_type ) {
					do_action( 'updated_postmeta', $meta['mid'], $object_id, $meta_key, $meta_value );
				}

				$check = true;
			}
		}

		return $check;

	}

	public function update_by_mid( $check, $mid, $meta_value, $meta_key ) {
		$check = false;
		$meta  = $this->find_by_mid( $mid );
		if ( $meta ) {

			$meta_subtype = get_object_subtype( $this->meta_type, $meta['object_id'] );
			$_meta_value  = $meta_value;
			$meta_value   = sanitize_meta( $meta_key, $meta_value, $this->meta_type, $meta_subtype );
			$meta_value   = maybe_serialize( $meta_value );
			$new_key      = false === $meta_key ? $meta['key'] : $meta_key;

			/** This action is documented in wp-includes/meta.php */
			do_action( "update_{$this->meta_type}_meta", $mid, $meta['object_id'], $meta_key, $_meta_value );

			if ( 'post' === $this->meta_type ) {
				/** This action is documented in wp-includes/meta.php */
				do_action( 'update_postmeta', $mid, $meta['object_id'], $meta_key, $meta_value );
			}

			$this->meta[ $meta['object_id'] ][ $meta['index'] ] = array(
				'mid'        => $mid,
				'meta_key'   => $new_key,
				'meta_value' => $meta_value,
			);

			/** This action is documented in wp-includes/meta.php */
			do_action( "updated_{$this->meta_type}_meta", $mid, $meta['object_id'], $meta_key, $_meta_value );

			if ( 'post' === $this->meta_type ) {
				/** This action is documented in wp-includes/meta.php */
				do_action( 'updated_postmeta', $mid, $meta['object_id'], $meta_key, $meta_value );
			}

			$check = true;
		}
		return $check;
	}

	public function clear_all_meta() {
		$this->meta = array();
	}

	public function clear_all_meta_for_object( $object_id ) {
		if ( isset( $this->meta[ $object_id ] ) ) {
			unset( $this->meta[ $object_id ] );
		}
	}

}
