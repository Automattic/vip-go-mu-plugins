import cx from 'classnames';
import { useCallback, useContext, useMemo, useRef, useState } from 'preact/hooks';

import { CodeEditor, EDITOR_HINT } from './code-editor';
import { JsonTree } from './json-tree';
import { AnnounceContext } from '../context';
import { hitIndex } from '../tree-lines';
import { RUN_SHORTCUT, countHits, describeJsonSize, displayPath, formatDuration, formatSize, hitsPerIndex, LARGE_JSON_LINES, isDraftEdited, parseFrame, postData, shortIndexName, summarizeResult } from '../utils';


/**
 * Copy text to the clipboard, with a fallback for non-secure (http) contexts.
 *
 * @param {string} text Text to copy.
 * @return {Promise<void>} Resolves once copied; rejects when the browser refuses the copy.
 */
async function copyText( text ) {
	if ( navigator.clipboard && window.isSecureContext ) {
		return navigator.clipboard.writeText( text );
	}
	const previous = document.activeElement;
	const textarea = document.createElement( 'textarea' );
	textarea.value = text;
	textarea.setAttribute( 'readonly', '' );
	textarea.setAttribute( 'aria-hidden', 'true' );
	textarea.style.position = 'fixed';
	textarea.style.opacity = '0';
	// Inside the panel, so its focus containment doesn't pull focus (and the selection) away before the copy.
	( previous?.closest?.( '.sdt' ) ?? document.body ).appendChild( textarea );
	try {
		textarea.select();
		// execCommand reports a refused copy by returning false rather than throwing.
		if ( ! document.execCommand( 'copy' ) ) { // NOSONAR -- deprecated, but the only copy API without a secure context.
			throw new Error( 'The browser refused the copy.' );
		}
	} finally {
		textarea.remove();
		previous?.focus?.();
	}
}

const formatArg = value => ( typeof value === 'string' ? value : JSON.stringify( value ) );

const WpQueryArgs = ( { args } ) => {
	const entries = Object.entries( args || {} );
	if ( ! entries.length ) {
		return <p className="sdt-empty">No WP_Query arguments were recorded.</p>;
	}
	return (
		<table className="sdt-kv">
			<tbody>
				{ entries.map( ( [ key, value ] ) => (
					<tr key={ key }>
						<th scope="row">{ key }</th>
						<td>{ formatArg( value ) }</td>
					</tr>
				) ) }
			</tbody>
		</table>
	);
};

const Trace = ( { frames, callerIndex } ) => {
	if ( ! frames.length ) {
		return <p className="sdt-empty">No stack trace was recorded. Enable WP_DEBUG to collect one.</p>;
	}
	return (
		<ol className="sdt-trace">
			{ frames.map( ( frame, idx ) => {
				const { file, line, call } = parseFrame( frame );
				// Frames repeat under recursion, so the key counts earlier occurrences of the same frame.
				const occurrence = frames.slice( 0, idx ).filter( earlier => earlier === frame ).length;
				return (
					<li key={ `${ frame }#${ occurrence }` } className={ cx( { 'is-caller': idx === callerIndex } ) }>
						<span className="sdt-gutter-num" aria-hidden="true">{ idx + 1 }</span>
						<span className="sdt-trace__frame">
							<span className="sdt-trace__call">{ call }</span>
							{ file ? <span className="sdt-trace__file">{ displayPath( file ) }{ line ? `:${ line }` : '' }</span> : null }
						</span>
						{ idx === callerIndex ? <span className="sdt-chip sdt-chip--accent">caller</span> : null }
					</li>
				);
			} ) }
		</ol>
	);
};

// Identifies each run, so a response is applied only if its run is still the query's current one.
let lastRunToken = 0;
const nextRunToken = () => ++lastRunToken;

/**
 * A draft without its in-flight run marker.
 *
 * @param {Object} draft Draft.
 * @return {Object} Draft.
 */
const withoutPendingRun = ( { pendingRun, ...rest } ) => rest;

/**
 * Status chip text: HTTP status for the original request; "Edited" or "Re-run" once a re-run result is shown.
 *
 * @param {boolean} hasRerun   Whether the result comes from a re-run.
 * @param {boolean} ranEdited  Whether the re-run request differed from the original.
 * @param {Object}  response   HTTP response meta of the original request.
 * @return {string} Chip text.
 */
