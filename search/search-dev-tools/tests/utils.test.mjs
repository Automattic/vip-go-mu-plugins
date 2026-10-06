/**
 * Unit tests for the Search Dev Tools view-model helpers.
 * Run with `npm test` (Node's built-in test runner).
 */
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import { buildTreeLines, childPath, hitIndex } from '../src/tree-lines.js';
import {
	countHits,
	countLines,
	describeJsonSize,
	escapeHtml,
	formatSize,
	isLargeRequest,
	LARGE_JSON_CHARS,
	LARGE_JSON_LINES,
	LARGE_REQUEST_CHARS,
	describeQuery,
	findCallerIndex,
	formatDuration,
	hitsPerIndex,
	isDraftEdited,
	parseFrame,
	queryLabel,
	speedClass,
	summarizeResult,
} from '../src/utils.js';

const MAIN_QUERY_TRACE = [
	String.raw`wp-content/mu-plugins/search/elasticpress/includes/classes/Elasticsearch.php:372 ElasticPress\Elasticsearch->remote_request()`,
	String.raw`wp-includes/class-wp-hook.php:341 ElasticPress\Indexable\Post\QueryIntegration->get_es_posts()`,
	'wp-includes/class-wp-query.php:3958 WP_Query->get_posts()',
	'wp-includes/class-wp.php:704 WP_Query->query()',
	'wp-includes/class-wp.php:824 WP->query_posts()',
	'index.php:17 require(\'wp-blog-header.php\')',
];

const PLUGIN_TRACE = [
	String.raw`wp-content/mu-plugins/search/elasticpress/includes/classes/Elasticsearch.php:372 ElasticPress\Elasticsearch->remote_request()`,
	'wp-includes/class-wp-query.php:3958 WP_Query->get_posts()',
	'wp-content/client-mu-plugins/demo.php:16 WP_Query->__construct()',
];

const okBody = ( total, returned = total ) => ( {
	took: 3,
	hits: { total: { value: total, relation: 'eq' }, hits: Array.from( { length: returned }, () => ( {} ) ) },
} );

describe( 'parseFrame', () => {
	it( 'splits file, line and call', () => {
		assert.deepEqual( parseFrame( 'wp-includes/class-wp.php:704  WP_Query->query()' ), { file: 'wp-includes/class-wp.php', line: '704', call: 'WP_Query->query()' } );
		assert.deepEqual( parseFrame( 'index.php require()' ), { file: 'index.php', line: '', call: 'require()' } );
	} );

	it( 'keeps frames without a PHP file as the call', () => {
		assert.deepEqual( parseFrame( 'Closure->__invoke()' ), { file: '', line: '', call: 'Closure->__invoke()' } );
		assert.deepEqual( parseFrame( 'file.php:12:3 call()' ), { file: '', line: '', call: 'file.php:12:3 call()' } );
	} );
} );

describe( 'findCallerIndex', () => {
	it( 'falls back to the core caller for the main query', () => {
		assert.equal( findCallerIndex( MAIN_QUERY_TRACE ), 3 );
	} );
} );

describe( 'formatDuration', () => {
	it( 'formats ms and seconds, "<1 ms" instead of "0 ms", and a dash when missing', () => {
		assert.equal( formatDuration( 0 ), '<1 ms' );
		assert.equal( formatDuration( 0.4 ), '<1 ms' );
		assert.equal( formatDuration( 7 ), '7 ms' );
		assert.equal( formatDuration( 1234 ), '1.23 s' );
		assert.equal( formatDuration( null ), '—' );
	} );
} );

describe( 'speedClass', () => {
	it( 'buckets by speed', () => {
		assert.equal( speedClass( 50 ), 'good' );
		assert.equal( speedClass( 300 ), 'warn' );
		assert.equal( speedClass( 900 ), 'bad' );
	} );

	it( 'keeps failed queries neutral so "failed" carries the red', () => {
		assert.equal( speedClass( 4, true ), 'neutral' );
	} );
} );

