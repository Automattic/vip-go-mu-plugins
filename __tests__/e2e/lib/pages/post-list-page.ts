import { type Page } from '@playwright/test';

import { type PostType } from '../wp-api-helper';

const selectors = {
	postLink: ( postID: string ) => `#post-${ postID } a.row-title`,
};

export class PostListPage {
	private readonly page: Page;
	private readonly postType: PostType;

	/**
	 * Constructs an instance of the component.
	 *
	 * @param { Page }     page     The underlying page
	 * @param { PostType } postType Post type to list
	 */
	constructor( page: Page, postType: PostType = 'post' ) {
		this.page = page;
		this.postType = postType;
	}

	/**
	 * Navigate to the list page for the post type
	 */
	public visit(): Promise<unknown> {
		return this.page.goto( `/wp-admin/edit.php?post_type=${ this.postType }` );
	}

	/**
	 * Edit Post by ID
	 *
	 * @param {string} postID ID of the post to be edited
	 */
	public editPostByID( postID: string ): Promise<void> {
		return this.page.locator( selectors.postLink( postID ) ).click();
	}
}
