/**
 * External dependencies
 */
import { APIRequestContext, APIResponse } from '@playwright/test';

type PostType = 'post' | 'page';
type PostData = {
    postType: PostType;
    title: string;
    body: string;
};

/**
 * Given a post id and post type, deletes the post using the REST api and the nonce saved to environment variable
 *
 * @param {APIRequestContext} request The Playwright API Request context
 * @param {string} id Id of the post to be deleted
 * @param {PostType} postType Type of the post to be deleted
 * @returns {APIResponse} The response of the api call.
 */
export async function deletePost( request: APIRequestContext, id: string, postType: PostType ): Promise<APIResponse> {
    const response = await request.delete( `/wp-json/wp/v2/${ postType }s/${ id }`, {
        headers: {
            'X-WP-Nonce': process.env.WP_E2E_NONCE,
        },
    } );
    return response;
}

/**
 * Given a post id, deletes the post using the REST api and the nonce saved to environment variable
 *
 * @param {APIRequestContext} request The Playwright API Request context
 * @param {PostData} postData Object containing title, body and type of post
 * @returns {APIResponse} The response of the api call.
 */
export async function createPost( request: APIRequestContext, postData: PostData ): Promise<APIResponse> {
    const response = await request.post( `/wp-json/wp/v2/${ postData.postType }s/?force=true`, {
        headers: {
            'X-WP-Nonce': process.env.WP_E2E_NONCE,
        },
        data: {
            title: postData.title,
            status: 'publish',
            content: postData.body,
        },
    } );
    return response;
}
