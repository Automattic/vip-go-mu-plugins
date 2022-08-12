/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { SuggestedPost } from '../models/suggested-post';

interface PostCardProps {
	post: SuggestedPost;
}

function PostCard( { post }: PostCardProps ) {
	return (
		<>
			<Card size="small" elevation={ 2 } className="parsely-content-helper-card">
				<CardHeader><b>{ post.title }</b></CardHeader>
				<CardBody>
					<ul>
						<li>{ __( 'Views:', 'wp-parsely' ) } { post.views }</li>
						<li>{ __( 'Published:', 'wp-parsely' ) } { post.date }</li>
						<li>{ __( 'Author:', 'wp-parsely' ) } { post.author }</li>
					</ul>
					<p>
						<Button href={ post.url } target="_blank" variant="primary">{ __( 'Open Post', 'wp-parsely' ) }</Button> { ' ' }
						<Button href={ post.statsUrl } target="_blank" variant="secondary">{ __( 'Post Stats', 'wp-parsely' ) }</Button>
					</p>
				</CardBody>
			</Card>
		</>
	);
}

export default PostCard;
