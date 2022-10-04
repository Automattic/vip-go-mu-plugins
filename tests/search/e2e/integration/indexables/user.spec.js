describe('User Indexable', () => {
	function createUser(userData) {
		const newUserData = {
			userLogin: 'testuser',
			userEmail: 'testuser@example.com',
			...userData,
		};

		cy.wpCli(`wp user get ${newUserData.userLogin} --field=ID`, true).then((wpCliResponse) => {
			if (wpCliResponse.code === 0) {
				cy.wpCli(`wp user delete ${newUserData.userLogin} --yes --network`);
				cy.wpCli('wp vip-search index --setup --skip-confirm');
			}
		});

		cy.visitAdminPage('user-new.php');
		cy.get('#user_login').clearThenType(newUserData.userLogin);
		cy.get('#email').clearThenType(newUserData.userEmail);
		cy.get('#noconfirmation').check();
		cy.get('#createusersub').click();
		cy.get('#message').should('be.visible');
	}

	function searchUser(userName = 'testuser') {
		cy.visitAdminPage('users.php');
		cy.get('#user-search-input').clearThenType(userName);
		cy.get('#search-submit').click();
	}

	after(() => {
		cy.maybeDisableFeature('users');
	});

	it('Can run a simple user sync', () => {
		cy.login();

		cy.maybeEnableFeature('users');

		createUser();

		searchUser();

		cy.get('.wp-list-table').should('contain.text', 'testuser@example.com');
		cy.getTotal(1);
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
		cy.get('.line-numbers').first().should('contain.text','"user_email": "testuser@example.com"');
		cy.get('#vip-search-dev-tools-mount').click();

		// Test if the user is still found a reindex.
		cy.wpCli('vip-search index --setup --skip-confirm').its('stdout').should('contain', 'Number of users indexed: 2');

		searchUser();

		cy.get('.wp-list-table').should('contain.text', 'testuser@example.com');
		cy.getTotal(1);
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
		cy.get('.line-numbers').first().should('contain.text','"user_email": "testuser@example.com"');
	});

	it('Can sync user meta data', () => {
		cy.login();

		cy.maybeEnableFeature('users');

		createUser();

		searchUser();

		cy.get('#the-list .column-username .edit a').click({ force: true });
		cy.get('#first_name').clearThenType('John');
		cy.get('#last_name').clearThenType('Doe');
		cy.get('#submit').click();

		searchUser();

		cy.get('.wp-list-table').should('contain.text', 'testuser@example.com');
		cy.getTotal(1);
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
		// eslint-disable-next-line jest/valid-expect-in-promise
		cy.get('.line-numbers').first().invoke('text').then((text) => {
			expect(text).to.contain('"user_email": "testuser@example.com"');
			expect(text).to.contain('"value": "John"');
			expect(text).to.contain('"value": "Doe"');
		});
	});

	it('Only returns users from the current blog', () => {
		cy.login();

		cy.maybeEnableFeature('users');

		const newUserData = {
			userLogin: 'nobloguser',
			userEmail: 'no-blog-user@example.com',
		};

		cy.wpCli(`wp user get ${newUserData.userLogin} --field=ID`, true).then((wpCliResponse) => {
			if (wpCliResponse.code === 0) {
				cy.wpCli(`wp user delete ${newUserData.userLogin} --yes --network`);
				cy.wpCli('vip-search index --setup --skip-confirm --network-wide');
			}
		});

		// Create a user without a blog.
		cy.visitAdminPage('network/user-new.php');
		cy.get('#username').clearThenType(newUserData.userLogin);
		cy.get('#email').clearThenType(newUserData.userEmail);
		cy.get('#add-user').click();
		cy.get('#message.updated').should('be.visible');

		// Searching for it should not return anything.
		searchUser('nobloguser');
		cy.get('.wp-list-table').should('contain.text', 'No users found.');
		cy.getTotal(0);
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');

		// Add user to the blog.
		cy.visitAdminPage('user-new.php');
		cy.get('#adduser-email').clearThenType(newUserData.userLogin);
		cy.get('#adduser-noconfirmation').check();
		cy.get('#addusersub').click();
		cy.get('#message.updated').should('be.visible');

		// Searching for it should return it.
		searchUser('nobloguser');
		cy.get('.wp-list-table').should('contain.text', 'nobloguser');
		cy.getTotal(1);
		cy.get('#vip-search-dev-tools-mount').click();
		cy.get('h3.vip-h3').first().should('contain.text','(200)');
		cy.get('.line-numbers').first().should('contain.text','"user_email": "no-blog-user@example.com"');
	});
});
