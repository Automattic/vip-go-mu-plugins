// eslint-disable-next-line jest/valid-describe-callback
describe('WooCommerce Feature', { tags: '@slow' }, () => {
	const userData = {
		username: 'testuser',
		email: 'testuser@example.com',
		firstName: 'John',
		lastName: 'Doe',
		address: '123 Main St',
		city: 'Culver City',
		postCode: '90230',
		phoneNumber: '1234567890',
	};

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

	it('Can fetch products from Elasticsearch in product rivers and category archives', () => {
		cy.login();

		cy.maybeEnableFeature('woocommerce');

		cy.visit('/shop/?filter_size=small');
		cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar

		cy.visit('/product-category/uncategorized');
		cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar
	});

	context('Dashboard', () => {
		before(() => {
			cy.login();
			cy.activatePlugin('woocommerce', 'wpCli');
			cy.maybeEnableFeature('protected_content');
			cy.maybeEnableFeature('woocommerce');
			cy.wpCli( 'plugin activate vip-woocommerce-meta-allow-list' ); // VIP: Since we index only on an explicit allow list, we need to add protected Woo keys.
		});

		it('Can fetch orders and products from Elasticsearch', () => {
			/**
			 * Orders
			 */
			// this is required to sync the orders to Elasticsearch.
			cy.wpCli('vip-search index --setup --skip-confirm');

			cy.visitAdminPage('edit.php?post_type=shop_order');
			cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar

			/**
			 * Products
			 */
			cy.visitAdminPage('edit.php?post_type=product');
			cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar
		});

		it('Can not display other users orders on the My Account Order page', () => {
			// enable payment gateway.
			cy.visitAdminPage('admin.php?page=wc-settings&tab=checkout&section=cod');
			cy.get('.woocommerce-layout__header-tasks-reminder-bar', { timeout: 10000 })
  			.should('be.visible');

			cy.get('#woocommerce_cod_enabled').check({waitForAnimations: false});
			cy.get(
				`.button-primary.woocommerce-save-button,
				.components-button.is-primary.woocommerce-save-button`,
			).click();

			cy.logout();

			// create new user.
			cy.createUser({
				username: userData.username,
				email: userData.email,
				login: true,
			});

			// add product to cart.
			cy.visit('product/fantastic-silk-knife');
			cy.get('.single_add_to_cart_button').click();

			// checkout and place order.
			cy.visit('checkout');
			cy.get('#billing-first_name').type(userData.firstName);
			cy.get('#billing-last_name').type(userData.lastName);
			cy.get('#billing-address_1').type(userData.address);
			cy.get('#billing-city').type(userData.city);
			cy.get('#billing-postcode').type(userData.postCode);
			cy.get('button.wc-block-components-checkout-place-order-button').trigger('click'); // VIP: Need to use .trigger('click') because Cypress can't click on the button.

			// VIP: Give some time for the order to be placed.
			cy.wait(2000); // eslint-disable-line cypress/no-unnecessary-waiting

			// ensure order is placed.
			cy.url().should('include', '/checkout/order-received');

			/**
			 * Give Elasticsearch some time to process the new posts.
			 *
			 */
			// eslint-disable-next-line cypress/no-unnecessary-waiting
			cy.wait(2000);

			// ensure order is visible to user.
			cy.visit('my-account/orders');
			cy.get('.woocommerce-orders-table tbody tr').should('have.length', 1);

			// VIP: Use Search Dev Tools instead of Debug Bar
			cy.searchDevToolsResponseOK('shop_order');
			cy.get('#vip-search-dev-tools-mount').click();
			cy.get('h3.vip-h3').eq(2).click();
			cy.get('strong.vip-h4.wp_query').eq(2).click();
			cy.get('ol.wp_query.vip-collapse-ol').eq(2).should('contain.text','orderby: "date"');
			cy.get('#vip-search-dev-tools-mount').click();

			cy.logout();

			cy.createUser({
				username: 'buyer',
				email: 'buyer@example.com',
				login: true,
			});

			// ensure no order is show.
			cy.visit('my-account/orders');
			cy.get('.woocommerce-orders-table tbody tr').should('have.length', 0);

			cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar
		});

		it('Can search orders from ElasticPress in WP Dashboard', () => {
			cy.visitAdminPage('edit.php?post_type=shop_order');
			cy.maybeRelogin(); // VIP: The login usually times out at this point

			// search order by user's name.
			cy.get('#post-search-input')
				.clear()
				.type(`${userData.firstName} ${userData.lastName}{enter}`);

			cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar

			cy.get('.order_number .order-view').should(
				'contain.text',
				`${userData.firstName} ${userData.lastName}`,
			);

			// search order by user's address.
			cy.get('#post-search-input').clear().type(`${userData.address}{enter}`);
			cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar
			cy.get('.order-view').should(
				'contain.text',
				`${userData.firstName} ${userData.lastName}`,
			);

			// search order by product.
			cy.get('#post-search-input').clear().type(`fantastic-silk-knife{enter}`);
			cy.searchDevToolsResponseOK(); // VIP: Use Search Dev Tools instead of Debug Bar

			cy.get('.order-view').should(
				'contain.text',
				`${userData.firstName} ${userData.lastName}`,
			);
		});
	});
});