describe( 'summarizeResult', () => {
	it( 'labels the matched total, not the returned page', () => {
		assert.equal( summarizeResult( okBody( 7, 3 ), { code: 200 } ).hitsLabel, '7 hits' );
		assert.equal( summarizeResult( okBody( 7, 0 ), { code: 200 } ).hitsLabel, '7 hits' );
		assert.equal( summarizeResult( okBody( 1 ), { code: 200 } ).hitsLabel, '1 hit' );
	} );

	it( 'flags failures and extracts the Elasticsearch reason', () => {
		const body = { error: { root_cause: [ { type: 'query_shard_exception', reason: 'No mapping found for [x]' } ] }, status: 400 };
		const summary = summarizeResult( body, { code: 400, message: 'Bad Request' } );
		assert.equal( summary.failed, true );
		assert.equal( summary.hitsLabel, 'failed' );
		assert.equal( summary.errorReason, 'query_shard_exception: No mapping found for [x]' );
	} );

	it( 'treats REST error payloads from a re-run as failures', () => {
		const summary = summarizeResult( { code: 'rest_cookie_invalid_nonce', message: 'Cookie check failed' } );
		assert.equal( summary.failed, true );
		assert.equal( summary.errorReason, 'Cookie check failed' );
	} );
} );

describe( 'queryLabel', () => {
	it( 'names the main search', () => {
		assert.equal( queryLabel( { query_args: { s: 'hello' }, backtrace: MAIN_QUERY_TRACE }, 0 ), 'Main search “hello”' );
	} );

	it( 'names post type queries', () => {
		assert.equal( queryLabel( { query_args: { post_type: 'page' }, backtrace: PLUGIN_TRACE }, 2 ), 'Page query' );
	} );

	it( 'falls back to the position', () => {
		assert.equal( queryLabel( { query_args: {}, backtrace: PLUGIN_TRACE }, 4 ), 'Query 5' );
	} );
} );

describe( 'describeQuery', () => {
	const query = ( url, extra = {} ) => ( {
		url,
		args: { body: { size: 10 } },
		request: { body: okBody( 2 ), response: { code: 200, message: 'OK' } },
		query_args: { s: 'hello' },
		backtrace: PLUGIN_TRACE,
		...extra,
	} );

	it( 'shortens the index name and treats one index as single-site', () => {
		const meta = describeQuery( query( 'https://es:9200/vip-200508-post-1/_search' ), 0 );
		assert.equal( meta.indexLabel, 'post-1' );
		assert.equal( meta.crossSite, false );
		assert.equal( meta.sitesLabel, '' );
		assert.deepEqual( meta.queriedIndexes, [ 'vip-200508-post-1' ] );
	} );

	it( 'lists every index of a cross-site query', () => {
		const meta = describeQuery( query( 'https://es:9200/vip-200508-post-2,vip-200508-post-3/_search', { query_args: { s: 'hello', sites: [ 2, 3 ] }, cross_site: true } ), 0 );
		assert.equal( meta.crossSite, true );
		assert.equal( meta.indexLabel, 'post-2, post-3' );
		assert.equal( meta.sitesLabel, '2 sites' );
		assert.equal( meta.indexTitle, 'Index: vip-200508-post-2, vip-200508-post-3' );
		assert.deepEqual( meta.queriedIndexes, [ 'vip-200508-post-2', 'vip-200508-post-3' ] );
	} );

	it( 'compacts long alias resolutions', () => {
		const alias = describeQuery( query( 'https://es:9200/vip-1-post-all/_search', {
			alias_indexes: [ 'vip-1-post-1', 'vip-1-post-2', 'vip-1-post-3', 'vip-1-post-4', 'vip-1-post-5' ],
		} ), 0 );
		assert.equal( alias.indexLabel, 'post-1 +4 via post-all' );
		assert.equal( alias.sitesLabel, '5 sites' );
	} );

	it( 'says how far a network alias reached', () => {
		const meta = describeQuery( query( 'https://es:9200/vip-200508-post-all/_search', {
			query_args: { s: 'hello', sites: 'all' },
			cross_site: true,
			alias_indexes: [ 'vip-200508-post-1', 'vip-200508-post-2', 'vip-200508-post-3-v2' ],
		} ), 0 );
		assert.equal( meta.crossSite, true );
		// The alias is not an index: lead with the real indexes it resolved to.
		assert.equal( meta.indexLabel, 'post-1, post-2, post-3-v2 via post-all' );
		assert.equal( meta.sitesLabel, '3 sites' );
		assert.equal( meta.indexTitle, 'Alias vip-200508-post-all → indexes vip-200508-post-1, vip-200508-post-2, vip-200508-post-3-v2' );
		assert.deepEqual( meta.queriedIndexes, [ 'vip-200508-post-1', 'vip-200508-post-2', 'vip-200508-post-3-v2' ] );
	} );

	it( 'uses the backend\'s cross-site decision', () => {
		// One index in the URL, but it is another site's: the backend knows it left the current site.
		assert.equal( describeQuery( query( 'https://es:9200/vip-1-post-2/_search', { query_args: { s: 'hello', sites: 2 }, cross_site: true } ), 0 ).crossSite, true );
	} );

	it( 'falls back to the request URL when the backend decision is missing', () => {
		assert.equal( describeQuery( query( 'https://es:9200/vip-1-post-2,vip-1-post-3/_search' ), 0 ).crossSite, true );
		assert.equal( describeQuery( query( 'https://es:9200/vip-1-post-1/_search' ), 0 ).crossSite, false );
	} );

	it( 'shows the caller path relative to wp-content', () => {
		assert.equal( describeQuery( query( 'https://es:9200/vip-1-post-1/_search' ), 0 ).caller, 'client-mu-plugins/demo.php:16' );
	} );

	it( 'falls back to wall-clock time when a failed request has no took', () => {
		const failed = query( 'https://es:9200/vip-1-post-1/_search', {
			request: { body: { error: {} }, response: { code: 'timeout', message: 'Request failure' } },
			time_start: 1,
			time_finish: 1.004,
		} );
		assert.equal( describeQuery( failed, 0 ).summary.took, 4 );
	} );
} );

