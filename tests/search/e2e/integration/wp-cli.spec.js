/* global indexNames */

describe('WP-CLI Commands', () => {
	context('wp vip-search index', () => {
		it('Can index all the posts of the current blog', () => {
			cy.wpCli('wp vip-search index')
				.its('stdout')
				.should('contain', 'Indexing posts')
				.should('contain', 'Number of posts indexed');
		});

		it('Can clear the index in Elasticsearch, put the mapping again and then index all the posts if user specifies --setup argument', () => {
			cy.wpCli('wp vip-search index --setup --skip-confirm')
				.its('stdout')
				.should('contain', 'Mapping sent')
				.should('contain', 'Indexing posts')
				.should('contain', 'Number of posts indexed');

			cy.wpCli('wp vip-search stats')
				.its('stdout')
				.should('contain', 'Documents')
				.should('contain', 'Index Size');
		});

		it('Can process that many posts in bulk index per round if user specifies --per-page parameter', () => {
			cy.wpCli('wp vip-search index --per-page=20')
				.its('stdout')
				.should('contain', 'Indexing posts')
				.should('contain', '20 of')
				.should('contain', '40 of ')
				.should('contain', 'Number of posts indexed');
		});

		it('Can index all the posts of a type if user specify --post-type parameter', () => {
			let indexPerPostType = 0;
			let indexTotal = 0;

			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp vip-search index --post-type=post').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');

				const match = wpCliResponse.stdout.match(
					/Number of posts indexed: (?<indexed>\d+)/,
				);
				indexPerPostType = match.groups.indexed;
			});

			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp vip-search index').then((wpCliResponse) => {
				expect(wpCliResponse.stdout).to.contains('Indexing posts');

				const match = wpCliResponse.stdout.match(
					/Number of posts indexed: (?<indexed>\d+)/,
				);
				indexTotal = match.groups.indexed;

				expect(indexPerPostType).to.not.equal(indexTotal);
			});
		});
	});

	it('Cannot delete the index of current blog if user runs wp vip-search delete-index', () => {
		cy.wpCli('wp vip-search delete-index', true)
			.its('stderr')
			.should('contain', 'use index versioning to manage your indices');
	});

	it('Can put mapping of the current blog if user runs wp vip-search put-mapping', () => {
		cy.wpCli('wp vip-search put-mapping --skip-confirm')
			.its('stdout')
			.should('contain', 'Adding post mapping')
			.should('contain', 'Mapping sent');
	});

	it('Can activate and deactivate a feature', () => {
		cy.wpCli('wp vip-search activate-feature search', true)
			.its('stderr')
			.should('contain', 'This feature is already active');

		cy.wpCli('wp vip-search activate-feature users')
			.its('stdout')
			.should('contain', 'Feature activated');

		cy.wpCli('wp vip-search deactivate-feature users')
			.its('stdout')
			.should('contain', 'Feature deactivated');

		cy.wpCli('wp vip-search deactivate-feature users', true)
			.its('stderr')
			.should('contain', 'Feature is not active');

		cy.wpCli('wp vip-search activate-feature invalid', true)
			.its('stderr')
			.should('contain', 'No feature with that slug is registered');

		cy.wpCli('wp vip-search activate-feature woocommerce', true)
			.its('stderr')
			.should('contain', 'Feature requirements are not met');

		cy.maybeDisableFeature( 'protected_content' ); // If it was previously activated, we need to disable it.

		cy.wpCli('wp vip-search activate-feature protected_content', true)
			.its('stderr')
			.should('contain', 'This feature requires a re-index')
			.should('contain', 'Feature is usable but there are warnings');
	});

	it('Can list all the active features if user runs wp vip-search list-features command', () => {
		cy.wpCli('wp vip-search list-features')
			.its('stdout')
			.should('contain', 'Active features');
	});

	it('Can list all the registered features if user runs wp vip-search list-features --all command', () => {
		cy.wpCli('wp vip-search list-features --all')
			.its('stdout')
			.should('contain', 'Registered features');
	});

	it('Can return a string indicating the index is not running', () => {
		cy.wpCli('wp vip-search get-indexing-status')
			.its('stdout')
			.should(
				'contain',
				'{"indexing":false,"method":"none","items_indexed":0,"total_items":-1}',
			);
	});

	it('Can return a string indicating with the appropriate fields if user runs wp vip-search get-last-cli-index command', () => {
		cy.wpCli('wp vip-search index');

		cy.wpCli('wp vip-search get-last-cli-index')
			.its('stdout')
			.should('contain', '"total":');

		cy.wpCli('wp vip-search get-last-cli-index --clear')
			.its('stdout')
			.should('contain', '[]');
	});

	context('multisite parameters', () => {
		it('Can index all blogs in network if user specifies --network-wide argument', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli('wp vip-search index --network-wide')
				.its('stdout')
				.then((output) => {
					expect((output.match(/Indexing posts/g) || []).length).to.equal(2);
					expect(
						(output.match(/Number of posts indexed:/g) || []).length,
					).to.equal(2);
				});
		});

		it('Can index only current site if user does not specify --network-wide argument', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli(`wp vip-search index`)
				.its('stdout')
				.then((output) => {
					expect((output.match(/Indexing posts/g) || []).length).to.equal(1);
					expect(
						(output.match(/Number of posts indexed:/g) || []).length,
					).to.equal(1);
				});
		});

		it('Can index only site in the --url parameter if user does not specify --network-wide argument', () => {
			// eslint-disable-next-line jest/valid-expect-in-promise
			cy.wpCli(`wp vip-search index --url=${Cypress.config('baseUrl')}/second-site`)
				.its('stdout')
				.then((output) => {
					expect((output.match(/Indexing posts/g) || []).length).to.equal(1);
					expect(
						(output.match(/Number of posts indexed:/g) || []).length,
					).to.equal(1);
				});
		});
	});

	it('Can set the algorithm version', () => {
		cy.wpCli('wp vip-search set-algorithm-version --default')
			.its('stdout')
			.should('contain', 'Done');

		cy.wpCli('wp vip-search get-algorithm-version')
			.its('stdout')
			.should('contain', 'default');

		cy.wpCli('wp vip-search set-algorithm-version --version=1.0.0')
			.its('stdout')
			.should('contain', 'Done');

		cy.wpCli('wp vip-search get-algorithm-version').its('stdout').should('contain', '1.0.0');

		cy.wpCli('wp vip-search set-algorithm-version', true)
			.its('stderr')
			.should('contain', 'This command expects a version number or the --default flag');
	});

	it('Can get the mapping information', () => {
		cy.wpCli('wp vip-search get-mapping').its('stdout').should('contain', 'mapping_version');
	});

	it('Can get the cluster indexes information', () => {
		cy.wpCli('wp vip-search get-cluster-indexes').its('stdout').should('contain', 'health');
	});

	it('Can get the indexes names', () => {
		cy.wpCli('wp vip-search get-indexes').its('code').should('equal', 0);

		cy.wpCli('wp vip-search get-indexes').its('stdout').should('contain', '"vip-1234-post-1"');
	});

	it('Can stop the sync operation and clear it', () => {
		// if no index is running, this will fail.
		cy.wpCli('wp vip-search stop-indexing')
			.its('stderr')
			.should('contain', 'There is no indexing operation running');

		// mock the indexing process
		cy.wpCliEval(
			`update_option('ep_index_meta', [ 'method' => 'test' ]); set_transient('ep_sync_interrupted', true);`,
		);

		cy.wpCli('wp vip-search stop-indexing').its('stdout').should('contain', 'Done');

		cy.wpCli('wp vip-search clear-index').its('stdout').should('contain', 'Index cleared');
	});
});
