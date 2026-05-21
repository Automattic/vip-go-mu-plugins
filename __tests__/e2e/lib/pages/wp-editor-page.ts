import type { FrameLocator, Locator, Page } from '@playwright/test';

type WPBlock = {
	attributes?: {
		content?: string;
	};
	clientId?: string;
	innerBlocks?: WPBlock[];
	name?: string;
};

type WPBlockEditorSelect = {
	getBlocks?: () => WPBlock[];
};

type WPBlockEditorDispatch = {
	resetBlocks?: ( blocks: WPBlock[] ) => void;
};

type WPData = {
	select?: ( storeName: string ) => WPBlockEditorSelect;
	dispatch?: ( storeName: string ) => WPBlockEditorDispatch;
};

type WPWindow = Window & typeof globalThis & {
	wp?: {
		blocks?: {
			parse?: ( html: string ) => WPBlock[];
		};
		data?: WPData;
	};
};

const selectors = {
	// Editor
	editorCanvas: 'iframe[name="editor-canvas"]',
	editorRoot: '.editor-styles-wrapper',
	editorTitle: '.editor-post-title__input',

	// Block inserter
	addBlockButton: 'button[aria-label="Add block"]',
	blockInserterToggle: 'button.edit-post-header-toolbar__inserter-toggle',
	blockInserterPanel: '.block-editor-inserter__content',
	blockSearch: '.block-editor-inserter__search input[type="search"]',
	blockInserterResultItem: '.block-editor-block-types-list__list-item',

	// Within the editor body.
	blockAppender: '.block-editor-default-block-appender',
	blockInserter: '.block-editor-inserter__toggle',
	paragraphBlocks: 'p.wp-block-paragraph',
	block: '.wp-block[id*="block-"][data-empty="false"]',
	blockWarning: '.block-editor-warning',
	imageBlocks: '.editor-block-list-item-image',
	uploadImageButton: '.block-editor-media-placeholder__upload-button',
	firstEmptyBlock: '.wp-block-paragraph[data-empty="true"]',
	spinner: '.components-spinner',

	// Top bar selectors.
	postToolbar: '.edit-post-header',
	settingsToggle: '.edit-post-header__settings .interface-pinned-items button:first-child',
	saveDraftButton: '.editor-post-save-draft',
	previewButton: ':is(button:text("Preview"), a:text("Preview"))',
	publishButton: ( parentSelector: string ) => `${ parentSelector } button:text("Publish")[aria-disabled=false]`,
	updateButton: '.editor-post-publish-button',
	// Settings panel.
	settingsPanel: '.interface-complementary-area',

	// Publish panel (including post-publish)
	publishPanel: '.editor-post-publish-panel',
	viewButton: '.editor-post-publish-panel a:has-text("View")',
	addNewButton: '.editor-post-publish-panel a:text-matches("Add a New P(ost|age)")',
	closePublishPanel: 'button[aria-label="Close panel"]',

	// Welcome tour
	welcomeTourCloseButton: '.edit-post-welcome-guide .components-modal__header button',

	// Block editor sidebar
	desktopEditorSidebarButton: 'button[aria-label="Block editor sidebar"]:visible',
	desktopDashboardLink: 'a[aria-description="Returns to the dashboard"]:visible',
	mobileDashboardLink: 'a[aria-current="page"]:visible',

	// Choose a pattern
	choosePatternCloseButton: '.components-modal__screen-overlay .components-modal__header button',
};

export class EditorPage {
	private page: Page;
	private hasDismissedNuisances = false;

	/**
	 * Constructs an instance of the component.
	 *
	 * @param { Page } page The underlying page
	 */
	constructor( page: Page ) {
		this.page = page;
	}

