/* eslint-disable no-shadow */
/* eslint-disable react/prop-types */
/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */
import { createContext, Fragment, h } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';
import Overlay from './overlay';

import style from './style.scss';

const SearchContext = createContext( null );

async function postData( url = '', data = {}, nonce = '' ) {
	const response = await fetch( url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			// 'X-WP-Nonce': nonce,
		},
		body: JSON.stringify( data ),
	} );
	return response.json();
}

const GeneralInformation = () => {
	const { status, queries, information } = useContext( SearchContext );
	return <div>
		<h2>General Info</h2>
		<h4>Status: {status}</h4>
		<h4>Query count: {queries.length}</h4>
		<h3>Debug Information</h3>
		<ul>
			{information.map( ( info, idx ) => ( <li key={idx}><strong>{info.label}</strong>: { info.value} </li> ) )}
		</ul>

	</div>;
};

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

		}
	};

	useEffect( () => {
		// Skip update
		if ( state.query === initialState.query ) {
			return setState( initialState );
		}

		fetchForQuery( state.query, url );
	}, [ state.query ] );

	return ( <div className={style.query_wrap}>
		<h3>URL: {url}</h3>
		<h3>Request</h3>
		{ ! state.editing
			? ( <div className={style.query_val} onClick={e => setState( { ...state, editing: true } )}>{state.query}</div> )
			: ( <textarea rows="50" className={style.query_val} onBlur={e => setState( { ...state, result: '', editing: false, query: e.target.value } )}>{state.query}</textarea> )
		}

		<button onClick={() => setState( initialState )}>Reset</button>

		<h3>Response</h3>
		<div className={style.query_result}>{state.result}</div>
	</div> );
};

const Queries = () => {
	const { queries } = useContext( SearchContext );

	if ( queries.length < 1 ) {
		return <div>No queries to show</div>;
	}

	return ( <div>
		{queries.map( ( q, idx ) => <Query key={idx} {...q} /> )}
	</div> );
};

const App = props => {
	const [ visible, setVisible ] = useState(false);
	const closeOverlay = e => setVisible(false);
	const openOverlay = e => setVisible(true);
	const toggleOverlay = () => setVisible( ! visible );

	return ( <SearchContext.Provider value={window?.VIPSearchDevTools || { status: 'disabled', queries: [], information: [] }}>
	<div className="search-dev-tools__wrapper">
		<button onClick={ toggleOverlay }>Open Search Dev Tools</button>
		<Overlay isVisible={ visible } closeOverlay={closeOverlay} opacity="100">
			<div className={style.vip_search_dev_tools}>
				<h1>Search Dev Tools</h1>
				<GeneralInformation />
				<Queries />
			</div>
		</Overlay>
	</div>
	</SearchContext.Provider>
	
	);
};
export default App;