describe( 'hitsPerIndex', () => {
	const body = { hits: { hits: [ { _index: 'vip-1-post-2' }, { _index: 'vip-1-post-2' }, { _index: 'vip-1-post-3' } ] } };

	it( 'counts returned hits per index in query order, including indexes that returned nothing', () => {
		assert.deepEqual( hitsPerIndex( body, [ 'vip-1-post-1', 'vip-1-post-2', 'vip-1-post-3' ] ), [
			{ index: 'vip-1-post-1', label: 'post-1', count: 0 },
			{ index: 'vip-1-post-2', label: 'post-2', count: 2 },
			{ index: 'vip-1-post-3', label: 'post-3', count: 1 },
		] );
	} );

	it( 'counts hits without a recognizable index as other, so the counts add up', () => {
		const rows = hitsPerIndex( { hits: { hits: [ { _index: 'vip-1-post-2' }, {}, { _index: '' } ] } }, [ 'vip-1-post-2' ] );
		assert.deepEqual( rows, [
			{ index: 'vip-1-post-2', label: 'post-2', count: 1 },
			{ index: '', label: 'other', count: 2 },
		] );
		assert.equal( rows.reduce( ( sum, row ) => sum + row.count, 0 ), 3 );
	} );
} );

describe( 'countHits', () => {
	// The object form of `total` is covered by summarizeResult above.
	it( 'reads the plain-number form of the total', () => {
		assert.deepEqual( countHits( { hits: { total: 3, hits: [ {}, {}, {} ] } } ), { total: 3, returned: 3 } );
	} );
} );

describe( 'isDraftEdited', () => {
	const original = '{\n  "size": 10\n}';

	it( 'is false without a draft or when re-running the untouched request', () => {
		assert.equal( isDraftEdited( undefined, original ), false );
		assert.equal( isDraftEdited( { text: original, ranText: original, result: {} }, original ), false );
	} );

	it( 'is true for unrun edits or results from an edited request', () => {
		assert.equal( isDraftEdited( { text: '{}' }, original ), true );
		assert.equal( isDraftEdited( { text: original, ranText: '{}', result: {} }, original ), true );
	} );
} );

describe( 'large JSON handling', () => {
	it( 'counts lines', () => {
		assert.equal( countLines( '' ), 1 );
		assert.equal( countLines( '{\n  "a": 1\n}' ), 3 );
	} );

	it( 'keeps small responses as a tree', () => {
		assert.equal( describeJsonSize( { took: 3, hits: { hits: [] } } ).large, false );
	} );

	it( 'flags responses over the line limit and reports their compact size', () => {
		const value = Array.from( { length: LARGE_JSON_LINES }, ( _, idx ) => idx );
		const size = describeJsonSize( value );
		assert.equal( size.large, true );
		assert.equal( size.size, JSON.stringify( value ).length );
		assert.ok( size.size < size.text.length, 'compact size is smaller than the pretty-printed text' );
	} );

	it( 'flags responses over the size limit', () => {
		assert.equal( describeJsonSize( { blob: 'x'.repeat( LARGE_JSON_CHARS ) } ).large, true );
	} );

	it( 'skips request highlighting only above the threshold', () => {
		assert.equal( isLargeRequest( 'x'.repeat( LARGE_REQUEST_CHARS ) ), false );
		assert.equal( isLargeRequest( 'x'.repeat( LARGE_REQUEST_CHARS + 1 ) ), true );
	} );

	it( 'escapes plain request text for the editor', () => {
		assert.equal( escapeHtml( '{"a":"<b>&</b>"}' ), '{"a":"&lt;b&gt;&amp;&lt;/b&gt;"}' );
	} );

	it( 'formats sizes', () => {
		assert.equal( formatSize( 512 ), '512 B' );
		assert.equal( formatSize( 300 * 1024 ), '300 KB' );
		assert.equal( formatSize( 13.3 * 1024 * 1024 ), '13.3 MB' );
	} );
} );

