/**
 * External dependencies
 */
import { Page } from '@playwright/test';

export class WPAdminSidebarComponent {
    readonly page: Page;

    /**
     *  Constructs an instance of the component.
     *
     * @param { Page } page The underlying page
     */
    constructor( page: Page ) {
        this.page = page;
    }

    /**
     * Hover over sidebar menu item
     *
     * @param {string} itemName Name of the item to be hovered over
     */
    async hoverMenuItem( itemName: string ): Promise<void> {
        await this.page.hover( `#adminmenu li :text('${ itemName }')` );
    }

    /**
     * Click sidebar menu item
     *
     * @param {string} itemName Name of the item to be clicked
     */
    async clickMenuItem( itemName: string ): Promise<void> {
        await this.page.click( `#adminmenu li :text('${ itemName }')` );
    }

    /**
     * Hover over sidebar submenu item
     *
     * @param {string} itemName Name of the item to be hovered over
     */
    async hoverSubMenuItem( itemName: string ): Promise<void> {
        await this.page.hover( `.wp-menu-open .wp-submenu :text('${ itemName }')` );
    }

    /**
     * Click sidebar submenu item
     *
     * @param {string} itemName Name of the item to be clicked
     */
    async clickSubMenuItem( itemName: string ): Promise<void> {
        await this.page.click( `.wp-menu-open .wp-submenu :text('${ itemName }')` );
    }
}
