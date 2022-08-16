/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';
// eslint-disable-next-line import/named
import { Schema } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { SuggestedPost } from './models/suggested-post';
import { GetTopPostsResult, BuildFetchDataQueryResult } from './models/function-results';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

interface ApiResponse {
	error?: object;
	data?: SuggestedPost[];
}

class ContentHelperProvider {
	static async getTopPosts(): Promise<GetTopPostsResult> {
		const editor = select( 'core/editor' );

		// Get post's author.
		const currentPost = editor.getCurrentPost() as Schema.Post;
		const author = select( 'core' ).getEntityRecord( 'root', 'user', currentPost.author ) as Schema.User;

		// Get post's first category.
		const categoryIds = editor.getEditedPostAttribute( 'categories' ) as Array<number>;
		const category = select( 'core' ).getEntityRecord( 'taxonomy', 'category', categoryIds[ 0 ] ) as Schema.Taxonomy;

		// Get post's first tag.
		const tagIds = editor.getEditedPostAttribute( 'tags' ) as Array<number>;
		const tag = select( 'core' ).getEntityRecord( 'taxonomy', 'post_tag', tagIds[ 0 ] ) as Schema.Taxonomy;

		// Create API query.
		const fetchQueryResult = this.buildFetchDataQuery( author, category, tag );
		if ( fetchQueryResult.query === null ) {
			return Promise.reject( fetchQueryResult.message );
		}

		// Fetch results from API and set the Content Helper's message.
		const data = await this.fetchData( fetchQueryResult );
		let message = `${ __( 'Top-performing posts', 'wp-parsely' ) } ${ fetchQueryResult.message }.`;

		if ( typeof data === 'string' ) {
			return { message: data, posts: [] };
		}

		if ( data.length === 0 ) {
			message = `${ __( 'The Parse.ly API did not return any results for top-performing posts', 'wp-parsely' ) } ${ fetchQueryResult.message }.`;
		}

		return { message, posts: this.processData( data ) };
	}

	private static async fetchData( fetchDataQueryResult: BuildFetchDataQueryResult ): Promise<SuggestedPost[] | string> {
		let response;

		try {
			response = await apiFetch( {
				path: addQueryArgs( '/wp-parsely/v1/analytics/posts', fetchDataQueryResult.query ),
			} ) as ApiResponse;
		} catch ( wpError ) {
			return `${ __( 'WordPress Error:', 'wp-parsely' ) } ${ wpError.message }`;
		}

		if ( response?.error ) {
			const errorMessage = JSON.stringify( response.error ).match( /\[\"(.*?)\"\]/ )[ 1 ];
			return `${ __( 'Error:', 'wp-parsely' ) } ${ errorMessage }`;
		}

		return response?.data || [];
	}

	private static processData( data: SuggestedPost[] ): SuggestedPost[] {
		return data.map( ( p ) => {
			const statsUrl = `${ window.wpParselyContentHelperPrefix }?url=${ window.encodeURIComponent( p.url ) }`;
			return Object.assign( p, { statsUrl } );
		} );
	}

	private static buildFetchDataQuery( author: Schema.User, category: Schema.Taxonomy, tag: Schema.Taxonomy ): BuildFetchDataQueryResult {
		const limit = 5;

		if ( ! author && ! category && ! tag ) {
			return ( {
				query: null,
				message: __( "Error: Cannot perform request because the post's Author, Category and Tag are empty.", 'wp-parsely' ),
			} );
		}

		if ( tag ) {
			return ( {
				query: { limit, tag },
				message: `${ __( 'with the tag', 'wp-parsely' ) } "${ tag.name }"`,
			} );
		}
		if ( category?.name ) {
			return ( {
				query: { limit, section: category.name },
				message: `${ __( 'in the category', 'wp-parsely' ) } "${ category.name }"`,
			} );
		}

		return ( {
			query: { limit, author: author.name },
			message: `${ __( 'by the author', 'wp-parsely' ) } "${ author.name }"`,
		} );
	}
}

export default ContentHelperProvider;
