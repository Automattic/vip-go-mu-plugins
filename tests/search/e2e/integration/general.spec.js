describe('WordPress can perform standard ElasticPress actions', () => {
	beforeEach(() => {
		cy.login();
	});

	it('Can see the settings page link in WordPress Dashboard', () => {
		// cy.login();

		cy.get('.toplevel_page_elasticpress .wp-menu-name').should('contain.text', 'Enterprise Search');
	});

	it('Can sync post data and meta details in Elasticsearch if user creates/updates a published post', () => {
		// cy.login();

		cy.publishPost({
			title: 'Test ElasticPress 1',
		});

		cy.visit('/?s=Test+ElasticPress+1');
		cy.contains('.site-content article h2', 'Test ElasticPress 1').should('exist');
	});
});
