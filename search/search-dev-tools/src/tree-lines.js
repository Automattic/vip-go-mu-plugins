/**
 * Pure line-building for the JSON tree (no JSX), so it can be unit tested and line counts known before rendering.
 */

const isContainer = value => value !== null && typeof value === 'object';

/**
 * Path of a child node. Object keys are JSON-encoded so dotted Elasticsearch field names stay unambiguous:
 * `{ "a.b": 1 }` is `$["a.b"]`, while `{ a: { b: 1 } }` is `$["a"]["b"]`. Array items are `[n]`.
 *
 * @param {string}        path Parent path (root is `$`).
 * @param {string|number} key  Object key or array index.
 * @return {string} Child path.
 */
export const childPath = ( path, key ) => ( typeof key === 'number' ? `${ path }[${ key }]` : `${ path }[${ JSON.stringify( key ) }]` );

const SHARDS_PATH = childPath( '$', '_shards' );
const HITS_PATH = childPath( childPath( '$', 'hits' ), 'hits' );

/**
 * Index of a hit if `path` is a top-level hit (`$["hits"]["hits"][n]`), else -1.
 *
 * @param {string} path Node path.
 * @return {number} Hit index or -1.
 */
export function hitIndex( path ) {
	if ( ! path.startsWith( HITS_PATH ) ) {
		return -1;
	}
	const match = /^\[(\d+)\]$/.exec( path.slice( HITS_PATH.length ) );
	return match ? Number( match[ 1 ] ) : -1;
}

/**
 * Whether a node starts collapsed.
 * In `auto` mode this keeps the first hit readable and folds the noisy parts.
 *
 * @param {string} path  Node path.
 * @param {number} depth Node depth (root is 0).
 * @param {Object} value Node value.
 * @param {string} mode  auto|expanded|collapsed
 * @return {boolean} Collapsed by default.
 */
function isCollapsedByDefault( path, depth, value, mode ) {
	if ( mode === 'expanded' ) {
		return false;
	}
	if ( mode === 'collapsed' ) {
		return depth > 0;
	}
	if ( path === SHARDS_PATH ) {
		return true;
	}
	const hit = hitIndex( path );
	if ( hit >= 0 ) {
		return hit > 0;
	}
	return depth >= 5 && Object.values( value ).some( isContainer );
}

const scalarType = value => {
	if ( value === null ) {
		return 'null';
	}
	return typeof value;
};

const overLimit = ( ctx, out ) => out.length > ctx.limit;

// Each line is a list of `[ type, text, slot ]` parts; the slot (key, colon, open, value, ...) is unique within a
// line, so it doubles as the render key.

/**
 * Per-line basics shared by every kind of line.
 *
 * @param {Object} ctx Context.
 * @return {Object} depth, label (toggle name), parent path, trailing comma and key parts.
 */
function lineBasics( ctx ) {
	const { depth, name, isLast } = ctx;
	return {
		depth,
		// Human-readable node name for toggle labels: `hits`, `hits[3]`, or `response` for the root.
		label: ctx.label ?? 'response',
		// Parent's path, for keyboard navigation to the parent node.
		parent: ctx.parent ?? null,
		comma: isLast ? '' : ',',
		keyParts: name === undefined ? [] : [ [ 'key', JSON.stringify( name ), 'key' ], [ 'punct', ': ', 'colon' ] ],
	};
}

/**
 * Collapsed one-line summary of a container, e.g. `"_shards": { 4 keys },`.
 *
 * @param {Object}  basics    lineBasics() result.
 * @param {string}  path      Node path.
 * @param {boolean} isArray   Whether the node is an array.
 * @param {number}  size      Item / key count.
 * @param {Array}   noteParts Optional note parts.
 * @return {Object} Line.
 */
function collapsedLine( { depth, label, parent, comma, keyParts }, path, isArray, size, noteParts ) {
	const [ open, close, noun ] = isArray ? [ '[', ']', 'item' ] : [ '{', '}', 'key' ];
	return {
		key: path,
		depth,
		path,
		parent,
		label,
		collapsed: true,
		parts: [
			...keyParts,
			[ 'punct', `${ open } `, 'open' ],
			[ 'summary', `${ size } ${ noun }${ size === 1 ? '' : 's' }`, 'summary' ],
			[ 'punct', ` ${ close }${ comma }`, 'close' ],
			...noteParts,
		],
	};
}

