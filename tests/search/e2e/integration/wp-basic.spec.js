describe('WordPress basic actions', () => {
	before(() => {
		cy.wpCli('vip-search index --setup --skip-confirm');
	});

	beforeEach(() => {
		cy.login();
	})

	it('Has <title> tag', () => {
		cy.visitAdminPage();
		cy.get('title').should('exist');
	});

	it('Can login', () => {
		cy.visitAdminPage();
		cy.get('#wpadminbar').should('exist');
	});

	it('Can see admin bar on front end', () => {
		cy.visitAdminPage();
		cy.get('#wpadminbar').should('exist');
	});

	it('Can save own profile', () => {
		cy.visitAdminPage('profile.php');
		cy.get('#first_name').clearThenType('Test Name');
		cy.get('#submit').click();
		cy.get('#first_name').should('have.value', 'Test Name');
	});

	it('Can change site title', () => {
		cy.visitAdminPage('options-general.php');
		cy.get('#wpadminbar').should('be.visible');
		cy.get('#blogname').clearThenType('Updated Title');
		cy.get('#submit').click();
		cy.get('#wp-admin-bar-site-name a').first().should('have.text', 'Updated Title');
	});
});