function statusLabel( hasRerun, ranEdited, response = {} ) {
	if ( hasRerun ) {
		// Chip color still shows whether the re-run failed.
		return ranEdited ? 'Edited' : 'Re-run';
	}
	return [ response.code, response.message ].filter( Boolean ).join( ' ' ) || 'unknown';
}


const DetailHeader = ( { meta, summary, status, isDirty, running, onReset, onRun } ) => (
	<header className="sdt-detail__head">
		<div className="sdt-detail__titles">
			<div className="sdt-detail__title-row">
				<h2 className="sdt-detail__title">{ meta.label }</h2>
				<span className={ cx( 'sdt-chip', summary.failed ? 'sdt-chip--bad' : 'sdt-chip--good' ) }>{ status }</span>
				<span className="sdt-detail__stats">
					{ /* On failure the status chip carries it; the reason lives in the response (and the live announcement). */ }
					{ summary.failed ? null : <span>{ summary.hitsLabel }</span> }
					{ summary.failed ? null : <span aria-hidden="true">·</span> }
					<span>{ formatDuration( summary.took ) }</span>
					{ meta.indexLabel
						? (
							<>
								<span aria-hidden="true">·</span>
								<span className="sdt-detail__index" title={ meta.indexTitle }>
									{ meta.indexLabel }
									<span className="sdt-visually-hidden">. { meta.indexTitle }</span>
								</span>
							</>
						)
						: null }
				</span>
			</div>
			<div className="sdt-detail__sub">
				{ meta.caller ? <span className="sdt-mono">{ meta.caller }</span> : null }
			</div>
		</div>
		<div className="sdt-detail__actions">
			<button type="button" className="sdt-btn" onClick={ onReset } disabled={ ! isDirty || running }>Reset</button>
			<button type="button" className="sdt-btn sdt-btn--primary" onClick={ onRun } disabled={ running }>
				{ running ? 'Running…' : 'Run query' }
			</button>
		</div>
	</header>
);

/**
 * ARIA tabs: one Tab stop (the selected tab), Left/Right/Home/End move and select, and only the
 * selected tab references the single rendered panel.
 *
 * @param {Object}   props          Props.
 * @param {Array}    props.tabs     Tabs: { id, label, count }.
 * @param {string}   props.active   Selected tab id.
 * @param {Function} props.onChange Selection handler.
 * @return {import('preact').VNode} Tab bar.
 */
const TabBar = ( { tabs, active, onChange } ) => {
	const onKeyDown = evt => {
		const idx = tabs.findIndex( item => item.id === active );
		const next = {
			ArrowRight: ( idx + 1 ) % tabs.length,
			ArrowLeft: ( idx - 1 + tabs.length ) % tabs.length,
			Home: 0,
			End: tabs.length - 1,
		}[ evt.key ];
		if ( next === undefined ) {
			return;
		}
		evt.preventDefault();
		onChange( tabs[ next ].id );
		evt.currentTarget.parentElement.querySelectorAll( '[role="tab"]' )[ next ]?.focus();
	};

	return (
		<div className="sdt-tabs" role="tablist" aria-label="Query details">
			{ tabs.map( item => (
				<button
					key={ item.id }
					type="button"
					role="tab"
					id={ `sdt-tab-${ item.id }` }
					tabIndex={ active === item.id ? 0 : -1 }
					aria-selected={ active === item.id }
					aria-controls={ active === item.id ? 'sdt-tabpanel' : undefined }
					className={ cx( 'sdt-tab', { 'is-active': active === item.id } ) }
					onClick={ () => onChange( item.id ) }
					onKeyDown={ onKeyDown }
				>
					{ item.label }
					{ item.count === undefined ? null : <span className="sdt-tab__count">{ item.count }</span> }
				</button>
			) ) }
		</div>
	);
};


/**
 * Inline Run button, shown only while the request has edits that haven't run yet.
 *
 * @param {Object}   props         Props.
 * @param {boolean}  props.pending Whether there are unrun edits.
 * @param {boolean}  props.running Whether a run is in flight.
 * @param {Function} props.onRun   Run handler.
 * @return {import('preact').VNode} Run control.
 */
