import { RECOMMENDATIONS_BLOCK_ERROR, RECOMMENDATIONS_BLOCK_LOADED, RECOMMENDATIONS_BLOCK_RECOMMENDATIONS } from './constants';

export const setError = ( { error } ) => ( {
	type: RECOMMENDATIONS_BLOCK_ERROR,
	error,
} );

export const setRecommendations = ( { recommendations } ) => ( {
	type: RECOMMENDATIONS_BLOCK_RECOMMENDATIONS,
	recommendations,
} );

export const setLoaded = () => ( {
	type: RECOMMENDATIONS_BLOCK_LOADED,
} );
