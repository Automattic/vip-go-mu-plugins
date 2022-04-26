/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ParselyRecommendationsFetcher from './parsely-recommendations-fetcher';
import ParselyRecommendationsList from './parsely-recommendations-list';
import ParselyRecommendationsTitle from './parsely-recommendations-title';
import { useRecommendationsStore } from '../recommendations-store';

export default function ParselyRecommendations( {
	boost,
	limit,
	imagestyle,
	isEditMode,
	personalized,
	showimages,
	sort,
	title,
} ) {
	const {
		state: { error, isLoaded, recommendations },
	} = useRecommendationsStore();

	// Show error messages within the WordPress Block Editor when needed.
	let errorMessage;
	if ( isLoaded && isEditMode ) {
		if ( error ) {
			errorMessage = __( 'Parse.ly API replied with error: ', 'wp-parsely' ) + JSON.stringify( error );
		} else if ( Array.isArray( recommendations ) && ! recommendations?.length ) {
			errorMessage = __( 'No recommendations found.', 'wp-parsely' );
		}
	}

	return (
		<>
			<ParselyRecommendationsFetcher
				boost={ boost }
				limit={ limit }
				personalized={ personalized }
				sort={ sort }
			/>
			{ ! isLoaded && (
				<span className="parsely-recommendations-loading">{ __( 'Loading…', 'wp-parsely' ) }</span>
			) }
			{ errorMessage && (
				<span>{ errorMessage }</span>
			) }
			{ isLoaded && !! recommendations?.length && (
				<>
					<ParselyRecommendationsTitle title={ title } />
					<ParselyRecommendationsList
						imagestyle={ imagestyle }
						recommendations={ recommendations }
						showimages={ showimages }
					/>
				</>
			) }
		</>
	);
}
