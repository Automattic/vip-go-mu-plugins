/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */
import { createContext, h } from 'preact';
import { useContext, useState } from 'preact/hooks';

const SearchContext = createContext( null );

const GeneralInformation = () => {
	const { status, queries, information } = useContext( SearchContext );
	return <div>
		<h2>General Info</h2>
		<h4>Status: {status}</h4>
		<h4>Query count: {queries.length}</h4>
		<ul>
			{information.map( info => ( <li>{ info.label }: { info.value } </li> ) ) }
		</ul>

	</div>;
};

const Query = ( { query, results } ) => {
	const [ editing, setEditing ] = useState( false );
	return (<div>
		{ ! editing ? <div onClick={e => setEditing( true )}>{query}</div> : <textarea>{query}</textarea> }
	</div>);
};

const Queries = () => {
	const { queries } = useContext( SearchContext );

	if ( queries.length < 1 ) {
		return <div>No queries to show</div>;
	}

	return ( <div>
		{queries.map( q => <Query {...q} /> ) }
	</div> );
};

const App = props => {
	return ( <SearchContext.Provider value={window?.VIPSearchDevTools || { status: 'disabled', queries: [], information: [] }}>
		<div>
			<h1>Search Dev Tools</h1>
			<GeneralInformation />
			<Queries />
		</div>
	</SearchContext.Provider>
	);
};
export default App;
