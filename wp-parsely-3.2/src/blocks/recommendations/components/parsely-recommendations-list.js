/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ParselyRecommendationsListItem from './parsely-recommendations-list-item';

const ParselyRecommendationsList = ( { imagestyle, recommendations, showimages } ) => (
	<ul className="parsely-recommendations-list">
		{ recommendations.map( ( recommendation, index ) => (
			<ParselyRecommendationsListItem
				imagestyle={ imagestyle }
				imageAlt={ __( 'Image for link', 'wp-parsely' ) }
				key={ index }
				recommendation={ recommendation }
				showimages={ showimages }
			/>
		) ) }
	</ul>
);

export default ParselyRecommendationsList;
