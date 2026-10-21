<?php

namespace Automattic\VIP\Search;

use WP_Term;

final class SyncManager_Helper {
	private static $instance = null;

	/** @var WP_Term[] */
	private $terms = [];

	public static function instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->init();
	}

	public function init(): void {
		$this->terms = [];

		/*
		 * Hook sequence:
		 * - wp_update_term_parent
		 * - edit_terms <====== we hook into this one to get the term before it gets updated
		 * - wp_update_term_data
		 * - (database update happens here)
		 * - edited_terms
		 * - edit_term_taxonomy
		 * - (term-taxonomy relationship update happens here)
		 * - edited_term_taxonomy
		 * - edit_term
		 * - edit_{$taxonomy}
		 * - term_id_filter
		 * - (term cache gets cleaned here, get_term() will return the updated value)
		 * - edited_term 
		 * -    <====== we use this action to get the fully updated term (priority=0)
		 * -    <====== Queue::offload_term_indexing_to_queue() sends large terms to cron and calls skip_term_edit() (priority=5)
		 * -    <====== EP uses this hook to sync the term index (priority=10)
		 * - edited_{$taxonomy}
		 * - saved_term <====== we use this action to clean up after edit_terms
		 * - saved_{$taxonomy}
		 */

		add_action( 'edit_terms', [ $this, 'edit_terms' ], 10, 2 );
		add_action( 'edited_term', [ $this, 'edited_term' ], 0, 3 );
		add_action( 'saved_term', [ $this, 'saved_term' ], 10, 3 );

		add_filter( 'ep_skip_action_edited_term', [ $this, 'ep_skip_action_edited_term' ], 10, 4 );

		// Invalidate the request cache on these hooks

		add_action( 'ep_after_bulk_index', [ $this, 'ep_after_bulk_index' ], 10, 3 );
		add_action( 'ep_after_bulk_index_dynamically', [ $this, 'ep_after_bulk_index' ], 10, 3 );
		add_action( 'ep_after_index', [ $this, 'ep_after_index' ], 10, 2 );
	}

	public function cleanup(): void {
		$this->terms = [];

		remove_action( 'edit_terms', [ $this, 'edit_terms' ], 10 );
		remove_action( 'edited_term', [ $this, 'edited_term' ], 0 );
		remove_action( 'saved_term', [ $this, 'saved_term' ] );

		remove_filter( 'ep_skip_action_edited_term', [ $this, 'ep_skip_action_edited_term' ], 10 );
	}


	/**
	 * Bump the last_changed  after bulk index
	 *
	 * @param array $object_ids
	 * @param string $indexable_slug
	 * @param mixed $result
	 * @return void
	 */
	public function ep_after_bulk_index( $object_ids, $indexable_slug, $result ) {
		if ( ! is_wp_error( $result ) && $result ) {
			$this->bump_last_changed();
		}
	}

	/**
	 * Bump the last_changed after indexing single document
	 *
	 * @param array $document
	 * @param mixed $return
	 * @return void
	 */
	public function ep_after_index( $document, $return ) {
		if ( ! is_wp_error( $return ) && $return ) {
			$this->bump_last_changed();
		}
	}

	/**
	 * Bump last updated what we can use to invalidate any cached requests
	 *
	 * @return bool 
	 */
	private function bump_last_changed() {
		return wp_cache_set( 'last_changed', microtime(), Search::SEARCH_CACHE_GROUP );
	}

	/**
	 * Fires immediately before the given terms are edited.
	 *
	 * @param int $term_id      Term ID.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return void
	 */
	public function edit_terms( $term_id, $taxonomy ): void {
		$term = get_term( $term_id, $taxonomy );
		$key  = "{$term_id}:{$taxonomy}";

		if ( $term instanceof WP_Term ) {
			$this->terms[ $key ] = $term;
		}
	}

	/**
	 * Fires after a term has been updated, and the term cache has been cleaned.
	 *
	 * @param int $term_id      Term ID.
	 * @param int $tt_id        Term taxonomy ID.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return void
	 */
	public function edited_term( $term_id, $tt_id, $taxonomy ): void {
		$key = "{$term_id}:{$taxonomy}";
		if ( isset( $this->terms[ $key ] ) ) {
			$new = get_term( $term_id, $taxonomy );
			if ( $new instanceof WP_Term ) {
				$old = $this->terms[ $key ];
				foreach ( [ 'name', 'slug', 'parent', 'term_taxonomy_id' ] as $field ) {
					if ( $old->$field !== $new->$field ) {
						unset( $this->terms[ $key ] );
						break;
					}
				}
			}
		}
	}

	/**
	 * Fires after a term has been saved, and the term cache has been cleared.
	 *
	 * Only the saved term is cleared, so a term saved during another term's edit
	 * (e.g. a synced translation) doesn't drop the outer term's state.
	 * Called without arguments, it clears the state of every term.
	 *
	 * @param int|null $term_id     Term ID.
	 * @param int|null $tt_id       Term taxonomy ID.
	 * @param string|null $taxonomy Taxonomy slug.
	 * @return void
	 */
	public function saved_term( $term_id = null, $tt_id = null, $taxonomy = null ): void {
		if ( null === $term_id || null === $taxonomy ) {
			$this->terms = [];
			return;
		}

		unset( $this->terms[ "{$term_id}:{$taxonomy}" ] );
	}

	/**
	 * Skip ElasticPress's action_edited_term() for the rest of this term edit,
	 * e.g. when the term's posts are reindexed by the term update cron job instead
	 *
	 * @param WP_Term $term The term being edited
	 * @return void
	 */
	public function skip_term_edit( WP_Term $term ): void {
		$this->terms[ "{$term->term_id}:{$term->taxonomy}" ] = $term;
	}

	/**
	 * @param mixed $skip       Whether to skip the update
	 * @param int $term_id      Term ID.
	 * @param int $tt_id        Term taxonomy ID.
	 * @param string $taxonomy  Taxonomy slug.
	 * @return bool
	 */
	public function ep_skip_action_edited_term( $skip, $term_id, $tt_id, $taxonomy ) {
		$key = "{$term_id}:{$taxonomy}";
		if ( isset( $this->terms[ $key ] ) ) {
			$skip = true;
		}

		return $skip;
	}
}
