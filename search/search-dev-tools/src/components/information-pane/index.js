/* eslint-disable wpcalypso/import-docblock */

import { h } from 'preact';
import { useContext, useEffect, useState } from 'preact/hooks';
import { SearchContext } from '../../context';
/**
 *
 * @returns {Preact.Component} General useful debug info.
 */
export const GeneralInformation = () => {
	const { status, queries, information } = useContext(SearchContext);
	return <div>
		<h4>General Info</h4>
		<h5>Status: {status}</h5>
		<h5>Query count: {queries.length}</h5>
		<h4 style="margin-top: 20px;">Debug Information</h4>
		<ul>
			{information.map((info, idx) => (<li key={idx}><strong>{info.label}</strong>: { info.value} </li>))}
		</ul>
	</div>;
};