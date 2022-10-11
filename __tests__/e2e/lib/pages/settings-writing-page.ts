/**
 * External dependencies
 */
import { Page } from '@playwright/test';

const selectors = {
    classicEditorBlock: '#classic-editor-block',
    classicEditorAllow: '#classic-editor-allow',
    saveButton: '#submit',
};

export class SettingsWritingPage {
    readonly page: Page;

    /**
     * Constructs an instance of the component.
     *
     * @param { Page } page The underlying page
     */
    constructor( page: Page ) {
        this.page = page;
    }

    /**
     * Navigate to Writing Settings page
     */
    visit(): Promise<unknown> {
        return this.page.goto( '/wp-admin/options-writing.php' );
    }

    /**
     * Checks to see if Classic Editor Settings are available
     *
     * @return { Promise<boolean> } Whether classic editor settings are visible
     */
    async hasClassicEditor(): Promise<boolean> {
        const editorSettings = await this.page.locator( selectors.classicEditorBlock );
        return await editorSettings.isVisible();
    }

    /**
     * Select settings to allow either block or classic editor
     */
    async allowBothEditors(): Promise<void> {
        await this.page.click( selectors.classicEditorBlock );
        await this.page.click( selectors.classicEditorAllow );
        await this.page.click( selectors.saveButton );
    }
}
