import type { Locator, Page } from '@playwright/test';

const selectors = {
	dialog: 'dialog.vip-large-media-warning-dialog',
	confirmButton: 'dialog.vip-large-media-warning-dialog button[data-action="confirm"]',
	cancelButton: 'dialog.vip-large-media-warning-dialog button[data-action="cancel"]',
};

export class LargeMediaWarningModal {
	private readonly page: Page;

	constructor( page: Page ) {
		this.page = page;
	}

	public get dialog(): Locator {
		return this.page.locator( selectors.dialog );
	}

	public waitForVisible( timeout = 5000 ): Promise<void> {
		return this.dialog.waitFor( { state: 'visible', timeout } );
	}

	public confirm(): Promise<void> {
		return this.page.locator( selectors.confirmButton ).click();
	}

	public cancel(): Promise<void> {
		return this.page.locator( selectors.cancelButton ).click();
	}
}
