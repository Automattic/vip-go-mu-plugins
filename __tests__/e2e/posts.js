/** External Dependencies **/
const fetch = require( 'node-fetch' );
const uuid = require( 'uuid' );
const wait = require( 'waait' );
const faker = require( 'faker' );

/** Variables **/
const USER_LOGIN = process.env.BASICAUTH_LOGIN || 'wordpress';
const USER_PASSWORD = process.env.BASICAUTH_PASSWORD || 'wordpress';
const WP_URL = process.env.WPURL || 'http://localhost';

function generatePostBody( { title, status, content, excerpt, tags, categories, meta } ) {
	return JSON.stringify( {
		'title': title || 'Hello from the E2E',
		'status': status || 'publish',
		'content': content || 'API testing',
		'excerpt': excerpt || 'This post was sent from E2E testing',
		'tags': tags || null,
		'categories': categories || null,
		'meta': meta || null,
	} );
}

const createBasicAuthFetcher = ( login, password ) => {
	const base64LoginAndPassword = ( new Buffer( `${ login }:${ password }` ) ).toString('base64');

	return ( url, method, body ) => {
		return fetch( url, {
			method: method,
			body: body,
			headers: { 'Content-Type': 'application/json', 'Authorization': `Basic ${ base64LoginAndPassword }` },
		} );
	}
};

const createRequest = createBasicAuthFetcher( USER_LOGIN, USER_PASSWORD );

const createNewPost = body => {
	return createRequest( WP_URL + '/wp-json/wp/v2/posts', 'post', body );
}

const updatePost = ( postId, body)  => {
	return createRequest( WP_URL + '/wp-json/wp/v2/posts/' + postId, 'post', JSON.stringify( body ) );
}


const deletePost = postId => {
	return createRequest( WP_URL + '/wp-json/wp/v2/posts/' + postId, 'delete' );
}

const createNewTag = body => {
	return createRequest( WP_URL + '/wp-json/wp/v2/tags', 'post', JSON.stringify( body ) );
}

const createNewCategory = body => {
	return createRequest( WP_URL + '/wp-json/wp/v2/categories', 'post', JSON.stringify( body ) );
}

// Create a unique string to use as a specific search term
const getUniqueString = () => {
	return faker.lorem.words( 6 ).replace(/\W/g, "");
}

