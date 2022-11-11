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
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
	});

	it('Can fetch products from Elasticsearch in WP Dashboard', () => {

		cy.maybeEnableFeature('protected_content');
		cy.maybeEnableFeature('woocommerce');

		cy.visitAdminPage('edit.php?post_type=product');
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
	});

	it('Can fetch products from Elasticsearch in product category archives', () => {

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/product-category/uncategorized');
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
	});

	it('Can fetch products from Elasticsearch in product rivers', () => {

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/shop/?filter_size=small');
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
	});
});