const RunControl = ( { pending, running, onRun } ) => {
	if ( ! pending ) {
		return null;
	}
	return (
		<button type="button" className="sdt-btn sdt-btn--primary sdt-btn--small" onClick={ onRun } disabled={ running }>
			{ running ? 'Running…' : 'Run' }
			<kbd className="sdt-kbd">{ RUN_SHORTCUT }</kbd>
		</button>
	);
};

/**
 * Returned hits per index for cross-site queries; zeros make an index that returned nothing obvious.
 *
 * @param {Object}   props         Props.
 * @param {*}        props.result  Response body.
 * @param {string[]} props.queried Indexes the query targeted.
 * @return {import('preact').VNode} Breakdown.
 */
const IndexBreakdown = ( { result, queried, pageSize } ) => {
	const { total, returned } = countHits( result );
	// Matched hits beyond the page size: the header counts them, the per-index counts can't.
	const notReturned = typeof total === 'number' ? total - returned : 0;
	return (
		<span className="sdt-breakdown" title="Returned hits per index">
			{ hitsPerIndex( result, queried ).map( ( { index, label, count } ) => (
				<span key={ index || 'other' } className={ cx( 'sdt-breakdown__item', { 'is-empty': ! count } ) }>
					{ label } <strong>{ count }</strong>
				</span>
			) ) }
			{ notReturned > 0
				? (
					<span
						className="sdt-breakdown__item is-more"
						title={ `${ total } matched; the request returns at most ${ pageSize ?? returned } (size), so per-index counts cover ${ returned }.` }
					>
						+{ notReturned } not returned
					</span>
				)
				: null }
		</span>
	);
};

/**
 * `size` of a request body, if set.
 *
 * @param {string} text Request JSON.
 * @return {number|undefined} Page size.
 */
const requestSize = text => {
	try {
		const size = JSON.parse( text )?.size;
		return typeof size === 'number' ? size : undefined;
	} catch {
		return undefined;
	}
};

// Tag each hit's opening line with the short name of the index it came from.
const annotateHit = ( path, value ) => ( hitIndex( path ) >= 0 && typeof value?._index === 'string' ? shortIndexName( value._index ) : '' );

/**
 * Screen reader announcement for a finished run.
 *
 * @param {Object} ran summarizeResult() of the run's response.
 * @return {string} Announcement.
 */
function runAnnouncement( ran ) {
	if ( ! ran.failed ) {
		return `Query ran: ${ ran.hitsLabel }, ${ formatDuration( ran.took ) }.`;
	}
	return ran.errorReason ? `Query failed: ${ ran.errorReason }` : 'Query failed.';
}

/**
 * Plain-text view of a response too large for the tree: one text node renders fast and ⌘F works.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.size       Result of describeJsonSize().
 * @param {Function} props.onShowTree Opt back into the tree.
 * @return {import('preact').VNode} Plain response.
 */
const PlainResponse = ( { size, onShowTree } ) => (
	<>
		<output className="sdt-notice sdt-notice--inline">
			<span>
				Large response ({ formatSize( size.size ) }, { size.lines.toLocaleString() } lines formatted): shown as plain text.
			</span>
			<button type="button" className="sdt-link-btn" onClick={ onShowTree }>Show tree anyway</button>
		</output>
		<pre className="sdt-plain">{ size.text }</pre>
	</>
);

