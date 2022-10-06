describe('Related Posts Feature', () => {
	/**
	 * Ensure the feature is active and ensure Classic Widgets is installed
	 * before running tests.
	 */
	before(() => {
		cy.maybeEnableFeature('related_posts');
	});

	/**
	 * Delete all widgets, ensure Classic Widgets is deactivated, and remove
	 * test posts before each test.
	 */
	beforeEach(() => {
		cy.emptyWidgets();
		cy.deactivatePlugin('classic-widgets', 'wpCli');
		cy.wpCli('post list --s="Test related posts" --ep_integrate=false --format=ids').then(
			(wpCliResponse) => {
				if (wpCliResponse.stdout) {
					cy.wpCli(`post delete ${wpCliResponse.stdout} --force`);
				}
			},
		);
	});

	/**
	 * Test that the Related Posts widget is functional and can be transformed
	 * into the Related Posts block.
	 */
	it('Can insert, configure, use, and transform the legacy Related Posts widget', () => {
		/**
		 * Add the legacy widget.
		 */
		cy.activatePlugin('classic-widgets', 'wpCli');
		cy.createClassicWidget('ep-related-posts', [
			{
				name: 'title',
				value: 'My related posts widget',
			},
			{
				name: 'num_posts',
				value: '2',
			},
		]);

		/**
		 * Create some posts that will be related and view the last post.
		 */
		for (let i = 0; i < 4; i++) {
			const viewPost = i === 3;

			cy.publishPost(
				{
					title: `Test related posts widget #${i + 1}`,
					content: 'Inceptos tristique class ac eleifend leo.',
				},
				viewPost,
			);
		}

		/**
		 * Verify the widget has the expected output on the front-end based on
		 * the widget's settings.
		 */
		cy.get(`[id^="ep-related-posts"]`).first().as('widget');
		cy.get('@widget')
			.should('contain.text', 'My related posts widget')
			.find('li')
			.should('contain', 'Test related posts widget #')
			.should('have.length', 2);

		/**
		 * Visit the block-based Widgets screen.
		 */
		cy.deactivatePlugin('classic-widgets', 'wpCli');
		cy.openWidgetsPage();

		/**
		 * Check that the widget is inserted in to the editor as a Legacy
		 * Widget block.
		 */
		cy.get(`.wp-block-legacy-widget`).first().as('widget');
		cy.get('@widget').should('contain.text', 'ElasticPress - Related Posts');
	});
});