	private async editorContent(): Promise<Page | FrameLocator> {
		const nuisanceTimeout = this.hasDismissedNuisances ? 0 : 1000;
		await EditorPage.dismissAnnoyingNuisances( this.page, nuisanceTimeout );

		const editorCanvas = this.page.locator( selectors.editorCanvas );
		const editorRoot = this.page.locator( selectors.editorRoot );
		const visibleEditor = await Promise.race( [
			editorCanvas.waitFor( { state: 'visible' } ).then( () => 'iframe' as const ).catch( () => undefined ),
			editorRoot.waitFor( { state: 'visible' } ).then( () => 'page' as const ).catch( () => undefined ),
		] );

		await EditorPage.dismissAnnoyingNuisances( this.page, nuisanceTimeout );
		this.hasDismissedNuisances = true;

		if ( 'iframe' === visibleEditor || await editorCanvas.isVisible() ) {
			return this.page.frameLocator( selectors.editorCanvas );
		}

		return this.page;
	}

	private async editorLocator( selector: string ): Promise<Locator> {
		return ( await this.editorContent() ).locator( selector );
	}

	private async waitForBlockEditor(): Promise<void> {
		await this.page.waitForFunction( () => {
			const wp = ( window as WPWindow ).wp;
			const data = wp?.data;
			const blockEditorSelect = data?.select?.( 'core/block-editor' );
			const blockEditorDispatch = data?.dispatch?.( 'core/block-editor' );

			return Boolean( wp?.blocks?.parse && blockEditorSelect?.getBlocks && blockEditorDispatch?.resetBlocks );
		} );
	}

	private async editorOrPageLocator( selector: string ): Promise<Locator> {
		const editorContent = await this.editorContent();
		const pageLocator = this.page.locator( selector );

		if ( ! await this.page.locator( selectors.editorCanvas ).isVisible() ) {
			return pageLocator;
		}

		const editorLocator = editorContent.locator( selector );
		const visibleLocator = await Promise.race( [
			editorLocator.first().waitFor( { state: 'visible' } ).then( () => editorLocator ).catch( () => undefined ),
			pageLocator.first().waitFor( { state: 'visible' } ).then( () => pageLocator ).catch( () => undefined ),
		] );

		return visibleLocator ?? pageLocator;
	}

	private static async clickIfVisible( page: Page, selector: string, timeout: number ): Promise<void> {
		const locator = page.locator( `${ selector }:visible` ).first();
		const isVisible = 0 < timeout
			? await locator.waitFor( { state: 'visible', timeout } )
				.then( () => true )
				.catch( () => false )
			: await locator.isVisible();

		if ( ! isVisible ) {
			return;
		}

		await locator.evaluate( ( button: HTMLButtonElement ) => button.click() );
		await locator.waitFor( { state: 'hidden', timeout: 3000 } ).catch( () => undefined );
	}

	private static async dismissAnnoyingNuisances( page: Page, timeout = 0 ): Promise<void> {
		await EditorPage.clickIfVisible( page, selectors.welcomeTourCloseButton, timeout );
		await EditorPage.clickIfVisible( page, selectors.choosePatternCloseButton, timeout );
	}

	public static automaticallyDismissAnnoyingNuisances( page: Page ): Promise<void> {
		return EditorPage.dismissAnnoyingNuisances( page, 1000 );
	}

	/**
	 * Enter Title of page or post
	 *
	 * @param {string} title Page/Post Title
	 */
	public async enterTitle( title: string ): Promise<void> {
		await ( await this.editorLocator( selectors.editorTitle ) ).fill( title );
	}

	/**
	 * Enter text in to page or post
	 *
	 * @param {string} text Text to enter
	 */
	public async enterText( text: string ): Promise<void> {
		const lines = text.split( '\n' );
		let locator: Locator;
		const blockAppender = await this.editorLocator( selectors.blockAppender );
		const paragraphBlocks = await this.editorLocator( selectors.paragraphBlocks );
		if ( await blockAppender.isVisible() ) {
			locator = blockAppender;
		} else {
			locator = paragraphBlocks.last();
		}

		await locator.click();

		// Playwright does not break up newlines in Gutenberg. This causes issues when we expect
		// text to be broken into new lines/blocks. This presents an unexpected issue when entering
		// text such as 'First sentence\nSecond sentence', as it is all put in one line.
		// frame.type() will respect newlines like a human would, but it is slow.
		// This approach will run faster than using frame.type() while respecting the newline chars.
		/* eslint-disable no-await-in-loop */
		for ( let idx = 0; idx < lines.length; ++idx ) {
			// eslint-disable-next-line security/detect-object-injection
			const line = lines[ idx ];
			const lineLocator = paragraphBlocks.nth( idx );
			await lineLocator.fill( line );
			await lineLocator.press( 'Enter' );
		}
		/* eslint-enable no-await-in-loop */
	}

