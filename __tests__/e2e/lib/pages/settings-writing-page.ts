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
    async visit(): Promise<void> {
        await this.page.goto( '/wp-admin/options-writing.php' );
    }

    /**
     * Checks to see if Classic Editor Settings are available
     *
     * @returns { boolean } Whether classic editor settings are visible
     */
    async hasClassicEditor(): Promise<boolean> {
        return await this.page.isVisible( selectors.classicEditorBlock );
    }

    /**
     * Select settings to allow either block or classic editor
     */
    async allowBothEditors(): Promise<void> {
        await this.page.click( '#classic-editor-block' );
        await this.page.click( '#classic-editor-allow' );
        await this.page.click( '#submit' );
    }
}
