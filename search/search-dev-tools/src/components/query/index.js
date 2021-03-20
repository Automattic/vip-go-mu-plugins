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

import style from '../../style/style.scss';
/**
 * A single query
 *
 * @returns {Preact.Component} A query component.
 */
const Query = ({ args, request, url }) => {
	const txtQuery = JSON.stringify(args.body, null, 2);
	const txtResult = JSON.stringify(request.body, null, 2);
	const initialState = {
		editing: false,
		query: txtQuery,
		result: txtResult,
	};

	const [state, setState] = useState(initialState);

	const fetchForQuery = async (query, url) => {
		try {
			const res = await postData(window.VIPSearchDevTools.ajaxurl, {
				action: window.VIPSearchDevTools.action,
				url,
				query,
			}, window.VIPSearchDevTools.nonce);

			setState({ ...state, result: res.result.body });
		} catch (err) {
			// eslint-disable-next-line no-console
			console.log(err);
		}
	};

	useEffect(() => {
		// Skip update
		if (state.query === initialState.query) {
			return setState(initialState);
		}

		if (!state.editing) {
			fetchForQuery(state.query, url);
		}
	}, [state.query, state.editing]);

	return (<div className={style.query_wrap}>
		<h5 stylw="width:100%;">URL: {url}</h5>
		<div className={style.query_actions}>
			<button>Run</button>
			<button onClick={() => setState(initialState)}>Reset</button>
		</div>
		<div className={style.query_pane}>
			<h5>Request</h5>
			<Editor
				value={state.query}
				onValueChange={code => setState({ ...state, query: code, editing: true })}
				onBlur={e => setState({ ...state, editing: false })}
				highlight={code => highlight(code, languages.json)}
				padding={10}
				style={{
					fontSize: 14,
				}}
			/>
		</div>

		<div className={style.query_pane}>
			<h5>Response</h5>
			<div className={style.query_result} dangerouslySetInnerHTML={{ __html: highlight(state.result, languages.json) }}></div>
		</div>

		<div className={style.query_actions}>
			<button>Run</button>
			<button onClick={() => setState(initialState)}>Reset</button>
		</div>

	</div>);
};


/**
 * Query list
 */
export const Queries = () => {
	const { queries } = useContext(SearchContext);

	if (queries.length < 1) {
		return <div>No queries to show</div>;
	}

	return (<div>
		{queries.map((q, idx) => <Query key={idx} {...q} />)}
	</div>);
};
