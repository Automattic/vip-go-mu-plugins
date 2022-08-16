/* eslint-disable jsx-a11y/no-noninteractive-element-interactions */
/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
import { h } from 'preact';
import { useContext, useState } from 'preact/hooks';
import pluralize from 'pluralize';
import cx from 'classnames';

import { SearchContext } from '../../context';
import style from './style.scss';

// More than this number of elements in the list will be hidden in the collapsible.
const EXPAND_THRESHOLD = 2;

/**
 *
 * @return {import('preact').VNode} General useful debug info.
 */
export const GeneralInformation = () => {
	const { queries, information } = useContext( SearchContext );
	return ( <div>
		<h2 className={ `vip-h2 ${ style.query_count }` }>{ pluralize( 'query', queries.length, true ) } { pluralize( 'was', queries.length ) } run on this page</h2>

		<div className={ style.info_wrapper }>
			{ information.map( ( info, idx ) => ( <InfoBlock key={ idx } { ...info } /> ) ) }
		</div>
	</div> );
};

/**
 * Represents a single collaplsible info block.
 *
 * @param {Object} props         including label, value and options
 * @param {string} props.label
 * @param {*}      props.value
 * @param {Object} props.options
 * @return {import('preact').VNode} A collapsible block of information.
 */
export const InfoBlock = ( { label, value, options = { collapsible: false } } ) => {
	const [ collapsed, setCollapsed ] = useState( true );

	const toggleCollapsed = () => {
		if ( options.collapsible ) {
			setCollapsed( ! collapsed );
		}
	};

	const isArrayValue = Array.isArray( value );
	const valueLength = value.length;
	const hasMore = isArrayValue && valueLength > EXPAND_THRESHOLD ? `+ ${ pluralize( 'other', valueLength - EXPAND_THRESHOLD, true ) }` : '';

	return ( <div className={ cx( {
		[ style.info_block ]: true,
		[ style.info_block_collapsible ]: options.collapsible || false,
		[ style.info_block_collapsed ]: collapsed,
	} ) }>
		<h5 className={ style.info_label } onClick={ toggleCollapsed }>{ label }</h5>
		{ options.collapsible
			? (
				<>
					<div className={ cx( { [ style.info_block_inner ]: true } ) }>
						{
							isArrayValue ? value.map( val => <span key={ val } className={ style.info_block_item }>{ val }</span> ) : <span>{ value }</span>
						}
					</div>
					<span className={ style.info_block_teaser } onClick={ toggleCollapsed }>{
						isArrayValue ? ` ${ value.slice( 0, EXPAND_THRESHOLD ).join( ', ' ) } ${ hasMore }` : 'Click to show'
					}
					</span>
				</> )
			: ( <span>{ isArrayValue ? value.join( ', ' ) : value }</span> ) }
	</div> );
};