describe( 'Post And Search', () => {
	it( 'should post a new post and find it using ES search', async () => {
		const uniqueString = await getUniqueString();

		// Post a new article
		await createNewPost( generatePostBody( { content: 'API testing- specific search term in content ' + uniqueString } ) );
		await wait( 1000 );
		const { _headers : responseHeaders } = await page.goto( WP_URL + '/?exact=1&ep_debug&s=' + uniqueString );
		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 1 );
		expect( responseHeaders[ 'x-elasticpress-search-valid-response' ] ).toBe( 'true' );
	}, 10000 );

	it( 'should post a new post, delete it, and double check it is not showing in search', async () => {
		const uniqueString = await getUniqueString();

		const response = await createNewPost( generatePostBody( {
			content: 'API testing- specific search term in content. This post should be deleted. ' + uniqueString
		} ) ).then( res => res.json() );

		await deletePost( response.id );
		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );

		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 0 );
	}, 10000 );

	// Unique search term in `title` of the post
	it( 'should generate a new post with a unique search term in the title and find it using ES', async () => {
		const uniqueString = await getUniqueString();

		// Create a new post/article that contains a specific search term in the 'title' of the post body
		await createNewPost( generatePostBody( {
			content: 'API testing- specific search term in title.',
			title: 'Unique title ' + uniqueString,
		} ) );
		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );
		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 1 );
	}, 10000 );

	// Delete the post with the unique search term in the title
	it( 'should post a new post w/ the search term in the title, delete it, and verify that it is not showing in search', async () => {
		const uniqueString = await getUniqueString();

		// Create a new post/article that contains a specific search term in the 'title' of the post body
		const response = await createNewPost( generatePostBody( {
			content: 'API testing- specific search term in title. This post should be deleted.',
			title: 'Unique title ' + uniqueString,
		} ) ).then( res => res.json() );

		// Delete the post
		await deletePost( response.id );
		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );
		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 0 );
	}, 10000 );

	// Unique search term in `excerpt` of the post
	it( 'should generate a new post with a unique search term in the excerpt and find it using ES', async () => {
		const uniqueString = await getUniqueString();

		// Create a new post/article that contains a specific search term in the 'excerpt' of the post body
		await createNewPost( generatePostBody( {
			content: 'API testing- specific search term in excerpt',
			excerpt: 'Unique excerpt ' + uniqueString,
		} ) );

		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );
		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 1 );
	}, 10000 );

	// Delete the post with a unique search term in the `excerpt`
	it( 'should post a new post w/ the search term in the excerpt, delete it, and verify that it is not showing in search', async () => {
		const uniqueString = await getUniqueString();

		// Create a new post/article that contains a specific search term in the 'excerpt' of the post body
		const response = await createNewPost( generatePostBody( {
			content: 'API testing- specific search term in excerpt. This post should be deleted.',
			excerpt: 'Unique excerpt ' + uniqueString,
		} ) ).then( res => res.json() );

		// Delete the post
		await deletePost( response.id );
		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );

		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 0 );
	}, 10000 );

	// Unique search term in 'tags'
	it( 'should generate a new post with a unique search term in the tags and find it using ES', async () => {
		const uniqueString = await getUniqueString();

		// Create a new tag
		const response = await createNewTag( {
			'name': uniqueString,
			'description': 'This is a test tag',
		} );

		const tag = await response.json();

		// Create a new post/article that contains a specific search term in the 'tags' of a post
		await createNewPost( generatePostBody( {
			ontent: 'API testing- specific search term in tags',
			tags: [ tag.id ],
		} ) );

		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );
		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 1 );
	}, 10000 );

	// Delete post with unique search term 'tags'
	it( 'should create a new post with a unique tag, delete it, then verify it does not show in search', async () => {
		const uniqueString = await getUniqueString();

		// Create a new tag
		const response = await createNewTag( {
			'name': uniqueString,
			'description': 'This is a test tag',
		} );

		const tag = await response.json();

		// Create a new post/article that contains a specific search term in the 'tags' of a post
		const res = await createNewPost( generatePostBody( {
			content: 'API testing- specific search term in tags. This post should be deleted.',
			tags: [ tag.id ],
		} ) ).then( res => res.json() );

		await deletePost( res.id );
		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );

		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 0 );
	}, 10000 );

	// Unique search term in 'categories'
	it( 'should generate a new post with a unique search term category and find it using ES', async () => {
		const uniqueString = await getUniqueString();

		// Create a new category
		const response = await createNewCategory( {
			'name': uniqueString,
			'description': 'This is a test category',
		} );

		const category = await response.json();

		// Create a new post/article that contains a specific search term category for a post
		await createNewPost( generatePostBody( {
			content: 'API testing- specific search term in category',
			categories: [ category.id ],
		} ) );

		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );
		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 1 );
	}, 10000 );

	// Delete post in unique search term 'categories'
	it( 'should create a category, generate a new post in the category, delete it, then verify it does not show in search', async () => {
		const uniqueString = await getUniqueString();

		// Create a new category
		const response = await createNewCategory( {
			'name': uniqueString,
			'description': 'This is a test category',
		} );

		const category = await response.json();

		// Create a new post/article that contains a specific search term category for a post
		const res = await createNewPost( generatePostBody( {
			content: 'API testing- specific search term in category. This post should be deleted.',
			categories: [ category.id ],
		} ) );

		const post = await res.json();

		// Delete the post
		await deletePost( post.id );
		await wait( 1000 );
		await page.goto( WP_URL + '/?exact=1&s=' + uniqueString );

		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 0 );
	}, 10000 );

	it( 'should index a post after it\'s been switched from draft to published', async () => {
		const uniqueString = await getUniqueString();
		// Create a new post/article that contains a specific search term in the 'title' of the post body
		const post = await createNewPost( generatePostBody( {
			content: 'API testing- Draft Post to published',
			title: 'Unique title ' + uniqueString,
			status: 'draft'
		} ) ).then( res => res.json() );

		await wait( 1000 );
		const { _headers : responseHeaders } = await page.goto( WP_URL + '/?exact=1&ep_debug&s=' + uniqueString );
		const articles = await page.$$( 'article' );

		expect( responseHeaders[ 'x-elasticpress-search-valid-response' ] ).toBe( 'true' );
		expect( articles.length ).toBe( 0 );

		//Change the post to Published and it should now appear in the search
		await updatePost( post.id, { status: 'publish' } );

		await wait( 1000 );
		//Note the cachebust param here, as we need to hae this URL be different from the above one.
		const { _headers : updatedResponseHeaders } = await page.goto( WP_URL + '/?exact=1&ep_debug&s=' + uniqueString + '&cachebust' );
		const articlePublished = await page.$$( 'article' );

		expect( updatedResponseHeaders[ 'x-elasticpress-search-valid-response' ] ).toBe( 'true' );
		expect( articlePublished.length ).toBe( 1 );
	}, 10000 );

	it( 'should find a post by unique meta ', async () => {
		const uniqueString = await getUniqueString();
		const uniqueMeta = await getUniqueString();

		// Create a new post/article that contains a specific search term in the 'title' of the post body
		const post = await createNewPost( generatePostBody( {
			content: 'API testing- Test searching for post meta',
			title: 'Unique title ' + uniqueString,
			meta: { e2e_test: uniqueMeta }
		} ) ).then( res => res.json() );

		await wait( 1000 );
		const { _headers : responseHeaders } =  await page.goto( WP_URL + '/?exact=1&s=Unique&ep_debug&e2e_test_meta=' + uniqueMeta );
		const articles = await page.$$( 'article' );

		expect( articles.length ).toBe( 1 );
		expect( responseHeaders[ 'x-elasticpress-search-valid-response' ] ).toBe( 'true' );

	}, 10000 );

	it( 'should remove meta from a post and check that it\'s no longer indexed ', async () => {
		const uniqueString = await getUniqueString();
		const uniqueMeta = await getUniqueString();

		// Create a new post/article that contains a specific search term in the 'title' of the post body
		const post = await createNewPost( generatePostBody( {
			content: 'API testing- Test searching for post meta',
			title: 'Unique title ' + uniqueString,
			meta: { e2e_test: uniqueMeta }
		} ) ).then( res => res.json() );
		await wait( 1000 );
		const { _headers : responseHeaders } =  await page.goto( WP_URL + '/?exact=1&s='+ uniqueString +'&ep_debug&e2e_test_meta=' + uniqueMeta );
		const articles = await page.$$( 'article' );

		expect( responseHeaders[ 'x-elasticpress-search-valid-response' ] ).toBe( 'true' );
		expect( articles.length ).toBe( 1 );

		//Remove the meta
		await updatePost( post.id, { meta: { e2e_test: null } } );

		await wait( 1000 );
		const { _headers : responseHeadersDeletedMeta } =  await page.goto( WP_URL + '/?exact=1&s='+ uniqueString +'&ep_debug&e2e_test_meta=' + uniqueMeta + '&cachebust' );
		const articleDeletedMeta = await page.$$( 'article' );
		expect( articleDeletedMeta.length ).toBe( 0 );
		expect( responseHeadersDeletedMeta[ 'x-elasticpress-search-valid-response' ] ).toBe( 'true' );

	}, 10000 );
} );
