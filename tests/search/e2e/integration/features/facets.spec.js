describe('Facets Feature', () => {
	/**
	 * Ensure the feature is active, perform an index, and remove test posts
	 * before running tests.
	 */
	before(() => {
		cy.maybeEnableFeature('facets');
		cy.wpCli( 'plugin activate vip-enable-facet-taxonomies' );
		cy.wpCli('vip-search index --setup --skip-confirm');
		cy.wpCli('post list --s="A new" --ep_integrate=false --format=ids').then(
			(wpCliResponse) => {
				if (wpCliResponse.stdout) {
					cy.wpCli(`post delete ${wpCliResponse.stdout} --force`);
				}
			},
		);
	});

	/**
	 * Delete all widgets and ensure Classic Widgets is deactivated before each
	 * test.
	 */
	beforeEach(() => {
		cy.emptyWidgets();
		cy.deactivatePlugin('classic-widgets', 'wpCli');
	});

	/**
	 * Test that the Facet widget is functional and can be transformed into the
	 * Facet block.
	 */
	it('Can insert, configure, use the legacy Facet widget', () => {
		/**
		 * Add the legacy widget.
		 */
		cy.activatePlugin('classic-widgets', 'wpCli');
		cy.createClassicWidget('ep-facet', [
			{
				name: 'title',
				value: 'My facet',
			},
			{
				name: 'facet',
				value: 'post_tag',
				type: 'select',
			},
			{
				name: 'orderby',
				value: 'name',
				type: 'select',
			},
			{
				name: 'order',
				value: 'asc',
				type: 'select',
			},
		]);

		/**
		 * Verify the widget has the expected output on the front-end based on
		 * the widget's settings.
		 */
		cy.visit('/');
		cy.get('.widget_ep-facet').first().as('widget');
		cy.get('@widget').find('input').should('have.attr', 'placeholder', 'Search Tags');
		cy.get('@widget').find('.terms').should('be.elementsSortedAlphabetically');
	});

	/**
	 * Test that the blog, taxonomy archives, and search only display the
	 * expected post types.
	 */
	it('Does not change post types being displayed', () => {
		cy.wpCli( 'plugin activate cpt-and-custom-tax' );
		cy.wpCli( 'term create genre action');
		cy.wpCliEval(
			`
			WP_CLI::runcommand( 'post create --post_title="A new page" --post_type="page" --post_status="publish"' );
			WP_CLI::runcommand( 'post create --post_title="A new post" --post_type="post" --post_status="publish"' );
			WP_CLI::runcommand( 'post create --post_title="A new post" --post_type="post" --post_status="publish"' );
			// tax_input does not seem to work properly in WP-CLI.
			$movie_id = wp_insert_post(
				[
					'post_title'  => 'A new movie',
					'post_type'   => 'movie',
					'post_status' => 'publish',
				]
			);
			if ( $movie_id ) {
				wp_set_object_terms( $movie_id, 'action', 'genre' );
				WP_CLI::runcommand( 'elasticpress index --include=' . $movie_id );
				WP_CLI::runcommand( 'rewrite flush' );
			}
			`,
		);

		// Blog page
		cy.visit('/');
		cy.contains('.site-content article h2', 'A new page').should('not.exist');
		cy.contains('.site-content article h2', 'A new post').should('exist');
		cy.contains('.site-content article h2', 'A new movie').should('not.exist');

		// Specific taxonomy archive
		cy.visit('/blog/genre/action/');
		cy.contains('.site-content article h2', 'A new page').should('not.exist');
		cy.contains('.site-content article h2', 'A new post').should('not.exist');
		cy.contains('.site-content article h2', 'A new movie').should('exist');

		// Search
		cy.visit('/?s=new');
		cy.contains('.site-content article h2', 'A new page').should('exist');
		cy.contains('.site-content article h2', 'A new post').should('exist');
		cy.contains('.site-content article h2', 'A new movie').should('exist');
	});
});