const ResponsePane = ( { result, resultKey, crossSite, queriedIndexes, pageSize } ) => {
	const announce = useContext( AnnounceContext );
	const [ copied, setCopied ] = useState( false );
	// `version` remounts the tree so manual toggles reset when a fold-all button is used.
	// `forceTree` lets the user opt back into the tree for a large response.
	const [ fold, setFold ] = useState( { mode: 'auto', version: 0, forceTree: false } );
	const foldAll = mode => setFold( prev => ( { mode, version: prev.version + 1, forceTree: false } ) );
	const showTreeAnyway = () => setFold( prev => ( { ...prev, version: prev.version + 1, forceTree: true } ) );

	// Measured lazily and at most once per result: stringifying a large response on every render
	// (e.g. each keystroke in the editor) would be expensive.
	const sizeOf = useMemo( () => {
		let size = null;
		const measure = () => {
			size = size ?? describeJsonSize( result );
			return size;
		};
		measure.peek = () => size;
		return measure;
	}, [ result ] );

	// Only measured when "Expand all" is requested, so normal viewing never pays for stringifying.
	const plain = fold.mode === 'expanded' && ! fold.forceTree && sizeOf().large ? sizeOf() : null;

	const copy = async () => {
		try {
			// Reuse the pretty-printed text when it has already been computed for the plain view.
			await copyText( sizeOf.peek()?.text ?? JSON.stringify( result, null, 2 ) );
			setCopied( true );
			announce( 'Response copied to the clipboard.' );
			setTimeout( () => setCopied( false ), 1500 );
		} catch {
			// Clipboard unavailable or the copy was refused: don't claim success.
		}
	};

	return (
		<div className="sdt-pane sdt-pane--response">
			<div className="sdt-pane__head">
				<h3 className="sdt-pane__title">Response</h3>
				<div className="sdt-pane__actions">
					<button type="button" className="sdt-btn sdt-btn--small sdt-btn--ghost" onClick={ () => foldAll( 'expanded' ) }>Expand all</button>
					<button type="button" className="sdt-btn sdt-btn--small sdt-btn--ghost" onClick={ () => foldAll( 'collapsed' ) }>Collapse all</button>
					<button type="button" className="sdt-btn sdt-btn--small" onClick={ copy }>{ copied ? 'Copied' : 'Copy' }</button>
				</div>
			</div>
			{ crossSite
				? (
					<div className="sdt-pane__subhead">
						<span className="sdt-muted">Returned per index:</span>
						<IndexBreakdown result={ result } queried={ queriedIndexes } pageSize={ pageSize } />
					</div>
				)
				: null }
			<div className="sdt-pane__body">
				{ plain
					? <PlainResponse size={ plain } onShowTree={ showTreeAnyway } />
					: (
						<JsonTree
							key={ `${ resultKey }-${ fold.version }` }
							value={ result }
							mode={ fold.mode }
							annotate={ crossSite ? annotateHit : undefined }
							// Safety net: even folded, a response can be huge (e.g. thousands of aggregation buckets).
							maxLines={ fold.forceTree ? undefined : LARGE_JSON_LINES }
							renderTooLarge={ () => <PlainResponse size={ sizeOf() } onShowTree={ showTreeAnyway } /> }
						/>
					) }
			</div>
		</div>
	);
};

/**
 * What the detail view shows for a query, given its draft (edits, last re-run, in-flight run).
 *
 * @param {Object|undefined} draft        Draft from App state.
 * @param {string}           originalText Original request text.
 * @param {Object}           query        Query log entry.
 * @return {Object} Derived state.
 */
function draftState( draft, originalText, query ) {
	const text = draft?.text ?? originalText;
	const ranText = draft?.ranText ?? originalText;
	const hasRerun = draft?.result !== undefined;
	return {
		text,
		ranText,
		hasRerun,
		// Edited since the last run (or since page load, if never re-run).
		hasPendingEdits: text !== ranText,
		running: Boolean( draft?.pendingRun ),
		result: hasRerun ? draft.result : query.request?.body,
		resultKey: hasRerun ? `rerun-${ draft.runId }` : 'original',
	};
}

/**
 * Detail view for a single query: header, editable request, and response.
 *
 * @param {Object}   props               Props.
 * @param {Object}   props.query         Raw query log entry.
 * @param {Object}   props.meta          Derived view model (see describeQuery).
 * @param {Object}   props.draft         Unsaved edits for this query, if any.
 * @param {Function} props.onDraftChange Called with the new draft (or undefined to reset).
 * @return {import('preact').VNode} Query detail.
 */
