/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */

import { h } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';
import { SearchContext } from '../../context';

// TODO: switch Editor to an async import
import { highlight, languages } from 'prismjs/components/prism-core';
import 'prismjs/components/prism-json';
import 'prismjs/themes/prism.css';
import Editor from 'react-simple-code-editor';
import { postData } from '../../utils';

import cx from 'classnames';

import pluralize from 'pluralize';

import style from './style.scss';
/**
 * A single query
 *
 * @returns {Preact.Component} A query component.
 */
const Query = ( { args, request, url } ) => {
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

	return ( <div className={cx( style.query_wrap, state.collapsed ? style.query_collapsed : null )}>
		<div className={style.query_handle}>
			<h3 className="vip-h3">{pluralize( 'result', ( request?.body?.hits?.hits?.length || 0 ), true )} <span style="color: var(--vip-grey-60);">that took</span> {request.body.took}ms <small>({request.response.code})</small></h3>
		</div>
		<div className={style.grid_container}>

			<div className={style.query_src_header}>
				Request
			</div>
			<div className={style.query_res_header}>
				Response
			</div>
			<div className={style.query_src}>
				<Editor
					value={state.query}
					onValueChange={code => setState( { ...state, query: code, editing: true } )}
					onBlur={e => setState( { ...state, editing: false } )}
					highlight={code => highlight( code, languages.json )}
					padding={10}
					style={{
						fontSize: 14,
					}}
				/>
			</div>
			<div className={style.query_res}>
				<div className={style.query_result} dangerouslySetInnerHTML={{ __html: highlight( state.result, languages.json ) }}></div>
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
