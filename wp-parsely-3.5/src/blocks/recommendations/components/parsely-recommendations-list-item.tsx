/**
 * External dependencies
 */
import { Card, CardBody, CardMedia } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { Recommendation } from '../models/Recommendation';

interface ParselyRecommendationsListItemProps {
	imageAlt: string;
	imagestyle: string;
	openlinksinnewtab: boolean;
	recommendation: Recommendation;
	showimages: boolean;
}

const getImageForLink = ( imagestyle: string, imageUrl: string, thumbUrlMedium: string ) => imagestyle === 'original' ? imageUrl : thumbUrlMedium;
const getLinkTarget = ( openlinksinnewtab: boolean ) => Boolean( openlinksinnewtab ) === true ? { target: '_blank', rel: 'noopener' } : { target: '_self', rel: '' };

const ParselyRecommendationsListItem = ( {
	imageAlt,
	imagestyle,
	openlinksinnewtab,
	recommendation: {
		title: linkTitle,
		url: linkUrl,
		image_url: imageUrl,
		thumb_url_medium: thumbUrlMedium,
	},
	showimages,
} : ParselyRecommendationsListItemProps ) => (
	<li>
		<a href={ linkUrl } className="parsely-recommendations-link" { ... getLinkTarget( openlinksinnewtab ) } >
			<Card className="parsely-recommendations-card">
				{ showimages && (
					<CardMedia className="parsely-recommendations-cardmedia">
						<img
							className="parsely-recommendations-image"
							src={ getImageForLink( imagestyle, imageUrl, thumbUrlMedium ) }
							alt={ imageAlt }
						/>
					</CardMedia>
				) }
				<CardBody className="parsely-recommendations-cardbody">{ linkTitle }</CardBody>
			</Card>
		</a>
	</li>
);

export default ParselyRecommendationsListItem;