export const QueryDetail = ( { query, meta, draft, onDraftChange } ) => {
	const announce = useContext( AnnounceContext );
	const [ tab, setTab ] = useState( 'request' );
	// The in-flight run lives in the draft (App state), so it survives this keyed component unmounting when
	// another query is selected or the panel is closed; the ref only covers presses within the same tick.
	const inFlightRef = useRef( false );
	const [ error, setError ] = useState( '' );

	const originalText = meta.requestText;
	const { text, ranText, hasRerun, hasPendingEdits, running, result, resultKey } = draftState( draft, originalText, query );
	const summary = useMemo(
		() => ( hasRerun ? summarizeResult( draft.result ) : meta.summary ),
		[ hasRerun, draft, meta.summary ],
	);
	const frames = query.backtrace || [];
	// Parsed once per request text, not on every keystroke render.
	const pageSize = useMemo( () => requestSize( ranText ), [ ranText ] );

	const run = useCallback( async () => {
		// One request per query at a time: `pendingRun` (App state) persists across remounts, and the ref guards
		// repeated ⌘/Ctrl+Enter before that state renders.
		if ( inFlightRef.current || draft?.pendingRun ) {
			return;
		}
		try {
			JSON.parse( text );
		} catch ( err ) {
			setError( `Invalid JSON: ${ err.message }` );
			setTab( 'request' );
			return;
		}

		setError( '' );
		inFlightRef.current = true;
		const token = nextRunToken();
		onDraftChange( prev => ( { ...prev, text: prev?.text ?? text, pendingRun: token } ) );
		try {
			const { ajaxurl, nonce } = window.VIPSearchDevTools;
			const res = await postData( ajaxurl, { url: query.url, query: text }, nonce );
			const body = res?.result ? res.result.body : res;
			// Apply only if this run is still the current one (not reset or superseded), merging into the latest
			// draft so edits typed while it was in flight survive.
			onDraftChange( prev => ( prev?.pendingRun === token
				? { ...withoutPendingRun( prev ), ranText: text, runId: Date.now(), result: body }
				: prev ) );
			announce( runAnnouncement( summarizeResult( body ) ) );
		} catch ( err ) {
			setError( `Request failed: ${ err.message }` );
			onDraftChange( prev => ( prev?.pendingRun === token ? withoutPendingRun( prev ) : prev ) );
		} finally {
			inFlightRef.current = false;
		}
	}, [ text, query.url, onDraftChange, announce, draft?.pendingRun ] );

	const updateText = code => onDraftChange( prev => {
		const next = { ...prev, text: code };
		// Typing back to the original with no re-run result is no edit at all, unless a run is in flight:
		// its result merges into this draft and must not fall back to the text that was sent.
		return ! next.pendingRun && next.result === undefined && ! isDraftEdited( next, originalText ) ? undefined : next;
	} );

	const reset = () => {
		setError( '' );
		onDraftChange( undefined );
	};

	const tabs = [
		{ id: 'request', label: 'Request' },
		{ id: 'wp_query', label: 'WP_Query', count: Object.keys( query.query_args || {} ).length },
		{ id: 'trace', label: 'Trace', count: frames.length },
	];

	return (
		<section className="sdt-detail" aria-label={ meta.label }>
			<DetailHeader
				meta={ meta }
				summary={ summary }
				status={ statusLabel( hasRerun, draft?.ranText !== originalText, query.request?.response ) }
				isDirty={ text !== originalText || hasRerun }
				running={ running }
				onReset={ reset }
				onRun={ run }
			/>

			<div className="sdt-panes">
				<div className="sdt-pane">
					<div className="sdt-pane__head">
						<TabBar tabs={ tabs } active={ tab } onChange={ setTab } />
						<span className="sdt-pane__hint" aria-hidden="true">{ EDITOR_HINT }</span>
						<RunControl pending={ hasPendingEdits } running={ running } onRun={ run } />
					</div>
					{ error ? <div className="sdt-error" role="alert">{ error }</div> : null }
					<div className="sdt-pane__body" role="tabpanel" id="sdt-tabpanel" aria-labelledby={ `sdt-tab-${ tab }` }>
						{ tab === 'request' && (
							<CodeEditor
								id={ `sdt-request-${ meta.index }` }
								label="Elasticsearch request body"
								value={ text }
								onValueChange={ updateText }
								onRun={ run }
							/>
						) }
						{ tab === 'wp_query' && <WpQueryArgs args={ query.query_args } /> }
						{ tab === 'trace' && <Trace frames={ frames } callerIndex={ meta.callerIndex } /> }
					</div>
				</div>

				<ResponsePane
					// A new result starts in the default view: fold mode and "Show tree anyway" don't carry over.
					key={ resultKey }
					result={ result }
					resultKey={ resultKey }
					crossSite={ meta.crossSite }
					queriedIndexes={ meta.queriedIndexes }
					pageSize={ pageSize }
				/>
			</div>
		</section>
	);
};
