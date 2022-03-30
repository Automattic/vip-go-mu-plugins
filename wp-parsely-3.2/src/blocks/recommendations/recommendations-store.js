/**
 * External dependencies
 */
import { createContext, useContext, useReducer } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { RECOMMENDATIONS_BLOCK_ERROR, RECOMMENDATIONS_BLOCK_LOADED, RECOMMENDATIONS_BLOCK_RECOMMENDATIONS } from './constants';

const RecommendationsContext = createContext();

const reducer = ( state, action ) => {
	switch ( action.type ) {
		case RECOMMENDATIONS_BLOCK_ERROR:
			return { ...state, isLoaded: true, error: action.error, recommendations: undefined };
		case RECOMMENDATIONS_BLOCK_LOADED:
			return { ...state, isLoaded: true };
		case RECOMMENDATIONS_BLOCK_RECOMMENDATIONS: {
			const { recommendations } = action;
			if ( ! Array.isArray( recommendations ) ) {
				return { ...state, recommendations: undefined };
			}
			const validRecommendations = recommendations.map(
				// eslint-disable-next-line camelcase
				( { title, url, image_url, thumb_url_medium } ) => ( {
					title,
					url,
					image_url, // eslint-disable-line camelcase
					thumb_url_medium, // eslint-disable-line camelcase
				} )
			);
			return { ...state, isLoaded: true, error: undefined, recommendations: validRecommendations };
		}
		default:
			return { ...state };
	}
};

const RecommendationsStore = ( props ) => {
	const defaultState = {
		isLoaded: false,
		recommendations: undefined,
		uuid: window.PARSELY?.config?.uuid,
		clientId: props.clientId,
	};

	const [ state, dispatch ] = useReducer( reducer, defaultState );
	return <RecommendationsContext.Provider value={ { state, dispatch } } { ...props } />;
};

export const useRecommendationsStore = () => useContext( RecommendationsContext );

export default RecommendationsStore;
