import { RECOMMENDATIONS_BLOCK_ERROR, RECOMMENDATIONS_BLOCK_LOADED, RECOMMENDATIONS_BLOCK_RECOMMENDATIONS } from './constants';
import { Recommendation } from './models/Recommendation';

interface SetErrorPayload {
	error: string;
}

interface SetRecommendationsPayload {
	recommendations: Recommendation[];
}

export const setError = ( { error }: SetErrorPayload ) => ( {
	type: RECOMMENDATIONS_BLOCK_ERROR,
	error,
} );

export const setRecommendations = ( { recommendations }: SetRecommendationsPayload ) => ( {
	type: RECOMMENDATIONS_BLOCK_RECOMMENDATIONS,
	recommendations,
} );

export const setLoaded = () => ( {
	type: RECOMMENDATIONS_BLOCK_LOADED,
} );
