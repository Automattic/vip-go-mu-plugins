/* eslint-disable wpcalypso/import-docblock */

import { h } from 'preact';
import { useContext } from 'preact/hooks';
import { SearchContext } from '../../context';
import pluralize from 'pluralize';
/**
 *
 * @returns {Preact.Component} General useful debug info.
 */
export const GeneralInformation = () => {
	const { status, queries, information } = useContext(SearchContext);
	return <div>
		<h4>{pluralize('query', queries.length, true)} { pluralize( 'was', queries.length ) } run on this page</h4>

		<h4 style="margin-top: 20px;">Debug Information</h4>
		<ul>
			{information.map((info, idx) => (<li key={idx}><strong>{info.label}</strong>: { info.value} </li>))}
		</ul>
	</div>;
};