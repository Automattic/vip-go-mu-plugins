/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { useDebounce } from '@wordpress/compose';
import { useCallback, useEffect } from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { setError, setRecommendations } from '../actions';
import { useRecommendationsStore } from '../recommendations-store';

const updateDelay = 300; // The Block's update delay in the Block Editor when settings/props change.

const ParselyRecommendationsFetcher = ( { boost, limit, sort } ) => {
	const {	dispatch } = useRecommendationsStore();

	const query = {
		boost,
		limit,
		sort,
		url: window.location.href,
	};

	async function fetchRecommendationsFromWpApi() {
		return apiFetch( {
			path: addQueryArgs( '/wp-parsely/v1/related', { query } ),
		} );
	}

	async function fetchRecommendations() {
		let response;
		let errorMessage;

		try {
			response = await fetchRecommendationsFromWpApi();
		} catch ( wpError ) {
			errorMessage = wpError;
		}

		if ( response?.error ) {
			errorMessage = response.error;
		}

		if ( errorMessage ) {
			dispatch( setError( { error: response.error } ) );
			return;
		}

		const data = response?.data || [];
		dispatch( setRecommendations( { recommendations: data } ) );
	}

	const apiMemoProps = [ ...Object.values( query ) ];
	const updateRecommendationsWhenPropsChange = useCallback( fetchRecommendations, apiMemoProps );
	const debouncedUpdate = useDebounce( updateRecommendationsWhenPropsChange, updateDelay );

	/**
	 * Fetch recommendations:
	 * - On component mount
	 * - When an attribute changes that affects the API call.
	 *   (This happens in the Editor context when someone changes a setting.)
	 */
	useEffect( debouncedUpdate, apiMemoProps );

	// This is a data-only component and does not render
	return null;
};

export default ParselyRecommendationsFetcher;
