import type { Page } from '@playwright/test';

const selectors = {
	dialog: 'dialog.vip-large-media-warning-dialog',
	confirmButton: 'dialog.vip-large-media-warning-dialog button[data-action="confirm"]',
	cancelButton: 'dialog.vip-large-media-warning-dialog button[data-action="cancel"]',
	dismissCheckbox: 'dialog.vip-large-media-warning-dialog #vip-lmw-dismiss',
};

export class LargeMediaWarningModal {
	private readonly page: Page;

	constructor( page: Page ) {
		this.page = page;
	}

	public waitForVisible( timeout = 5000 ): Promise<void> {
		return this.page.locator( selectors.dialog ).waitFor( { state: 'visible', timeout } );
	}

	public isVisible(): Promise<boolean> {
		return this.page.locator( selectors.dialog ).isVisible();
	}

	public confirm(): Promise<void> {
		return this.page.locator( selectors.confirmButton ).click();
	}

	public cancel(): Promise<void> {
		return this.page.locator( selectors.cancelButton ).click();
	}

	public dismissForSession(): Promise<void> {
		return this.page.locator( selectors.dismissCheckbox ).check();
	}
}
