describe('Protected Content Feature', () => {
	before(() => {
		cy.maybeEnableFeature('protected_content');
		cy.wpCli('vip-search index --setup --skip-confirm');
	});

	it('Can use Elasticsearch in the Posts List Admin Screen', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');

		cy.visitAdminPage('edit.php');
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').should('contain.text', 'results that took');
	});

	it('Can use Elasticsearch in the Draft Posts List Admin Screen', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');

		// Delete previous drafts, so we can be sure we just expect 1 draft post.
		cy.wpCli('post list --post_status=draft --format=ids').then((wpCliResponse) => {
			if (wpCliResponse.stdout !== '') {
				cy.wpCli(`post delete ${wpCliResponse.stdout}`);
			}
		});

		cy.wpCli('vip-search index --setup --skip-confirm');

		cy.publishPost({
			title: 'Test ElasticPress Draft',
			status: 'draft',
		});

		cy.visitAdminPage('edit.php?post_status=draft&post_type=post');
		cy.getTotal(1);
	});

	it('Can sync autosaved drafts', () => {
		cy.login();

		cy.maybeEnableFeature('protected_content');

		// Delete previous drafts, so we can be sure we just expect 1 draft post.
		cy.wpCli('post list --post_status=draft --format=ids').then((wpCliResponse) => {
			if (wpCliResponse.stdout !== '') {
				cy.wpCli(`post delete ${wpCliResponse.stdout}`);
			}
		});

		cy.wpCli('vip-search index --setup --skip-confirm');

		cy.createAutosavePost();

		cy.visitAdminPage('edit.php?post_status=draft&post_type=post');
		cy.getTotal(1);
	});
});