describe( 'buildTreeLines', () => {
	const response = {
		took: 3,
		_shards: { total: 1, successful: 1, skipped: 0, failed: 0 },
		hits: { total: { value: 3, relation: 'eq' }, hits: [ { _id: '1' }, { _id: '2' }, { _id: '3' } ] },
	};
	const hit = idx => childPath( childPath( childPath( '$', 'hits' ), 'hits' ), idx );
	const shards = childPath( '$', '_shards' );
	const collapsedPaths = lines => lines.filter( line => line.collapsed ).map( line => line.path );

	it( 'folds _shards and every hit after the first by default', () => {
		assert.deepEqual( collapsedPaths( buildTreeLines( response ).lines ), [ shards, hit( 1 ), hit( 2 ) ] );
	} );

	it( 'honours manual toggles over the defaults', () => {
		const { lines } = buildTreeLines( response, { overrides: { [ shards ]: false, [ hit( 0 ) ]: true } } );
		assert.deepEqual( collapsedPaths( lines ), [ hit( 0 ), hit( 1 ), hit( 2 ) ] );
	} );

	it( 'keeps only the top level open when collapsed', () => {
		assert.equal( buildTreeLines( response, { mode: 'collapsed' } ).lines.length, 5 );
	} );

	it( 'gives every line, and every part within a line, a unique render key, even for dotted keys', () => {
		const value = { ...response, empty: {}, list: [ 1, [], { a: null } ], 'a.b': { x: 1 }, a: { b: { y: 2 } } };
		for ( const { lines } of [ buildTreeLines( value, { mode: 'expanded' } ), buildTreeLines( value, { mode: 'collapsed', annotate: () => 'note' } ) ] ) {
			assert.equal( new Set( lines.map( line => line.key ) ).size, lines.length );
			for ( const line of lines ) {
				assert.equal( new Set( line.parts.map( part => part[ 2 ] ) ).size, line.parts.length, JSON.stringify( line.parts ) );
			}
		}
		const paths = buildTreeLines( value, { mode: 'expanded' } ).lines.map( line => line.path );
		assert.ok( paths.includes( '$["a.b"]' ) && paths.includes( '$["a"]["b"]' ) );
	} );

	it( 'records each node\'s parent path for keyboard navigation', () => {
		const { lines } = buildTreeLines( { 'post_type.raw': { buckets: [ { key: 'post' } ] } }, { mode: 'expanded' } );
		const buckets = lines.find( line => line.path === '$["post_type.raw"]["buckets"]' );
		assert.equal( buckets.parent, '$["post_type.raw"]' );
	} );

	it( 'recognizes top-level hits only', () => {
		assert.equal( hitIndex( hit( 4 ) ), 4 );
		assert.equal( hitIndex( childPath( hit( 4 ), '_source' ) ), -1 );
		assert.equal( hitIndex( shards ), -1 );
	} );

	it( 'stops building once the line limit is exceeded, even folded', () => {
		const buckets = Array.from( { length: 15000 }, ( _unused, idx ) => ( { key: `tag-${ idx }`, doc_count: idx } ) );
		const value = { aggregations: { tags: { buckets } } };
		assert.ok( buildTreeLines( value ).lines.length > LARGE_JSON_LINES, 'folded view really is that large' );

		const { lines, truncated } = buildTreeLines( value, { maxLines: LARGE_JSON_LINES } );
		assert.equal( truncated, true );
		assert.ok( lines.length <= LARGE_JSON_LINES + 1, `${ lines.length } lines built` );
		assert.equal( buildTreeLines( response, { maxLines: LARGE_JSON_LINES } ).truncated, false );
	} );
} );
