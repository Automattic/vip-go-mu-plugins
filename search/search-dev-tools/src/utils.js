/**
 * Async helper to post data to a REST andpoint.
 *
 * This function doesn't handle the Errors that maybe thrown after
 * an unsuccessful request. Please handle these in the caller function/method.
 *
 * @param {string} url   Request URL
 * @param {Object} data  Any data to post.
 * @param {string} nonce The nonce verify the permissions
 */
export async function postData( url = '', data = {}, nonce = '' ) {
	const response = await fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': nonce,
		},
		body: JSON.stringify( data ),
	} );
	return response.json();
}

// Frames that belong to WordPress core or the Search plumbing itself; skipped when looking for the "real" caller.
const INTERNAL_FRAME = /^(wp-includes\/|wp-admin\/|wp-content\/mu-plugins\/search\/|wp-blog-header\.php|wp-load\.php|wp-settings\.php|index\.php)/;
// Frames that are pure hook/query plumbing; skipped when falling back to a core caller.
const PLUMBING_FRAME = /^(wp-content\/mu-plugins\/search\/|wp-includes\/(class-wp-hook|plugin|class-wp-query)\.php)/;

/**
 * Shorten a file path for display.
 *
 * @param {string} file File path relative to ABSPATH.
 * @return {string} Display path.
 */
