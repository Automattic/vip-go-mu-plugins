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

interface ParselyRecommendationsProps {
	boost: string;
	imagestyle: string;
	isEditMode: boolean;
	limit: number;
	openlinksinnewtab: boolean;
	showimages: boolean;
	sort: string;
	title: string;
}

export default function ParselyRecommendations( {
	boost,
	imagestyle,
	isEditMode,
	limit,
	openlinksinnewtab,
	showimages,
	sort,
	title,
} : ParselyRecommendationsProps ) {
	const {
		state: { error, isLoaded, recommendations },
	} = useRecommendationsStore();

	function getErrorMessage() {
		let message = `${ __( 'Error:', 'wp-parsely' ) } ${ JSON.stringify( error ) }`;
		const httpError = message.includes( '"errors":{"http_request_failed"' ) ||
		( typeof error === 'object' && error?.code === 'fetch_error' );

		if ( httpError ) {
			message = __( 'The Parse.ly Recommendations API is not accessible. You may be offline.', 'wp-parsely' );
		} else if ( message.includes( '{"errors":{"403":["Forbidden"]},"error_data":[]}' ) ) {
			message = __( 'Access denied. Please verify that your Site ID is valid.', 'wp-parsely' );
		} else if ( typeof error === 'object' && error?.code === 'rest_no_route' ) {
			message = __( 'The REST route is unavailable. To use it, wp_parsely_enable_related_api_proxy should be true.', 'wp-parsely' );
		}

		return message;
	}

	// Show error messages within the WordPress Block Editor when needed.
	let errorMessage;
	if ( isLoaded && isEditMode ) {
		if ( error ) {
			errorMessage = getErrorMessage();
		} else if ( Array.isArray( recommendations ) && ! recommendations?.length ) {
			errorMessage = __( 'No recommendations found.', 'wp-parsely' );
		}
	}

	return (
		<>
			<ParselyRecommendationsFetcher
				boost={ boost }
				limit={ limit }
				sort={ sort }
				isEditMode={ isEditMode }
			/>
			{ ! isLoaded && (
				<span className="parsely-recommendations-loading">{ __( 'Loading…', 'wp-parsely' ) }</span>
			) }
			{ errorMessage && (
				<span className="parsely-recommendations-error">{ errorMessage }</span>
			) }
			{ isLoaded && !! recommendations?.length && (
				<>
					<ParselyRecommendationsTitle title={ title } />
					<ParselyRecommendationsList
						imagestyle={ imagestyle }
						openlinksinnewtab={ openlinksinnewtab }
						recommendations={ recommendations }
						showimages={ showimages }
					/>
				</>
			) }
		</>
	);
}
