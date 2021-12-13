import { Page } from "@playwright/test";

type PreviewOptions = 'Desktop' | 'Mobile' | 'Tablet';

const selectors = {
	editorTitle: '#title',
	editorFrame: '#content_ifr',
	editorBody: '#tinymce p',
	saveDraftButton: '#save-post',
	previewButton: '#post-preview',
	publishButton: '#publish',
	viewButton: '#message p a',
	permalink: '#sample-permalink a'
};

export class ClassicEditorPage {
  private page: Page; 

  /**
   * @param { import( 'playwright' ).Page } page 
   */
  constructor( page: Page ) {
    this.page = page;
  }

    /**
     * Enter Title of page or post
     * @param {string} title Page/Post Title
     */
    async enterTitle( title: string ): Promise< void > {
      await this.page.click( selectors.editorTitle );
      await this.page.fill( selectors.editorTitle, title );
    }

    /**
     * Enter text in to page or post
     * @param {string} text Text to enter
     */
    async enterText( text: string ): Promise< void > {
	  const editorFrame = await this.page.click( selectors.editorFrame );
	  await this.page.keyboard.type( text );
    }

  /**
	 * Publishes the post or page.
	 *
	 * @param {boolean} visit Whether to then visit the page.
	 */
	async publish( { visit = false }: { visit?: boolean; } = {} ): Promise< string > {
		const publishedURL = await this.page.locator( selectors.permalink ).textContent() as string;
		await this.page.click( selectors.publishButton );
		
		if ( visit ) {
			await this.visitPublishedPost( publishedURL );
		}
		return publishedURL;
	}

  /**
	 * Visits the published entry from the post-publish sidebar.
	 *
	 */
	private async visitPublishedPost( url: string ): Promise< void > {
		await Promise.all( [
			this.page.waitForNavigation( { waitUntil: 'networkidle', url: url } ),
			this.page.click( selectors.viewButton ),
		] );
	}
}