export const displayPath = file => file.replace( /^wp-content\//, '' );

/**
 * A query's backtrace frames (`{ file, line, call }`, as ElasticPress logs them). Anything else is dropped, so an
 * older string backtrace leaves the trace empty instead of breaking the panel.
 *
 * @param {Object} query Query log entry.
 * @return {Array<{file: string, line: ?number, call: string}>} Frames.
 */
export const backtraceFrames = query => ( Array.isArray( query?.backtrace ) ? query.backtrace.filter( frame => frame && typeof frame === 'object' ) : [] );

/**
 * Find the most useful frame to describe where a query came from.
 *
 * @param {Array<{file: string, line: ?number, call: string}>} backtrace Backtrace frames from the query log.
 * @return {number} Index of the caller frame, or -1.
 */
export function findCallerIndex( backtrace = [] ) {
	// Only frames in a PHP file can be the caller: not internal calls (no file) or eval()'d code.
	const inPhpFile = frame => Boolean( frame?.file?.endsWith( '.php' ) );
	let idx = backtrace.findIndex( frame => inPhpFile( frame ) && ! INTERNAL_FRAME.test( frame.file ) );
	if ( idx === -1 ) {
		idx = backtrace.findIndex( frame => inPhpFile( frame ) && ! PLUMBING_FRAME.test( frame.file ) );
	}
	return idx;
}

/**
 * Format a duration in ms.
 *
 * @param {number} ms Duration.
 * @return {string} Human readable duration.
 */
export function formatDuration( ms ) {
	if ( typeof ms !== 'number' || Number.isNaN( ms ) ) {
		return '—';
	}
	if ( Math.round( ms ) < 1 ) {
		return '<1 ms';
	}
	return ms < 1000 ? `${ Math.round( ms ) } ms` : `${ ( ms / 1000 ).toFixed( 2 ) } s`;
}

/**
 * Pull a readable error message out of a failed response body.
 *
 * @param {*} body Response body.
 * @return {string} Error message, or ''.
 */
export function describeError( body ) {
	if ( Array.isArray( body ) ) {
		return body.filter( item => typeof item === 'string' ).join( '; ' );
	}
	if ( ! body || typeof body !== 'object' ) {
		return typeof body === 'string' ? body : '';
	}
	const { error } = body;
	if ( typeof error === 'string' ) {
		return error;
	}
	if ( error && typeof error === 'object' ) {
		const root = error.root_cause?.[ 0 ];
		const reason = root?.reason || error.reason || '';
		const type = root?.type || error.type || '';
		return [ type, reason ].filter( Boolean ).join( ': ' );
	}
	return body.message || '';
}

/**
 * Number with thousands separators, e.g. `10,000`.
 *
 * @param {number} count Count.
 * @return {string} Formatted count.
 */
const formatCount = count => count.toLocaleString( 'en-US' );

/**
 * Count with a pluralized noun, e.g. `1 hit`, `2,507 hits`, or `10,000+ hits` for a lower bound.
 *
 * @param {number}  count      Count.
 * @param {string}  noun       Singular noun.
 * @param {boolean} lowerBound Whether the count is only a lower bound.
 * @return {string} Label.
 */
const countLabel = ( count, noun, lowerBound = false ) => {
	if ( lowerBound ) {
		return `${ formatCount( count ) }+ ${ noun }s`;
	}
	const word = count === 1 ? noun : `${ noun }s`;
	return `${ formatCount( count ) } ${ word }`;
};

/**
 * Summarize a response body: timing, hit counts and failure state.
 *
 * @param {*}      body     Decoded Elasticsearch response body (or an error payload).
 * @param {Object} response HTTP response meta (`code`, `message`), if known.
 * @return {Object} Summary.
 */
export function summarizeResult( body, response ) {
	const failed = isFailedResult( body, response );
	const { total, returned, relation } = countHits( body );
	// Matched total, not the returned page size, so size-limited and counts-only queries read correctly.
	const matched = total ?? returned;
	const hitsLabel = failed ? 'failed' : countLabel( matched, 'hit', relation === 'gte' );

	return {
		took: typeof body?.took === 'number' ? body.took : null,
		failed,
		hitsLabel,
		errorReason: failed ? describeError( body ) : '',
	};
}

/**
 * Whether a response represents a failed request.
 *
 * @param {*}      body     Response body.
 * @param {Object} response HTTP response meta, if known.
 * @return {boolean} Failed.
 */
function isFailedResult( body, response ) {
	const code = response?.code;
	if ( code !== undefined && ! ( Number( code ) >= 200 && Number( code ) < 300 ) ) {
		return true;
	}
	if ( ! body || typeof body !== 'object' || Array.isArray( body ) ) {
		return true;
	}
	return Boolean( body.error ) || Boolean( body.code );
}

/**
 * Hit counts from a search response; handles the ES7+ `{ value }` total shape.
 *
 * @param {*} body Response body.
 * @return {{total: number|undefined, returned: number}} Counts.
 */
export function countHits( body ) {
	const rawTotal = body?.hits?.total;
	const isObject = Boolean( rawTotal ) && typeof rawTotal === 'object';
	return {
		total: isObject ? rawTotal.value : rawTotal,
		// `gte` when Elasticsearch stopped counting (a capped or disabled `track_total_hits`): `total` is a lower bound.
		relation: isObject && rawTotal.relation === 'gte' ? 'gte' : 'eq',
		returned: Array.isArray( body?.hits?.hits ) ? body.hits.hits.length : 0,
	};
}

/**
 * Speed bucket for a request time.
 *
 * @param {number}  took   Time in ms.
 * @param {boolean} failed Whether the request failed.
 * @return {string} good|warn|bad, or neutral for failures (the failure itself carries the red)
 */
export function speedClass( took, failed = false ) {
	if ( failed ) {
		return 'neutral';
	}
	if ( took < 200 ) {
		return 'good';
	}
	return took < 500 ? 'warn' : 'bad';
}

/**
 * Build a descriptive label for a query.
 *
 * @param {Object} query Query log entry.
 * @param {number} index Position in the list.
 * @return {string} Label.
 */
export function queryLabel( query, index ) {
	const args = query.query_args || {};
	const isMain = backtraceFrames( query ).some( frame => frame.call === 'WP->query_posts()' );
	const postType = args.post_type && args.post_type !== 'any' ? [ args.post_type ].flat().join( ', ' ) : '';

	if ( args.s ) {
		return `${ isMain ? 'Main search' : 'Search' } “${ args.s }”`;
	}
	if ( isMain ) {
		return 'Main query';
	}
	if ( postType ) {
		return `${ postType[ 0 ].toUpperCase() }${ postType.slice( 1 ) } query`;
	}
	return `Query ${ index + 1 }`;
}

/**
 * Caller location for display, e.g. `themes/foo/inc/related.php:88`.
 *
 * @param {{file: string, line: ?number}} frame Backtrace frame.
 * @return {string} Location.
 */
const formatCaller = ( { file, line } ) => ( line ? `${ displayPath( file ) }:${ line }` : displayPath( file ) );

/**
 * Which indexes and sites a query reached, from its URL and the backend's cross-site and alias data.
 *
 * @param {Object} query Query log entry.
 * @return {Object} indexName, queriedIndexes, indexLabel, indexTitle, crossSite and sitesLabel.
 */
function describeIndexScope( query ) {
	const path = ( () => {
		try {
			return new URL( query.url ).pathname;
		} catch {
			return '';
		}
	} )();
	const indexName = path.split( '/' ).find( Boolean ) || '';
	const indexNames = indexName ? indexName.split( ',' ) : [];
	const aliasIndexes = Array.isArray( query.alias_indexes ) ? query.alias_indexes : [];
	// The backend flags a network alias whose indexes couldn't be looked up; the alias itself is not an index.
	const aliasUnresolved = query.alias_unresolved === true;

	// Indexes the query actually reached: the alias members for `'sites' => 'all'`, else the URL's index list.
	// Unknown for an unresolved alias (the per-index breakdown then falls back to the hits' own indexes).
	let queriedIndexes = indexNames;
	if ( aliasIndexes.length ) {
		queriedIndexes = aliasIndexes;
	} else if ( aliasUnresolved ) {
		queriedIndexes = [];
	}
	// ElasticPress's own decision, computed server-side (network mode, `sites` vs the current blog). Falls back
	// to the request URL for data without it (e.g. the standalone mock template).
	const crossSite = typeof query.cross_site === 'boolean' ? query.cross_site : indexNames.length > 1 || aliasIndexes.length > 0 || aliasUnresolved;
	// Shown after the hit count in the sidebar, e.g. `4 hits · 2 sites`; empty for single-site queries.
	let sitesLabel = '';
	if ( crossSite ) {
		sitesLabel = aliasUnresolved ? 'all sites' : countLabel( queriedIndexes.length, 'site' );
	}

	return {
		indexName,
		queriedIndexes,
		indexLabel: indexScopeLabel( indexNames, aliasIndexes, aliasUnresolved ),
		indexTitle: indexScopeTitle( indexName, aliasIndexes, aliasUnresolved ),
		crossSite,
		sitesLabel,
	};
}

/**
 * Normalize a query log entry into what the UI needs.
 *
 * @param {Object} query Query log entry.
 * @param {number} index Position in the list.
 * @return {Object} View model.
 */
export function describeQuery( query, index ) {
	const backtrace = backtraceFrames( query );
	const callerIndex = findCallerIndex( backtrace );
	const caller = callerIndex >= 0 ? backtrace[ callerIndex ] : null;
	const scope = describeIndexScope( query );

	const summary = summarizeResult( query.request?.body, query.request?.response );
	// Failed requests may have no `took`; fall back to wall-clock time.
	if ( summary.took === null && query.time_finish && query.time_start ) {
		summary.took = Math.round( ( query.time_finish - query.time_start ) * 1000 );
	}

	return {
		index,
		label: queryLabel( query, index ),
		// Pretty-printed original request; the editor baseline and what "edited" is measured against.
		requestText: JSON.stringify( query.args?.body ?? {}, null, 2 ),
		caller: caller ? formatCaller( caller ) : '',
		callerIndex,
		...scope,
		summary,
	};
}

/**
 * Whether a draft represents a real change to the request: unrun edits, or a result from an edited request.
 * Re-running the untouched request is not an edit.
 *
 * @param {Object|undefined} draft    Draft: { text, ranText, result }.
 * @param {string}           original Original request text.
 * @return {boolean} Edited.
 */
export function isDraftEdited( draft, original ) {
	if ( ! draft ) {
		return false;
	}
	return draft.text !== original || ( draft.ranText !== undefined && draft.ranText !== original );
}

/**
 * Short index name for display: `vip-200508-post-1` → `post-1`.
 *
 * @param {string} name Full index name.
 * @return {string} Short name.
 */
export const shortIndexName = name => name.replace( /^vip-\d+-/, '' );

/**
 * Index label for the detail header: `post-1`, `post-2, post-3`, or the real indexes behind a network alias,
 * `post-1, post-2, post-3-v2 via post-all` (`post-1 +4 via post-all` when long). The alias itself is not an index.
 *
 * @param {string[]} indexNames   Indexes (or the alias) in the request URL.
 * @param {string[]} aliasIndexes Indexes behind a network alias, if any.
 * @param {boolean}  unresolved   Whether the alias's indexes couldn't be looked up.
 * @return {string} Label.
 */
function indexScopeLabel( indexNames, aliasIndexes, unresolved = false ) {
	const label = indexNames.map( shortIndexName ).join( ', ' );
	if ( unresolved ) {
		return `all sites via ${ label }`;
	}
	if ( ! aliasIndexes.length ) {
		return label;
	}
	const real = aliasIndexes.map( shortIndexName );
	const list = real.length <= 3 ? real.join( ', ' ) : `${ real[ 0 ] } +${ real.length - 1 }`;
	return `${ list } via ${ label }`;
}

/**
 * Hover text with the full index names.
 *
 * @param {string}   indexName    Index part of the request URL.
 * @param {string[]} aliasIndexes Indexes behind a network alias, if any.
 * @param {boolean}  unresolved   Whether the alias's indexes couldn't be looked up.
 * @return {string} Title.
 */
function indexScopeTitle( indexName, aliasIndexes, unresolved = false ) {
	if ( ! indexName ) {
		return '';
	}
	const names = indexName.replaceAll( ',', ', ' );
	if ( unresolved ) {
		return `Alias ${ names }; its indexes couldn't be looked up`;
	}
	return aliasIndexes.length ? `Alias ${ names } → indexes ${ aliasIndexes.join( ', ' ) }` : `Index: ${ names }`;
}

/**
 * Returned hits per index, including queried indexes that returned nothing.
 * Hits without a recognizable `_index` are counted as `other`, so the counts always add up to the hits returned.
 *
 * @param {*}        body    Response body.
 * @param {string[]} queried Indexes the query targeted.
 * @return {Array<{index: string, label: string, count: number}>} Counts in query order, then any extra indexes, then other.
 */
export function hitsPerIndex( body, queried = [] ) {
	const counts = new Map( queried.map( name => [ name, 0 ] ) );
	let other = 0;
	for ( const hit of Array.isArray( body?.hits?.hits ) ? body.hits.hits : [] ) {
		if ( hit && typeof hit._index === 'string' && hit._index ) {
			counts.set( hit._index, ( counts.get( hit._index ) || 0 ) + 1 );
		} else {
			other++;
		}
	}
	const rows = Array.from( counts, ( [ index, count ] ) => ( { index, label: shortIndexName( index ), count } ) );
	return other ? [ ...rows, { index: '', label: 'other', count: other } ] : rows;
}

// Above either limit, "Expand all" shows plain text: a fully expanded tree of this size draws hundreds of
// thousands of rows and freezes the page (13 MB ≈ 860k lines ≈ 27 s), while one text node renders instantly.
export const LARGE_JSON_CHARS = 1024 * 1024;
export const LARGE_JSON_LINES = 50000;

/**
 * Number of lines in a string.
 *
 * @param {string} text Text.
 * @return {number} Line count.
 */
export function countLines( text ) {
	let lines = 1;
	for ( let idx = text.indexOf( '\n' ); idx !== -1; idx = text.indexOf( '\n', idx + 1 ) ) {
		lines++;
	}
	return lines;
}

/**
 * Pretty-printed JSON plus whether it is too large to expand as a tree.
 *
 * @param {*} value JSON value.
 * @return {{text: string, lines: number, large: boolean}} Plain-text rendering info.
 */
export function describeJsonSize( value ) {
	const text = JSON.stringify( value, null, 2 ) ?? '';
	const lines = countLines( text );
	const large = text.length > LARGE_JSON_CHARS || lines > LARGE_JSON_LINES;
	// Report the response's own (compact) size, not the inflated pretty-printed one; only paid for large responses.
	const size = large ? ( JSON.stringify( value ) ?? '' ).length : text.length;
	return { text, lines, large, size };
}

// Prism re-highlights the whole request on every keystroke. Measured: ~11 ms at 200 KB, ~29 ms at 500 KB and
// ~59 ms at 1 MB in JS alone, before the browser parses ~4.5x as much highlighted HTML. Above this, show plain text.
export const LARGE_REQUEST_CHARS = 200 * 1024;

/**
 * Whether a request is too large to syntax highlight while typing.
 *
 * @param {string} text Request JSON.
 * @return {boolean} Too large.
 */
export const isLargeRequest = text => text.length > LARGE_REQUEST_CHARS;

/**
 * Escape text for use as HTML (the editor's highlight callback returns HTML).
 *
 * @param {string} text Text.
 * @return {string} Escaped HTML.
 */
export const escapeHtml = text => text.replaceAll( '&', '&amp;' ).replaceAll( '<', '&lt;' ).replaceAll( '>', '&gt;' );

/**
 * Human readable size, e.g. `13.3 MB` or `512 KB`.
 *
 * @param {number} chars Size in characters (close to bytes for JSON).
 * @return {string} Size.
 */
export function formatSize( chars ) {
	if ( chars >= 1024 * 1024 ) {
		return `${ ( chars / ( 1024 * 1024 ) ).toFixed( 1 ) } MB`;
	}
	if ( chars >= 1024 ) {
		return `${ Math.round( chars / 1024 ) } KB`;
	}
	return `${ chars } B`;
}

/**
 * Modifier label for the run shortcut: ⌘ on Apple platforms, Ctrl elsewhere.
 */
export const RUN_SHORTCUT = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test( navigator.platform || navigator.userAgent || '' ) ? '⌘↵' : 'Ctrl+↵';
