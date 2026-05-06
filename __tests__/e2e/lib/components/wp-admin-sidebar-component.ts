import { Page } from '@playwright/test';

const selectors = {
	menuItem: ( target: string ) => `#adminmenu li a :text-is( '${ target }' )`,
	submenuItem: ( target: string ) => `.wp-menu-open .wp-submenu a:text-is( '${ target }' )`,
};

export class WPAdminSidebarComponent {
	private readonly page: Page;

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
	public hoverMenuItem( itemName: string ): Promise<void> {
		return this.page.locator( selectors.menuItem( itemName ) ).hover();
	}

	/**
	 * Click sidebar menu item
	 *
	 * @param {string} itemName Name of the item to be clicked
	 */
	public clickMenuItem( itemName: string ): Promise<void> {
		return this.page.locator( selectors.menuItem( itemName ) ).click();
	}

	/**
	 * Hover over sidebar submenu item
	 *
	 * @param {string} itemName Name of the item to be hovered over
	 */
	public hoverSubMenuItem( itemName: string ): Promise<void> {
		return this.page.locator( selectors.submenuItem( itemName ) ).hover();
	}

	/**
	 * Click sidebar submenu item
	 *
	 * @param {string} itemName Name of the item to be clicked
	 */
	public clickSubMenuItem( itemName: string ): Promise<void> {
		return this.page.locator( selectors.submenuItem( itemName ) ).click();
	}
}