	/**
	 * Clear Title of page or post
	 */
	public async clearTitle(): Promise<void> {
		await ( await this.editorLocator( selectors.editorTitle ) ).fill( '' );
	}

	/**
	 * Clear text of page or post
	 */
	public async clearText(): Promise<void> {
		await this.editorContent();
		await this.waitForBlockEditor();
		await this.page.evaluate( () => {
			const wp = ( window as WPWindow ).wp;
			const data = wp?.data;
			const blockEditorDispatch = data?.dispatch?.( 'core/block-editor' );
			const blocks = wp?.blocks?.parse?.( '' ) ?? [];

			blockEditorDispatch?.resetBlocks?.( blocks );
		} );
		await this.page.waitForFunction( () => {
			const blocks = ( window as WPWindow ).wp?.data?.select?.( 'core/block-editor' )?.getBlocks?.();

			return Array.isArray( blocks ) && blocks.every( ( block ) => {
				return ! block.attributes?.content && 0 === ( block.innerBlocks?.length ?? 0 );
			} );
		} );
	}

	/**
	 * Add Image to Post or Page
	 *
	 * @param {string} fileName Name of image file to add
	 */
	public async addImage( fileName: string ): Promise<void> {
		const blockAppender = await this.editorLocator( selectors.blockAppender );
		if ( await blockAppender.isVisible() ) {
			await blockAppender.click();
		} else {
			const lastBlock = ( await this.editorLocator( selectors.paragraphBlocks ) ).last();
			await lastBlock.click();
			const box = await lastBlock.boundingBox();
			if ( box ) {
				const offsetX = box.x + ( box.width - 10 );
				const offsetY = box.y + ( box.height / 2 );
				await this.page.mouse.move( offsetX, offsetY );
			}
			await ( await this.editorOrPageLocator( selectors.addBlockButton ) ).click();
		}
		await ( await this.editorOrPageLocator( selectors.imageBlocks ) ).click();

		const [ fileChooser ] = await Promise.all( [
			// It is important to call waitForEvent before click to set up waiting.
			this.page.waitForEvent( 'filechooser' ),
			// This has to click twice, the first focuses in the block, the second opens the upload
			( await this.editorLocator( selectors.uploadImageButton ) ).click(),
			( await this.editorLocator( selectors.uploadImageButton ) ).click(),
		] );
		await fileChooser.setFiles( fileName );
		await ( await this.editorLocator( selectors.spinner ) ).waitFor( { state: 'detached' } );
	}

	/**
	 * Publishes the post or page.
	 *
	 * @param {boolean} visit Whether to then visit the page.
	 * @return {string} Url of published post or page
	 */
	public async publish( { visit = false }: { visit?: boolean } = {} ): Promise<string> {
		await this.page.locator( selectors.publishButton( selectors.postToolbar ) ).click();
		await this.page.locator( selectors.publishButton( selectors.publishPanel ) ).click();
		const publishedURL = ( await this.page.locator( selectors.viewButton ).getAttribute( 'href' ) )!;

		if ( visit ) {
			await this.visitPublishedPost( publishedURL );
		}
		return publishedURL;
	}

	/**
	 * Updates the post or page.
	 */
	public update(): Promise<void> {
		return this.page.locator( selectors.updateButton ).click();
	}

	/**
	 * Visits the published entry from the post-publish sidebar.
	 *
	 * @param {string} url Url to visit
	 */
	private async visitPublishedPost( url: string ): Promise<unknown[]> {
		const locator = this.page.locator( selectors.viewButton );
		await locator.evaluate( ( el ) => el.removeAttribute( 'target' ) );
		return Promise.all( [
			this.page.waitForURL( url, { waitUntil: 'domcontentloaded' } ),
			locator.click(),
		] );
	}
}
