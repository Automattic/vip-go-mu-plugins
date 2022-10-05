describe('Terms Feature', () => {
	const tags = ['Far From Home', 'No Way Home', 'The Most Fun Thing'];

	before(() => {
		cy.visitAdminPage('edit-tags.php?taxonomy=post_tag');

		/**
		 * Delete all tags.
		 */
		tags.forEach((tag) => {
			cy.wpCli(
				`wp term delete post_tag $(wp term get post_tag -s='${tag}' --field=ids)`,
				true,
			);
		});
	});

	it('Can search a term in the admin dashboard using Elasticsearch', () => {
		cy.login();
		cy.maybeEnableFeature('terms');
		cy.wpCli('vip-search index --skip-confirm --setup');

		const searchTerm = 'search term';
		cy.createTerm({ name: searchTerm });

		cy.get('#tag-search-input').type(searchTerm);
		cy.get('#search-submit').click();

		cy.get('.wp-list-table tbody tr')
			.should('have.length', 1)
			.should('contain.text', searchTerm);

		// make sure elasticsearch result does contain the term.
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().click();
		cy.get('.line-numbers')
			.first()
			.should('contain.text', searchTerm);
		cy.get('#vip-search-dev-tools-mount').click();

		// Delete the term
		cy.get('.wp-list-table tbody tr')
			.first()
			.find('.row-actions .delete a')
			.click({ force: true });
	});

	it('Can a term be removed from the admin dashboard after deleting it', () => {
		cy.login();
		cy.maybeEnableFeature('terms');
		cy.wpCli('vip-search index --skip-confirm --setup');

		// Create a new term
		const term = 'amazing term';
		cy.createTerm({ name: term });

		// Search for the term
		cy.get('#tag-search-input').type(term);
		cy.get('#search-submit').click();
		cy.get('.wp-list-table tbody tr').should('have.length', 1).should('contain.text', term);

		// make sure elasticsearch result does contain the term.
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().click();
		cy.get('.line-numbers')
			.first()
			.should('contain.text', term);
		cy.get('#vip-search-dev-tools-mount').click();

		// Delete the term
		cy.get('.wp-list-table tbody tr')
			.first()
			.find('.row-actions .delete a')
			.click({ force: true });
		cy.reload();

		// Re-search for the term and make sure it's not there.
		cy.get('#search-submit').click();
		cy.get('.wp-list-table tbody').should('contain.text', 'No categories found');
	});

	it('Can return a correct tag on searching a tag in admin dashboard', () => {
		cy.login();
		cy.maybeEnableFeature('terms');
		cy.wpCli('vip-search index --skip-confirm --setup');

		cy.visitAdminPage('edit-tags.php?taxonomy=post_tag');

		// create tags.
		tags.forEach((tag) => {
			cy.createTerm({ name: tag, taxonomy: 'post_tag' });
		});

		// search for the tag.
		cy.get('#tag-search-input').type('the most fun thing');
		cy.get('#search-submit').click();

		cy.get('.wp-list-table tbody tr .row-title').should('contain.text', 'The Most Fun Thing');

		// make sure elasticsearch result does contain the term.
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().click();
		cy.get('.line-numbers')
			.first()
			.should('contain.text', 'The Most Fun Thing');
		cy.get('#vip-search-dev-tools-mount').click();
	});

	it('Can update a child term when a parent term is deleted', () => {
		cy.login();
		cy.maybeEnableFeature('terms');
		cy.wpCli('vip-search index --skip-confirm --setup');

		const parentTerm = 'bar-parent';
		const childTerm = 'baz-child';

		cy.createTerm({ name: parentTerm });
		cy.createTerm({ name: childTerm, parent: parentTerm });

		cy.get('#tag-search-input').type(`${parentTerm}{enter}`);

		// delete the parent term.
		cy.intercept('POST', 'wp-admin/admin-ajax.php*').as('ajaxRequest');
		cy.get('.wp-list-table tbody tr')
			.first()
			.find('.row-actions .delete a')
			.click({ force: true });
		cy.wait('@ajaxRequest').its('response.statusCode').should('eq', 200);

		// make sure the child term parent field is set to none.
		cy.get('#tag-search-input').clear().type(`${childTerm}{enter}`);
		cy.get('.wp-list-table tbody tr .column-primary a').first().click();
		cy.get('#parent').should('have.value', '-1');

		// delete the child term.
		cy.get('#delete-link a').click();
	});
});
