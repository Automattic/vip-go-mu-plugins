/* eslint-disable no-shadow */
/* eslint-disable react/prop-types */
/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */
import { createContext, h } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';

// TODO: switch Editor to an async import
import Editor from 'react-simple-code-editor';
import { highlight, languages } from 'prismjs/components/prism-core';
import 'prismjs/components/prism-json';
import 'prismjs/themes/prism.css';

import Overlay from './overlay';
import style from './style.scss';

import { postData } from '../utils';

// Global state as Context.
const SearchContext = createContext( null );

/**
 * 
 * @returns {Preact.Component} General useful debug info.
 */
const GeneralInformation = () => {
	const { status, queries, information } = useContext( SearchContext );
	return <div>
		<h4>General Info</h4>
		<h5>Status: {status}</h5>
		<h5>Query count: {queries.length}</h5>
		<h4 style="margin-top: 20px;">Debug Information</h4>
		<ul>
			{information.map( ( info, idx ) => ( <li key={ idx }><strong>{ info.label }</strong>: { info.value } </li> ) )}
		</ul>
	</div>;
};

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

	return ( <div className={style.query_wrap}>
		<h5>URL: {url}</h5>
		<h5>Request</h5>
		<Editor
			value={state.query}
			onValueChange={code => setState( { ...state, query: code, editing: true } ) }
			onBlur={e => setState( { ...state, editing: false } )}
			highlight={ code => highlight( code, languages.json )}
			padding={10}
			style={{
				fontSize: 14,
			}}
		/>

		<div className={style.query_actions}>
			<button>Run</button>
			<button onClick={() => setState( initialState )}>Reset</button>
		</div>


		<h3>Response</h3>
		<div className={style.query_result} dangerouslySetInnerHTML={{ __html: highlight( state.result, languages.json ) }}></div>
	</div> );
};

/**
 * Query list
 */
const Queries = () => {
	const { queries } = useContext( SearchContext );

	if ( queries.length < 1 ) {
		return <div>No queries to show</div>;
	}

	return ( <div>
		{queries.map( ( q, idx ) => <Query key={idx} {...q} /> )}
	</div> );
};

/**
 * Main app component
 */
const App = props => {
	const [ visible, setVisible ] = useState( false );
	const closeOverlay = e => setVisible( false );
	const openOverlay = e => setVisible( true );
	const toggleOverlay = () => setVisible( ! visible );

	return ( <SearchContext.Provider value={window?.VIPSearchDevTools || { status: 'disabled', queries: [], information: [] }}>
		<div className="search-dev-tools__wrapper">
			<button onClick={ toggleOverlay }>Open Search Dev Tools</button>
			{ visible ? ( <Overlay isVisible={ visible } closeOverlay={closeOverlay} opacity="100">
				<div className={style.vip_search_dev_tools}>
					<h3>Search Dev Tools</h3>
					<GeneralInformation />
					<Queries />
				</div>
			</Overlay> ) : null }
		</div>
	</SearchContext.Provider>
	);
};
export default App;
