/**
 * External dependencies
 */
import { Page } from '@playwright/test';

const selectors = {
    menuItem: ( target: string ) => `#adminmenu li :text( '${ target }' )`,
    submenuItem: ( target: string ) => `.wp-menu-open .wp-submenu :text( '${ target }' )`,
};

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
    hoverMenuItem( itemName: string ): Promise<void> {
        return this.page.hover( selectors.menuItem( itemName ) );
    }

    /**
     * Click sidebar menu item
     *
     * @param {string} itemName Name of the item to be clicked
     */
    clickMenuItem( itemName: string ): Promise<void> {
        return this.page.click( selectors.menuItem( itemName ) );
    }

    /**
     * Hover over sidebar submenu item
     *
     * @param {string} itemName Name of the item to be hovered over
     */
    hoverSubMenuItem( itemName: string ): Promise<void> {
        return this.page.hover( selectors.submenuItem( itemName ) );
    }

    /**
     * Click sidebar submenu item
     *
     * @param {string} itemName Name of the item to be clicked
     */
    clickSubMenuItem( itemName: string ): Promise<void> {
        return this.page.click( selectors.submenuItem( itemName ) );
    }
}
