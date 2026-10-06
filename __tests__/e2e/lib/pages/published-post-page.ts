import type { Locator, Page } from '@playwright/test';

const selectors = {
	postImage: '.entry-content img',
	postContent: '.entry-content',
};

/**
 * Represents the site's published post or page.
 */
export class PublishedPostPage {
	private readonly page: Page;
	public readonly image: Locator;
	public readonly content: Locator;

	/**
	 * Constructs an instance of the component.
	 *
	 * @param {Page} page The underlying page.
	 */
	constructor( page: Page ) {
		this.page = page;
		this.image = page.locator( selectors.postImage );
		this.content = page.locator( selectors.postContent );
	}

	/**
	 * Returns the heading that renders the given post or page title.
	 *
	 * @param {string} title Title text
	 * @return {Locator} Heading locator
	 */
	public heading( title: string ): Locator {
		return this.page.getByRole( 'heading', { name: title, exact: true } ).first();
	}
}
