describe('WooCommerce Feature', () => {
	before(() => {
		cy.deactivatePlugin('woocommerce', 'wpCli');

	});

	beforeEach(() => {
		cy.login();
	})

	after(() => {
		cy.deactivatePlugin('woocommerce', 'wpCli');
	});

	it('Can auto-activate the feature', () => {

		cy.activatePlugin('woocommerce');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-woocommerce').should('have.class', 'feature-active');
	});

	it('Can fetch orders from Elasticsearch', () => {

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		cy.visitAdminPage('edit.php?post_type=shop_order');
		cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar
	});

	it('Can fetch products from Elasticsearch in WP Dashboard', () => {

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		cy.visitAdminPage('edit.php?post_type=product');
		cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar
	});

	it('Can fetch products from Elasticsearch in product category archives', () => {

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/product-category/uncategorized');
		cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar
	});

	it('Can fetch products from Elasticsearch in product rivers', () => {

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/shop/?filter_size=small');
		cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar
	});
});
