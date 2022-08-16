/* eslint-disable jsx-a11y/no-static-element-interactions */
/* eslint-disable jsx-a11y/click-events-have-key-events */
import { h } from 'preact';
import { useState } from 'preact/hooks';
import cx from 'classnames';
import style from './style.scss';

/**
 * Collapsible list of values.
 *
 * @param {Object} props       { list, title }
 * @param {Array}  props.list
 * @param {string} props.title
 * @return {import('preact').VNode} a collapsible list of values
 */
export const CollapsibleList = ( { list = [], title = 'View' } ) => {
	const [ visible, setVisible ] = useState( false );
	const toggle = () => {
		setVisible( ! visible );
	};

	return ( <div className={ cx( { [ style.collapsible_list ]: true, [ style.visible ]: visible } ) }>
		<strong className="vip-h4" onClick={ list.length ? toggle : null }>{ title } ({ `${ list.length }` })</strong>
		<ol className={ style.collapsible_list_details }>{ list.map( ( frame, index ) => ( <li key={ index }>{ frame }</li> ) ) }</ol>
	</div> );
};
