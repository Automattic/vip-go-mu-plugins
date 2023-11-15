<?php

namespace Automattic\VIP\Search;

class BlockIndex {
	private static $instance;

	/**
	 * Initialize the VIP Block Index
	 */
	public function init() {
		$this->setup_hooks();
	}

	public static function instance() {
		if ( ! ( static::$instance instanceof BlockIndex ) ) {
			static::$instance = new BlockIndex();
			static::$instance->init();
		}

		return static::$instance;
	}

	protected function setup_hooks() {
		// Add block type counts to index
		add_filter( 'ep_prepare_meta_data', array( $this, 'add_block_index_meta' ), 10, 2 );

		// Allow block index meta key
		add_filter( 'vip_search_post_meta_allow_list', array( $this, 'allow_block_index_meta' ), 10, 2 );
	}

	public function allow_block_index_meta( $allow, $post ) {
		$is_indexed = apply_filters( 'vip_search_block_index_post_type', true, $post->post_type );

		if ( $is_indexed ) {
			$allow['vip_block_index_counts'] = true;
		}

		return $allow;
	}

	/**
	 * Filter for reducing post meta for indexing to only the allow list
	 */
	public function add_block_index_meta( $current_meta, $post ) {
		$current_meta['vip_block_index_counts'] = $this->get_block_type_counts( $post->post_content );

		return $current_meta;
	}

	public function get_block_type_counts( $post_content ) {
		$post_length       = strlen( $post_content );
		$post_length_limit = apply_filters( 'vip_search_block_index_post_length_limit', 100000 );

		if ( $post_length > $post_length_limit ) {
			$blocks = parse_blocks( mb_substr( $post_content, 0, $post_length_limit ) );
		} else {
			$blocks = parse_blocks( $post_content );
		}

		$block_types = $this->get_block_type_list( $blocks );
		foreach ( array_count_values( $block_types ) as $key => $value ) {
			$block_array[ $key ] = $value;
		}

		return $block_array;
	}

	/**
	* Returns a list of block names from the given block tree.
	*
	* @since 2022-04-01
	*
	* @param array $tree An array of blocks which might contain inner blocks, as
	* returned by the parser.
	*
	* @return array An array with the names of blocks, in the order in which they
	* appear.
	*/
	protected function get_block_type_list( $tree ) {
		$list = $this->flatten_blocks_with_refs( $tree );
		return array_reduce(
			$list,
			function ( $result, $block ) {
				if ( null === $block['blockName'] && empty( trim( $block['innerHTML'] ) ) ) {
					// Freeform content is parsed as a block with no name.
					// We'll interpret that as a freeform (classic) block, as
					// long as the HTML isn't just whitespace.
					return $result;
				}
				$block_name = $block['blockName'] ? $block['blockName'] : 'core/freeform';
				array_push( $result, $block_name );
				return $result;
			},
			[]
		);
	}

	/**
	* Flattens a tree of nested blocks into a list according to the order in which
	* they appear and resolves the content of reusable blocks.
	* When encountering reusable blocks, returns both the reusable block as well
	* as the content. Thus if the reusable block contains 2 blocks, a total of 3
	* blocks will be returned
	*
	* @since 2022-04-01
	*
	* @param array $tree An array of blocks which might contain inner blocks, as
	* returned by the parser.
	* @param int $max_blocks Don't try to parse more than this many blocks - likely will result in OOM out of memory
	*
	* @return array ( blocks, ref_ids) A flat array of blocks in the order they appear and also an array of ids referring to reusable blocks
	*/
	protected function flatten_blocks_with_refs( $tree, $max_blocks = 10000 ) {
		$result = [];

		$stack       = $tree;
		$block_count = 0;
		while ( ! empty( $stack ) ) {
			$block = array_shift( $stack );
			if ( ! $block['blockName'] && empty( $block['innerHTML'] ) ) {
				// sometimes we get empty blocks. Ignore them
				continue;
			}

			$inner_blocks = $block['innerBlocks'];
			unset( $block['innerBlocks'] );

			if ( ! empty( $inner_blocks ) ) {
				$stack = array_merge( $inner_blocks, $stack );
			}

			++$block_count;
			if ( $block_count > $max_blocks ) {
				break;
			}
			array_push( $result, $block );
		}
		return $result;
	}
}
