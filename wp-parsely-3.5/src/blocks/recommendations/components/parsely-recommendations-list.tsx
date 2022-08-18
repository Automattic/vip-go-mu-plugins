/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ParselyRecommendationsListItem from './parsely-recommendations-list-item';
import { Recommendation } from '../models/Recommendation';

interface ParselyRecommendationsListProps {
	imagestyle: string;
	openlinksinnewtab: boolean;
	recommendations: Recommendation[];
	showimages: boolean;
}

const ParselyRecommendationsList = ( { imagestyle, recommendations, showimages, openlinksinnewtab }: ParselyRecommendationsListProps ) => (
	<ul className="parsely-recommendations-list">
		{ recommendations.map( ( recommendation, index ) => (
			<ParselyRecommendationsListItem
				imageAlt={ __( 'Image for link', 'wp-parsely' ) }
				imagestyle={ imagestyle }
				key={ index }
				openlinksinnewtab={ openlinksinnewtab }
				recommendation={ recommendation }
				showimages={ showimages }
			/>
		) ) }
	</ul>
);

export default ParselyRecommendationsList;
