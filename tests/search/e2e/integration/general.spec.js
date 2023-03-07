// eslint-disable-next-line jest/valid-describe-callback
describe('WordPress can perform standard ElasticPress actions', { tags: '@slow' }, () => {
	it('Can see the menu page link in wp-admin', () => {
		cy.login();

		cy.visitAdminPage('/');

		cy.get('.toplevel_page_elasticpress').should('contain.text', 'Enterprise Search');
	});

	it('Can sync post data and meta details in Elasticsearch if user creates/updates a published post', () => {
		cy.login();

		cy.publishPost({
			title: 'Test ElasticPress 1',
		});

		cy.visit('/?s=Test+ElasticPress+1');
		cy.contains('.site-content article h2', 'Test ElasticPress 1').should('exist');
	});

	it('Cannot save settings while a sync is in progress', () => {
		cy.login();
		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.wpCliEval(`update_option( 'ep_index_meta', [ 'indexing' => true ] );`).then(() => {
			cy.get('.ep-feature-search .settings-button').click();
			cy.get('.ep-feature-search .button-primary').click();
			cy.get('.ep-feature-search .requirements-status-notice--syncing').should('be.visible');
			cy.wpCliEval(`delete_option( 'ep_index_meta' );`);
		});
	});
});
