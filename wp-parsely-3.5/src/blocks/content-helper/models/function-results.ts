/**
 * Internal dependencies
 */
import { SuggestedPost } from './suggested-post';

export interface GetTopPostsResult {
	message: string;
	posts: SuggestedPost[];
}

export interface BuildFetchDataQueryResult {
	message: string;
	query: object;
}
