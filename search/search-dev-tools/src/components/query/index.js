/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */

import { h } from 'preact';
import { useContext, useEffect, useState, useRef } from 'preact/hooks';
import { SearchContext } from '../../context';

// TODO: switch Editor to an async import
import { highlight, highlightElement, languages } from 'prismjs/components/prism-core';
import 'prismjs/plugins/line-numbers/prism-line-numbers.js';

import 'prismjs/components/prism-json';
import 'prism-themes/themes/prism-ghcolors.css';
import Editor from 'react-simple-code-editor';
import { postData } from '../../utils';

import cx from 'classnames';

import pluralize from 'pluralize';

import style from './style.scss';


const Backtrace = ({ trace }) => {
	const [ visible, setVisible ] = useState( false );
	const toggle = () => {
		setVisible( ! visible );
	}


	return (<div className={ cx( { [style.backtrace]: true, [style.visible]: visible } ) }>
		<strong class="vip-h4" onClick={ toggle }>Trace ({ `${ trace.length }` })</strong>
		<ol class={style.backtrace_details}>{ trace.map( frame => (<li>{frame}</li>) ) }</ol>
	</div>);
}

/**
 * A single query
 *
 * @returns {Preact.Component} A query component.
 */
const Query = ( { args, request, url, backtrace = null } ) => {
	const txtQuery = JSON.stringify( args.body, null, 2 );
	const txtResult = JSON.stringify( request.body, null, 2 );
	const initialState = {
		editing: false,
		query: txtQuery,
		result: txtResult,
		// raw|wpQueryArgs
		tab: 'raw',
		collapsed: true,
	};

	const [ state, setState ] = useState( initialState );

	const queryResultRef = useRef(null);

	const fetchForQuery = async ( query, url ) => {
		try {
			const res = await postData( window.VIPSearchDevTools.ajaxurl, {
				action: window.VIPSearchDevTools.action,
				url,
				query,
			}, window.VIPSearchDevTools.nonce );

			setState( { ...state, result: res.result.body } );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.log( err );
		}
	};

	useEffect( () => {
		// Skip update
		if ( state.query === initialState.query ) {
			return setState( initialState );
		}

		if ( ! state.editing ) {
			fetchForQuery( state.query, url );
		}
	}, [ state.query, state.editing ] );

	useEffect( () => {
		highlightElement( queryResultRef.current );
	}, [ queryResultRef ] );

	return ( <div className={cx( style.query_wrap, state.collapsed ? style.query_collapsed : null )}>
		<div className={style.query_handle} onClick={ () => setState({...state, collapsed: ! state.collapsed }) }>
			<h3 className="vip-h3">
				{pluralize( 'result', ( request?.body?.hits?.hits?.length || 0 ), true )} <span style="color: var(--vip-grey-60);">that took</span> {request.body.took}ms <small>({request.response.code})</small>
			</h3>
			
		</div>
		<div className={style.grid_container}>
			<div className={style.query_src_header}>
				<span style="margin-right: auto;">Request</span>
				<div class={style.query_src_extra}>
				<span>WP_Query</span>
					{backtrace ? <Backtrace trace={backtrace} /> : null}
				</div>
			</div>
			<div className={style.query_res_header}>
				Response
			</div>
			<div className={style.query_src}>
				<Editor
					value={state.query}
					onValueChange={code => setState( { ...state, query: code, editing: true } )}
					onBlur={e => setState( { ...state, editing: false } )}
					highlight={
						/** Prism has line-numbers plugin, unfortunately it doesn't work with low-level highlight function:
						'complete' hook doesn't run, so we use a trick here */
						code => highlight( code, languages.json, 'json' )
							.split('\n')
							.map(
								line =>
									`<span class="${style.container_editor_line_number}">${line}</span>`
							)
							.join('\n')
					}
					padding={null}
					className={style.container_editor}
					style={{
						fontSize: 12,
						// paddingTop: '32px'
					}}
				/>
			</div>
			<div className={style.query_res}>
				<div className={style.query_result}>
					<pre class="line-numbers">
						<code class="language-json" ref={ queryResultRef } dangerouslySetInnerHTML={{ __html: state.result }}></code>
					</pre>
				</div>
			</div>
		</div>

		{/* <h5 style="width:100%;">URL: {url}</h5> */}
		<div className={style.query_actions}>
			<button>Run</button>
			<button onClick={() => setState( initialState )}>Reset</button>
		</div>

	</div> );
};

/**
 * Query list
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
