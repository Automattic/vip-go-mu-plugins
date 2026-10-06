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
 * Split a backtrace frame such as `wp-includes/class-wp.php:704 WP_Query->query()`.
 *
 * @param {string} frame Backtrace frame.
 * @return {{file: string, line: string, call: string}} Parsed frame.
 */
export function parseFrame( frame = '' ) {
	// Plain string scanning rather than one regex, which backtracks on long frames without a match.
	const space = frame.search( /\s/ );
	const location = space > 0 ? frame.slice( 0, space ) : '';
	const colon = location.lastIndexOf( ':' );
	const line = colon > 0 && /^\d+$/.test( location.slice( colon + 1 ) ) ? location.slice( colon + 1 ) : '';
	const file = line ? location.slice( 0, colon ) : location;
	if ( ! file.endsWith( '.php' ) ) {
		return { file: '', line: '', call: frame };
	}
	return { file, line, call: frame.slice( space ).trimStart() };
}

/**
 * Shorten a file path for display.
 *
 * @param {string} file File path relative to ABSPATH.
 * @return {string} Display path.
 */
export const displayPath = file => file.replace( /^wp-content\//, '' );

/**
 * Find the most useful frame to describe where a query came from.
 *
 * @param {Array<string>} backtrace Backtrace frames.
 * @return {number} Index of the caller frame, or -1.
 */
export function findCallerIndex( backtrace = [] ) {
	const frames = backtrace.map( parseFrame );
	let idx = frames.findIndex( frame => frame.file && ! INTERNAL_FRAME.test( frame.file ) );
	if ( idx === -1 ) {
		idx = frames.findIndex( frame => frame.file && ! PLUMBING_FRAME.test( frame.file ) );
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
 * Count with a pluralized noun, e.g. `1 hit`, `7 hits`.
 *
 * @param {number} count Count.
 * @param {string} noun  Singular noun.
 * @return {string} Label.
 */
const countLabel = ( count, noun ) => {
	const word = count === 1 ? noun : `${ noun }s`;
	return `${ count } ${ word }`;
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
	const { total, returned } = countHits( body );
	// Matched total, not the returned page size, so size-limited and counts-only queries read correctly.
	const matched = total ?? returned;
	const hitsLabel = failed ? 'failed' : countLabel( matched, 'hit' );

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
	return {
		total: rawTotal && typeof rawTotal === 'object' ? rawTotal.value : rawTotal,
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
	const isMain = ( query.backtrace || [] ).some( frame => frame.includes( 'WP->query_posts()' ) );
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
 * @param {{file: string, line: string}} frame Parsed frame.
 * @return {string} Location.
 */
const formatCaller = ( { file, line } ) => ( line ? `${ displayPath( file ) }:${ line }` : displayPath( file ) );

/**
 * Normalize a query log entry into what the UI needs.
 *
 * @param {Object} query Query log entry.
 * @param {number} index Position in the list.
 * @return {Object} View model.
 */
export function describeQuery( query, index ) {
	const backtrace = query.backtrace || [];
	const callerIndex = findCallerIndex( backtrace );
	const caller = callerIndex >= 0 ? parseFrame( backtrace[ callerIndex ] ) : null;
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

	// Indexes the query actually reached: the alias members for `'sites' => 'all'`, else the URL's index list.
	const queriedIndexes = aliasIndexes.length ? aliasIndexes : indexNames;
	// ElasticPress's own decision, computed server-side (network mode, `sites` vs the current blog). Falls back
	// to the request URL for data without it (e.g. the standalone mock template).
	const crossSite = typeof query.cross_site === 'boolean' ? query.cross_site : indexNames.length > 1 || aliasIndexes.length > 0;

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
		indexName,
		queriedIndexes,
		indexLabel: indexScopeLabel( indexNames, aliasIndexes ),
		indexTitle: indexScopeTitle( indexName, aliasIndexes ),
		crossSite,
		// Shown after the hit count in the sidebar, e.g. `4 hits · 2 sites`; empty for single-site queries.
		sitesLabel: crossSite ? countLabel( queriedIndexes.length, 'site' ) : '',
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
 * @return {string} Label.
 */
function indexScopeLabel( indexNames, aliasIndexes ) {
	const label = indexNames.map( shortIndexName ).join( ', ' );
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
 * @return {string} Title.
 */
function indexScopeTitle( indexName, aliasIndexes ) {
	if ( ! indexName ) {
		return '';
	}
	const names = indexName.replaceAll( ',', ', ' );
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
