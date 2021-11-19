import { Page } from 'playwright';

/**
 * Represents the site's published post.
 */
export class PublishedPostPage {
	private page: Page;

	/**
	 * Constructs an instance of the component.
	 *
	 * @param {Page} page The underlying page.
	 */
	constructor( page: Page ) {
		this.page = page;
	}


	/**
	 * Validates that the provided text can be found in the post page. 
	 *
	 * @param {string} text Text to search for in post page
	 */
	async validateTextInPost( text: string ): Promise< void > {
		await this.page.waitForSelector( `text=${ text }` );
	}
}