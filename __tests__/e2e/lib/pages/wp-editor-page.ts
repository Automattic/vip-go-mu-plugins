/**
 * External dependencies
 */
import { Page } from '@playwright/test';

const selectors = {
    // Editor
    editorTitle: '.editor-post-title__input',
    editorTitleContainer: '.edit-post-visual-editor__post-title-wrapper',

    // Block inserter
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
};

export class EditorPage {
    private page: Page;

    /**
     * Constructs an instance of the component.
     *
     * @param { Page } page The underlying page
     */
    constructor( page: Page ) {
        this.page = page;
    }

    /**
     * Dismisses the Welcome Tour (card) if it is present.
     */
    async dismissWelcomeTour(): Promise<void> {
        try {
            await this.page.waitForSelector( selectors.welcomeTourCloseButton, {
                state: 'visible',
                timeout: 5000,
            } );
        } catch ( err ) {
            return;
        }

        const closeButton = this.page.locator( selectors.welcomeTourCloseButton );
        return closeButton.click( {
            delay: 20,
        } );
    }

    /**
     * Enter Title of page or post
     *
     * @param {string} title Page/Post Title
     */
    async enterTitle( title: string ): Promise<void> {
        await this.page.click( selectors.editorTitleContainer );
        await this.page.fill( selectors.editorTitle, title );
    }

    /**
     * Enter text in to page or post
     *
     * @param {string} text Text to enter
     */
    async enterText( text: string ): Promise<void> {
        const lines = text.split( '\n' );
        if ( await this.page.isVisible( selectors.blockAppender ) ) {
            await this.page.click( selectors.blockAppender );
        } else {
            await this.page.click( selectors.paragraphBlocks );
        }

        // Playwright does not break up newlines in Gutenberg. This causes issues when we expect
        // text to be broken into new lines/blocks. This presents an unexpected issue when entering
        // text such as 'First sentence\nSecond sentence', as it is all put in one line.
        // frame.type() will respect newlines like a human would, but it is slow.
        // This approach will run faster than using frame.type() while respecting the newline chars.
        await Promise.all(
            lines.map( async ( line, index ) => {
                await this.page.fill( `${ selectors.paragraphBlocks }:nth-of-type(${ index + 1 })`, line );
                await this.page.keyboard.press( 'Enter' );
            } ),
        );
    }

    /**
     * Clear Title of page or post
     */
    async clearTitle(): Promise<void> {
        await this.page.click( selectors.editorTitle );
        await this.page.keyboard.down( 'Shift' );
        await this.page.keyboard.press( 'Home' );
        await this.page.keyboard.up( 'Shift' );
        await this.page.keyboard.press( 'Backspace' );
    }

    /**
     * Clear text of page or post
     */
    async clearText(): Promise<void> {
        /* eslint-disable no-await-in-loop */
        while ( await this.page.isVisible( selectors.block ) ) {
            await this.page.click( selectors.block );
            await this.page.keyboard.down( 'Shift' );
            await this.page.keyboard.press( 'Home' );
            await this.page.keyboard.up( 'Shift' );
            await this.page.keyboard.press( 'Backspace' );
            await this.page.keyboard.press( 'Backspace' );
        }
        /* eslint-enable no-await-in-loop */
    }

    /**
     * Add Image to Post or Page
     *
     * @param {string} fileName Name of image file to add
     */
    async addImage( fileName: string ): Promise<void> {
        if ( await this.page.isVisible( selectors.blockAppender ) ) {
            await this.page.click( selectors.blockAppender );
        } else {
            await this.page.keyboard.press( 'Tab' );
            await this.page.click( selectors.blockInserter );
        }
        await this.page.click( selectors.imageBlocks );

        const [ fileChooser ] = await Promise.all( [
            // It is important to call waitForEvent before click to set up waiting.
            this.page.waitForEvent( 'filechooser' ),
            // This has to click twice, the first focuses in the block, the second opens the upload
            this.page.click( selectors.uploadImageButton ),
            this.page.click( selectors.uploadImageButton ),
        ] );
        await fileChooser.setFiles( fileName );
        await this.page.waitForSelector( selectors.spinner, { state: 'detached' } );
    }

    /**
     * Publishes the post or page.
     *
     * @param {boolean} visit Whether to then visit the page.
     * @return {string} Url of published post or page
     */
    async publish( { visit = false }: { visit?: boolean } = {} ): Promise<string> {
        await this.page.click( selectors.publishButton( selectors.postToolbar ) );
        await this.page.click( selectors.publishButton( selectors.publishPanel ) );
        const viewPublishedArticleButton = await this.page.waitForSelector( selectors.viewButton );
        const publishedURL = ( await viewPublishedArticleButton.getAttribute( 'href' ) ) as string;

        if ( visit ) {
            await this.visitPublishedPost( publishedURL );
        }
        return publishedURL;
    }

    /**
     * Updates the post or page.
     */
    update(): Promise<void> {
        return this.page.click( selectors.updateButton );
    }

    /**
     * Visits the published entry from the post-publish sidebar.
     *
     * @param {string} url Url to visit
     */
    private visitPublishedPost( url: string ): Promise<unknown> {
        return Promise.all( [
            this.page.waitForNavigation( { waitUntil: 'networkidle', url } ),
            this.page.click( selectors.viewButton ),
        ] );
    }
}
