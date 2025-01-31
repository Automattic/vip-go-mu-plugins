Cypress.Commands.add('openBlockSettingsSidebar', () => {
	cy.get('body').then(($el) => {
		if ($el.hasClass('widgets-php')) {
			cy.get('.edit-widgets-header__actions button[aria-label="Settings"]').click();
			cy.get('.edit-widgets-sidebar__panel-tab').contains('Block').click();
		} else {
			cy.get('.editor-header__settings button[aria-label="Settings"]').then(($btn) => {
				if (!$btn.hasClass('is-pressed')) {
					$btn.trigger('click');
				}
				cy.get('.editor-sidebar__panel-tabs').contains('Block').click();
			});
		}
	});
});

Cypress.Commands.add('openBlockInserter', () => {
	cy.get('body').then(($body) => {
		// If already open, skip.
		if ($body.find('.edit-widgets-layout__inserter-panel-content').length > 0) {
			return;
		}
		cy.get('button[aria-label="Toggle block inserter"]').click();
	});
});

Cypress.Commands.add('getBlocksList', () => {
	cy.get('.block-editor-inserter__block-list');
});

Cypress.Commands.add('insertBlock', (blockName) => {
	cy.get('.block-editor-block-types-list__item').contains(blockName).click();
});
