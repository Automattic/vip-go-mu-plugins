import { Fragment, h } from 'preact';
import { useContext, useEffect, useState, useRef } from 'preact/hooks';
import { highlight, highlightElement, languages } from 'prismjs/components/prism-core';
import 'prismjs/plugins/line-numbers/prism-line-numbers.js';
import 'prismjs/components/prism-json';
import 'prism-themes/themes/prism-ghcolors.css';
import Editor from 'react-simple-code-editor';

import { SearchContext } from '../../context';
import { postData } from '../../utils';

import cx from 'classnames';

import pluralize from 'pluralize';

import style from './style.scss';

/**
 *  Collapsible list of values.
 *
 * @param {Object} props: { list, title }
 * @returns {Preact.Component} a Collapsible list
 */
const CollapsibleList = ( { list = [], title = 'View' } ) => {
	const [ visible, setVisible ] = useState( false );
	const toggle = () => {
		setVisible( ! visible );
	};

	return ( <div className={ cx( { [ style.backtrace ]: true, [ style.visible ]: visible } ) }>
		<strong className="vip-h4" onClick={ list.length ? toggle : null }>{ title } ({ `${ list.length }` })</strong>
		<ol className={style.backtrace_details}>{list.map( ( frame, i ) => ( <li key={i}>{frame}</li> ) ) }</ol>
	</div> );
};

/**
 * A single query
 *
 * @returns {Preact.Component} A query component.
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

	const queryResultRef = useRef( null );

	/**
	 * 
	 * @param {Object} query the query to Run
	 * @param {String} url ES url
	 */
	const fetchForQuery = async ( query, url ) => {
		try {
			const res = await postData( window.VIPSearchDevTools.ajaxurl, {
				action: window.VIPSearchDevTools.action,
				url,
				query,
			}, window.VIPSearchDevTools.nonce );

			setState({ ...state, result: JSON.stringify( res?.result?.body, null, 2 ) } );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.log( err );
		}
	};

	useEffect( () => {
		// Skip remote fetching if the query is the same.
		// (e.g. was just reset to the initial one or wasn't change at all)
		if ( state.query === initialState.query ) {
			return;
		}

		if ( ! state.editing ) {
			fetchForQuery( state.query, url );
		}
	}, [ state.query, state.editing ] );

	// Re-highlight the query on result change (after the response is received).
	useEffect( () => {
		highlightElement( queryResultRef.current );
	}, [ queryResultRef, state.result ] );

	return ( <div className={cx( style.query_wrap, state.collapsed ? style.query_collapsed : null )}>
		<div className={style.query_handle} onClick={ () => setState( { ...state, collapsed: ! state.collapsed } ) }>
			<h3 className="vip-h3">
				{ pluralize( 'result', ( request?.body?.hits?.hits?.length || 0 ), true )}
				<span style="color: var(--vip-grey-60);"> that took</span> {request.body.took}ms
				<small> ({request.response.code})</small>
			</h3>
		</div>
		<div className={style.grid_container}>
			<div className={style.query_src_header}>
				<span style="margin-right: auto;">Request</span>
				<div className={style.query_src_extra}>
					<CollapsibleList title="WP_Query"
						list={ Object.entries( query_args ).map( ( [ key, value ] ) => `${ key }: ${ JSON.stringify( value ) }` ) }
					/>
					<CollapsibleList title="Trace" list={ backtrace } />
				</div>
			</div>
			<div className={style.query_res_header}>
				Response
			</div>
			<div className={style.query_src}>
				<div className={style.query_actions}>
					{ state.editing || state.result !== txtResult
						? ( <Fragment>
							<button onClick={ () => setState( { ...state, editing: false } ) }>RUN</button>
							<button onClick={ () => setState( { ...initialState, collapsed: false } ) }>RESET</button>
						</Fragment> )
						: 'Edit me!'
					}
				</div>

				<Editor
					value={state.query}
					onValueChange={code => setState( { ...state, query: code, editing: true } )}
					highlight={
						/** Prism has line-numbers plugin, unfortunately it doesn't work with low-level highlight function:
						'complete' hook doesn't run, so we use a trick here */
						code => highlight( code, languages.json, 'json' )
							.split( '\n' )
							.map(
								line =>
									`<span class="${ style.container_editor_line_number }">${ line }</span>`
							)
							.join( '\n' )
					}
					padding={null}
					className={style.container_editor}
					style={{
						fontSize: 12,
					}}
				/>
			</div>
			<div className={style.query_res}>
				<div className={style.query_result}>
					<pre className="line-numbers">
						<code className="language-json" ref={ queryResultRef } dangerouslySetInnerHTML={{ __html: state.result }}></code>
					</pre>
				</div>
			</div>
		</div>
	</div> );
};

/**
 * Query list component
 * 
 * @returns {Preact.Component} query list.
 */
export const Queries = () => {
	const { queries } = useContext( SearchContext );

	if ( queries.length < 1 ) {
		return <div>No queries to show</div>;
	}

	return ( <div>
		{queries.map( ( q, idx ) => <Query key={idx} {...q} /> )}
	</div> );
};
