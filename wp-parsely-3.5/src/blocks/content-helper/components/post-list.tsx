/**
 * External dependencies
 */
import { Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import ContentHelperProvider from '../content-helper-provider';
import PostCard from './post-card';
import { SuggestedPost } from '../models/suggested-post';

const FETCH_RETRIES = 3;

function PostList() {
	const [ loading, setLoading ] = useState<boolean>( true );
	const [ error, setError ] = useState<string>( null );
	const [ message, setMessage ] = useState<string>( null );
	const [ posts, setPosts ] = useState<SuggestedPost[]>( [] );

	useEffect( () => {
		const fetchPosts = async ( retries: number ) => {
			ContentHelperProvider.getTopPosts()
				.then( ( result ) => {
					setPosts( result.posts );
					setMessage( result.message );
					setLoading( false );
				} )
				.catch( async ( err: string ) => {
					if ( retries > 0 ) {
						await new Promise( ( r ) => setTimeout( r, 500 ) );
						await fetchPosts( retries - 1 );
					} else {
						setLoading( false );
						setError( err );
					}
				} );
		};

		setLoading( true );
		fetchPosts( FETCH_RETRIES );
	}, [] );

	if ( error ) {
		return <p>{ error }</p>;
	}

	const postList = posts.map( ( post ) => <PostCard key={ post.id } post={ post } /> );
	return (
		<>
			<p>{ message }</p>
			{ loading ? <Spinner /> : postList }
		</>
	);
}

export default PostList;
