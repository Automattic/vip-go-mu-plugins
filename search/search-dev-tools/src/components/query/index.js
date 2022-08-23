/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
import { h } from 'preact';
import { useCallback, useContext, useEffect, useState } from 'preact/hooks';
import { highlight, languages } from 'prismjs/components/prism-core';
import 'prismjs/plugins/line-numbers/prism-line-numbers.js';
import 'prismjs/components/prism-json';
import Editor from 'react-simple-code-editor';
import cx from 'classnames';
import pluralize from 'pluralize';
import ClipboardJS from 'clipboard';
import { SearchContext } from '../../context';
import { postData } from '../../utils';
import { CollapsibleList } from '../collapsible-list';

import style from './style.scss';
import '../../style/prism.scss';

/**
 * A single query
 *
 * @param {Object} root0
 * @param {Object} root0.args
 * @param {Object} root0.request
 * @param {string} root0.url
 * @param {Object} root0.query_args
 * @param {Array}  root0.backtrace
 * @return {import('preact').VNode} A query component.
 */
const Query = ( { args, request, url, query_args, backtrace = [] } ) => {
	const txtQuery = JSON.stringify( args.body, null, 2 );
	const txtResult = JSON.stringify( request.body, null, 2 );
	const initialState = {
		editing: false,
		query: txtQuery,
		result: txtResult,
		collapsed: true,
	};

	const [ state, setState ] = useState( initialState );

	const copyButtonDOMSelector = '#query-response-copy-handle';

	/**
	 * @param {Object} query the query to Run
	 */
	const fetchForQuery = useCallback( async query => {
		try {
			const res = await postData( window.VIPSearchDevTools.ajaxurl, {
				action: window.VIPSearchDevTools.action,
				url,
				query,
			}, window.VIPSearchDevTools.nonce );

			setState( prevState => ( { ...prevState, result: JSON.stringify( res?.result?.body, null, 2 ) } ) );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.log( err );
		}
	}, [ url ] );

	useEffect( () => {
		const cbHandler = new ClipboardJS( copyButtonDOMSelector );
		cbHandler.on( 'success', evt => {
			document.querySelector( copyButtonDOMSelector ).innerHTML = 'COPIED!';
			setTimeout( () => {
				document.querySelector( copyButtonDOMSelector ).innerHTML = 'COPY';
			}, 2000 );
			evt.clearSelection();
		} );
		return () => cbHandler.destroy();
	}, [] );

	useEffect( () => {
		// Skip remote fetching if the query is the same.
		// (e.g. was just reset to the initial one or wasn't change at all)
		if ( state.query === initialState.query ) {
			return;
		}

		if ( ! state.editing ) {
			fetchForQuery( state.query );
		}
	}, [ state.query, state.editing, initialState.query, fetchForQuery ] );

	const colorizeRequestTime = timeMs => {
		let cls;

		if ( timeMs < 200 ) {
			cls = 'green-60';
		} else if ( timeMs < 500 ) {
			cls = 'red-30';
		} else {
			cls = 'red-60';
		}

		return <span style={ { color: `var(--vip-${ cls })`, fontWeight: 'bold' } }>{ timeMs }ms</span>;
	};

	return ( <div className={ cx( style.query_wrap, state.collapsed ? style.query_collapsed : null ) }>
		<div className={ style.query_handle } onClick={ () => setState( { ...state, collapsed: ! state.collapsed } ) }>
			<h3 className="vip-h3">
				{ pluralize( 'result', ( request?.body?.hits?.hits?.length || 0 ), true ) }
				<span style="color: var(--vip-grey-60);"> that took</span> { colorizeRequestTime( request.body.took ) }
				<small> ({ request?.response?.code || 'unknown' })</small>
			</h3>
		</div>
		<div className={ style.grid_container }>
			<div className={ style.query_src_header }>
				<span style="margin-right: auto;">Request</span>
				<div className={ style.query_src_extra }>
					<CollapsibleList title="WP_Query"
						list={ Object.entries( query_args ).map( ( [ key, value ] ) => `${ key }: ${ JSON.stringify( value ) }` ) }
					/>
					<CollapsibleList title="Trace" list={ backtrace } />
				</div>
			</div>
			<div className={ style.query_res_header }>
				Response
			</div>
			<div className={ style.query_src }>
				<div className={ style.query_actions }>
					{ state.editing || state.result !== txtResult
						? ( <>
							<button onClick={ () => setState( { ...state, editing: false } ) } style="background-color: var(--vip-green-40) !important">RUN</button>
							<button onClick={ () => setState( { ...initialState, collapsed: false } ) } style="background-color: var(--vip-blue-10) !important">RESET</button>
						</> )
						: 'Edit me!'
					}
				</div>

				<Editor
					value={ state.query }
					onValueChange={ code => setState( { ...state, query: code, editing: true } ) }
					highlight={
						/**
						  Prism has line-numbers plugin, unfortunately it doesn't work with low-level highlight function:
						  'complete' hook doesn't run, so we use a trick here
						 */
						code => highlight( code, languages.json, 'json' )
							.split( '\n' )
							.map(
								line =>
									`<span class="${ style.container_editor_line_number }">${ line }</span>`,
							)
							.join( '\n' )
					}
					padding={ 0 }
					className={ style.container_editor }
					style={ {
						fontSize: 'var(--vip-sdt-editor-font-size)',
						lineHeight: '1.2em',
					} }
				/>
			</div>
			<div className={ style.query_res }>
				<div className={ style.query_result }>
					<div className={ style.query_actions }>
						<button id="query-response-copy-handle" data-clipboard-target="#query-response-text">COPY</button>
					</div>
					<pre className="line-numbers">
						<code className="language-json" id="query-response-text">{ state.result }</code>
					</pre>
				</div>
			</div>
		</div>
	</div> );
};

/**
 * Query list component
 *
 * @return {import('preact').VNode} query list.
 */
export const Queries = () => {
	const { queries } = useContext( SearchContext );

	if ( queries.length < 1 ) {
		return <div>No queries to show</div>;
	}

	return ( <div>
		{ queries.map( ( query, idx ) => <Query key={ idx } { ...query } /> ) }
	</div> );
};
