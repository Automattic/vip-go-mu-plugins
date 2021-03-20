/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */

import { h } from 'preact';
import { useContext, useState } from 'preact/hooks';
import { SearchContext } from '../../context';
import pluralize from 'pluralize';

import style from './style.scss';
/**
 *
 * @returns {Preact.Component} General useful debug info.
 */
export const GeneralInformation = () => {
	const { status, queries, information } = useContext( SearchContext );
	return ( <div>
		<h2 className={`vip-h2 ${ style.query_count }`}>{pluralize( 'query', queries.length, true )} { pluralize( 'was', queries.length ) } run on this page</h2>

		<div className={style.info_wrapper}>
			{information.map( ( info, idx ) => ( <InfoBlock key={idx} {...info} /> ) ) }
		</div>
	</div> );
};

/**
 *
 * @param {Object} props
 * @returns {Preact.Component} A collapsible block of information.
 */
export const InfoBlock = ( { label, value, options = {} } ) => {
	const [ collapsed, setCollapsed ] = useState( true );

	return ( <div className={ style.info_block }><h5 className={style.info_label}> { label } </h5>
		{
			Array.isArray( value ) ? value.map( val => <span key={ val } className={style.info_block_item}>{ val }</span> ) : <span>{ value }</span>
		}
	</div> );
};
