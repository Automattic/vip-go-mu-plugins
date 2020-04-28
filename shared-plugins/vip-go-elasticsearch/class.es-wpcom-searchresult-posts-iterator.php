<?php

/**
 * Provide a custom Iterator for seamlessly switching to the appropriate blog and inflating posts for
 * search results that may or many not live on other sites.
 *
 * Provides lazy loading of posts (by id) and transparent blog contetx switching for iterating a ES result set
 */
class ES_WPCOM_SearchResult_Posts_Iterator implements SeekableIterator, Countable, ArrayAccess {
	/**
	 * The ES search result to retrieve posts for
	 *
	 * @var array
	 */
	protected $search_result;

	/**
	 * An array of inflated posts represented in the $search_result
	 *
	 * @var array
	 */
	protected $posts;

	/**
	 * The current offset
	 *
	 * @var int
	 */
	protected $pointer = 0;

	/**
	 * Retrieve the ES search result
	 *
	 * @return array The ES search result
	 */
	public function get_search_result() {
		return $this->search_result;
	}

	/**
	 * Set the ES search result
	 *
	 * @param array $search_result The ES search result to associate with this iterator
	 */
	public function set_search_result( array $search_result ) {
		$this->search_result = $search_result;

		return $this;
	}

	/**
	 * Retrieve a post from the database by id, and conditionally switch to the appropriate blog, if needed
	 *
	 * @param  array 	$es_result The individual hit in an ES search result to inflate a post for
	 * @return WP_Post 	The inflated WP_Post object, or null if not found
	 */
	protected function inflate_post( $es_result ) {
		if ( isset( $es_result['fields']['blog_id'] ) && get_current_blog_id() !== $es_result['fields']['blog_id'] )
			switch_to_blog( $es_result['fields']['blog_id'] );

		$post = get_post( $es_result['fields']['post_id'] );

		return $post;
	}

	// Implement SeekableIterator

	public function seek( $position ) {
		$this->pointer = $position;
	}

	public function current () {
		return $this->pointer;
	}

	public function key () {
		return $this->pointer;
	}

	public function next() {
		$this->pointer++;
	}

	public function rewind() {
		$this->pointer = 0;
	}

	public function valid() {
		return $this->offsetExists( $this->pointer );
	}

	// Implement Countable

	public function count() {
		return count( $this->search_result['results']['hits'] );
	}

	// Implement ArrayAccess

	public function offsetExists( $index ) {
		return isset( $this->search_result['results']['hits'][ $index ] );
	}

	public function offsetGet( $index ) {
		if ( ! $this->offsetExists( $index ) )
			return null;

		// Lazy load the post
		if ( ! isset( $this->posts[ $index ] ) )
			$this->posts[ $index ] = $this->inflate_post( $this->search_result['results']['hits'][ $index ] );

		return $this->posts[ $index ];
	}

	public function offsetSet( $index, $value ) {
		$this->posts[ $index ] = $value;
	}

	public function offsetUnset( $index ) {
		unset( $this->posts[ $index ] );
	}
}