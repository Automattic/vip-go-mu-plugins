describe('WooCommerce Feature', () => {
	before(() => {
		cy.deactivatePlugin('woocommerce', 'wpCli');
	});

	after(() => {
		cy.deactivatePlugin('woocommerce', 'wpCli');
	});

	it('Can auto-activate the feature', () => {
		cy.login();

		cy.activatePlugin('woocommerce');

		cy.visitAdminPage('admin.php?page=elasticpress');
		cy.get('.ep-feature-woocommerce').should('have.class', 'feature-active');
	});

	it('Can fetch orders from Elasticsearch', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		cy.visitAdminPage('edit.php?post_type=shop_order');
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
	});

	it('Can fetch products from Elasticsearch in WP Dashboard', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		cy.visitAdminPage('edit.php?post_type=product');
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
	});

	it('Can fetch products from Elasticsearch in product category archives', () => {
		cy.login();

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/product-category/uncategorized');
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
	});

	it('Can fetch products from Elasticsearch in product rivers', () => {
		cy.login();

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/shop/?filter_size=small');
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
	});
});
