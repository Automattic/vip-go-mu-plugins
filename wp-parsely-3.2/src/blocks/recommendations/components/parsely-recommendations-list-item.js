/**
 * External dependencies
 */
import { Card, CardBody, CardMedia } from '@wordpress/components';

const getImageForLink = ( { imagestyle, imageUrl, thumbUrlMedium } ) => imagestyle === 'original' ? imageUrl : thumbUrlMedium;

const ParselyRecommendationsListItem = ( {
	imageAlt,
	imagestyle,
	recommendation: {
		title: linkTitle,
		url: linkUrl,
		image_url: imageUrl,
		thumb_url_medium: thumbUrlMedium,
	},
	showimages,
} ) => {
	const imageForLink = showimages && getImageForLink( { imagestyle, imageUrl, thumbUrlMedium } );

	return (
		<li>
			<a href={ linkUrl } className="parsely-recommendations-link">
				<Card className="parsely-recommendations-card" size="custom">
					{ imageForLink && (
						<CardMedia className="parsely-recommendations-cardmedia">
							<img
								className="parsely-recommendations-image"
								src={ imageForLink }
								alt={ imageAlt }
							/>
						</CardMedia>
					) }
					<CardBody className="parsely-recommendations-cardbody">{ linkTitle }</CardBody>
				</Card>
			</a>
		</li>
	);
};
export default ParselyRecommendationsListItem;
