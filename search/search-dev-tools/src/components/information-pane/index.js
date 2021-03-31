/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */

import { h } from 'preact';
import { useContext, useState } from 'preact/hooks';
import { SearchContext } from '../../context';
import pluralize from 'pluralize';
import cx from 'classnames';

import style from './style.scss';

const EXPAND_THRESHOLD = 2;

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
export const InfoBlock = ( { label, value, options = { collapsible: false } } ) => {
	const [ collapsed, setCollapsed ] = useState( true );

	const toggleCollapsed = e => {
		if ( options.collapsible ) {
			setCollapsed( ! collapsed );
		}
	};

	const isArrayValue = Array.isArray( value );
	const valueLength = value.length;
	const hasMore = isArrayValue && valueLength > EXPAND_THRESHOLD ? `+${ pluralize( 'other', valueLength - EXPAND_THRESHOLD, true ) }` : '';

	// This is getting a bit unwieldy
	return ( <div className={cx( {
		[ style.info_block ]: true,
		[ style.info_block_collapsible ]: options.collapsible || false,
		[ style.info_block_collapsed ]: collapsed } )}>
		<h5 className={style.info_label} onClick={ toggleCollapsed }> {label} </h5>
		{ options.collapsible ? (
			<Fragment>
				<div className={cx( { [ style.info_block_inner ]: true } )}>
					{
						isArrayValue ? value.map( val => <span key={val} className={style.info_block_item}>{val}</span> ) : <span>{value}</span>
					}
				</div>
				<span style="color: var(--vip-brand-60); cursor: pointer;" onClick={toggleCollapsed}>{
					isArrayValue ? ` ${ value.slice( 0, EXPAND_THRESHOLD ).join( ', ' ) } ${ hasMore }` : 'Click to show'
				}
				</span>
			</Fragment> ) : ( <span>{ isArrayValue ? value.join( ', ' ) : value }</span> ) }
	</div> );
};