/**
 * Flatten a JSON value into renderable lines, honoring collapsed state.
 * Stops as soon as more than `ctx.limit` lines exist, so huge payloads are never fully expanded in memory.
 *
 * @param {*}      value Value.
 * @param {Object} ctx   Context.
 * @param {Array}  out   Accumulated lines.
 */
function buildLines( value, ctx, out ) {
	if ( overLimit( ctx, out ) ) {
		return;
	}
	const { path, overrides, mode, annotate } = ctx;
	const basics = lineBasics( ctx );
	const { depth, label, parent, comma, keyParts } = basics;

	if ( ! isContainer( value ) ) {
		out.push( { key: path, depth, parts: [ ...keyParts, [ scalarType( value ), JSON.stringify( value ) ?? String( value ), 'value' ], [ 'punct', comma, 'comma' ] ] } );
		return;
	}

	const isArray = Array.isArray( value );
	const keys = isArray ? null : Object.keys( value );
	const size = isArray ? value.length : keys.length;
	const [ open, close ] = isArray ? [ '[', ']' ] : [ '{', '}' ];

	if ( ! size ) {
		out.push( { key: path, depth, parts: [ ...keyParts, [ 'punct', `${ open }${ close }${ comma }`, 'value' ] ] } );
		return;
	}

	const note = annotate ? annotate( path, value ) : '';
	const noteParts = note ? [ [ 'note', note, 'note' ] ] : [];
	const collapsed = path in overrides ? overrides[ path ] : isCollapsedByDefault( path, depth, value, mode );
	if ( collapsed ) {
		out.push( collapsedLine( basics, path, isArray, size, noteParts ) );
		return;
	}

	out.push( { key: path, depth, path, parent, label, collapsed: false, parts: [ ...keyParts, [ 'punct', open, 'open' ], ...noteParts ] } );
	buildChildren( value, ctx, keys, size, out );
	// Once over the limit the result is discarded anyway; don't keep appending closing brackets.
	if ( ! overLimit( ctx, out ) ) {
		out.push( { key: `${ path }/end`, depth, parts: [ [ 'punct', `${ close }${ comma }`, 'close' ] ] } );
	}
}

/**
 * Lines for each child of an expanded container, stopping at the line limit.
 *
 * @param {Object|Array}  value Container.
 * @param {Object}        ctx   Container's context.
 * @param {string[]|null} keys  Object keys, or null for arrays.
 * @param {number}        size  Child count.
 * @param {Array}         out   Accumulated lines.
 */
function buildChildren( value, ctx, keys, size, out ) {
	const { path, depth, name, overrides, mode, annotate, limit } = ctx;
	// Array items are labelled after their array, e.g. `hits[3]`; the root array's items are just `[3]`.
	const arrayName = name ?? ( depth ? ctx.label ?? 'response' : '' );
	for ( let idx = 0; idx < size && ! overLimit( ctx, out ); idx++ ) {
		const key = keys ? keys[ idx ] : idx;
		buildLines( value[ key ], {
			path: childPath( path, key ),
			depth: depth + 1,
			name: keys ? key : undefined,
			label: keys ? String( key ) : `${ arrayName }[${ idx }]`,
			parent: path,
			isLast: idx === size - 1,
			overrides,
			mode,
			annotate,
			limit,
		}, out );
	}
}

/**
 * Flatten a JSON value into the lines the tree renders.
 *
 * @param {*}        value     JSON value.
 * @param {Object}   options   Options.
 * @param {string}   options.mode      Fold mode: auto|expanded|collapsed.
 * @param {Object}   options.overrides Manual toggles keyed by path.
 * @param {Function} options.annotate  Optional ( path, value ) => note for a container's opening line.
 * @param {number}   options.maxLines  Optional limit; building stops once it is exceeded.
 * @return {{lines: Array, truncated: boolean}} Lines, and whether the limit was exceeded.
 */
export function buildTreeLines( value, { mode = 'auto', overrides = {}, annotate, maxLines = Infinity } = {} ) {
	const out = [];
	buildLines( value, { path: '$', depth: 0, isLast: true, overrides, mode, annotate, limit: maxLines }, out );
	return { lines: out, truncated: out.length > maxLines };
}
