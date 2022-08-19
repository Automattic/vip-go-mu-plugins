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
import { Recommendation } from '../models/Recommendation';

interface ParselyRecommendationsFetcherProps {
	boost: string;
	limit: number;
	sort: string;
	isEditMode: boolean;
}

interface ApiResponse {
	error?: string;
	data?: Recommendation[];
}

const updateDelay = 300; // The Block's update delay in the Block Editor when settings/props change.

const ParselyRecommendationsFetcher = ( { boost, limit, sort, isEditMode } : ParselyRecommendationsFetcherProps ): JSX.Element => {
	const {	dispatch } = useRecommendationsStore();

	const query = {
		boost,
		limit,
		sort,
		url: window.location.href,
	};

	async function fetchRecommendationsFromWpApi(): Promise<ApiResponse> {
		return apiFetch( {
			path: addQueryArgs( '/wp-parsely/v1/related', { query } ),
		} );
	}

	async function fetchRecommendations() {
		let response;
		let error;

		try {
			response = await fetchRecommendationsFromWpApi();
		} catch ( wpError ) {
			error = wpError;
		}

		if ( response?.error ) {
			error = response.error;
		}

		if ( error ) {
			dispatch( setError( { error } ) );
			return;
		}

		let data = response?.data || [];

		// When in the editor, change URLs to # for better screen reader experience.
		if ( isEditMode ) {
			data = data.map( ( obj ) => {
				return { ...obj, url: '#' };
			} );
		}

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
	useEffect( () => {
		debouncedUpdate();
	}, apiMemoProps );

	// This is a data-only component and does not render
	return null;
};

export default ParselyRecommendationsFetcher;
