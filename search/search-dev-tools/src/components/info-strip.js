import cx from 'classnames';
import { useEffect, useRef, useState } from 'preact/hooks';

import { eventOrigin } from '../dom';

// Lists this short are shown inline instead of behind a popover.
const INLINE_MAX_ITEMS = 3;
const INLINE_MAX_CHARS = 40;
const isShortList = list => list.length <= INLINE_MAX_ITEMS && list.join( ', ' ).length <= INLINE_MAX_CHARS;

const capitalize = value => ( typeof value === 'string' && value ? value[ 0 ].toUpperCase() + value.slice( 1 ) : value );

/**
 * One info item. Short lists show inline; longer ones open a popover listing every entry.
 *
 * @param {Object} props       Props.
 * @param {Object} props.item  Info item: { key, label, value, options }.
 * @return {import('preact').VNode} Info item.
 */
const InfoItem = ( { item } ) => {
	const { key, label, value } = item;
	const [ open, setOpen ] = useState( false );
	const ref = useRef( null );

	useEffect( () => {
		if ( ! open ) {
			return undefined;
		}
		const onPointer = evt => ! ref.current?.contains( eventOrigin( evt ) ) && setOpen( false );
		const onKey = evt => {
			if ( evt.key === 'Escape' ) {
				// Captured on window first, so this keeps the panel from closing too.
				evt.stopPropagation();
				setOpen( false );
			}
		};
		document.addEventListener( 'mousedown', onPointer );
		window.addEventListener( 'keydown', onKey, true );
		return () => {
			document.removeEventListener( 'mousedown', onPointer );
			window.removeEventListener( 'keydown', onKey, true );
		};
	}, [ open ] );

	// Rate limiting reports details as a list when active.
	if ( key === 'rate_limited' || label === 'Rate limited?' ) {
		const limited = Array.isArray( value );
		return (
			<div className="sdt-info__item">
				<span className="sdt-info__label">Rate limited</span>
				<strong className={ cx( limited ? 'is-bad' : 'is-quiet' ) } title={ limited ? value.join( '\n' ) : undefined }>
					{ limited ? value.join( ' · ' ) : capitalize( String( value ) ) }
				</strong>
			</div>
		);
	}

	if ( ! Array.isArray( value ) ) {
		// No concurrent requests is the normal state; keep it quiet.
		const quiet = key === 'concurrent_requests' && Number( value ) === 0;
		return (
			<div className="sdt-info__item">
				<span className="sdt-info__label">{ label }</span>
				<strong className={ cx( { 'is-quiet': quiet } ) }>{ String( value ) }</strong>
			</div>
		);
	}

	if ( isShortList( value ) ) {
		return (
			<div className="sdt-info__item">
				<span className="sdt-info__label">{ label }</span>
				<strong className={ cx( { 'sdt-muted': ! value.length } ) }>{ value.length ? value.join( ', ' ) : 'None' }</strong>
			</div>
		);
	}

	const popoverId = `sdt-info-${ key || label.replace( /\W+/g, '-' ).toLowerCase() }`;

	return (
		<div className="sdt-info__item sdt-info__item--list" ref={ ref }>
			<button
				type="button"
				className="sdt-info__toggle"
				aria-expanded={ open }
				aria-controls={ popoverId }
				onClick={ () => setOpen( ! open ) }
			>
				<span className="sdt-info__label">{ label }</span>
				<strong className="sdt-accent">{ value.length }</strong>
				<span className="sdt-caret" aria-hidden="true">▾</span>
			</button>
			{ open && (
				<dialog open className="sdt-popover" id={ popoverId } aria-label={ label }>
					{ value.length
						? <ul className="sdt-chips">{ value.map( entry => <li key={ entry } className="sdt-mono">{ entry }</li> ) }</ul>
						: <p className="sdt-empty">None</p> }
				</dialog>
			) }
		</div>
	);
};

/**
 * Strip of general Search information shown under the header.
 *
 * @param {Object} props             Props.
 * @param {Array}  props.information Info items from the backend.
 * @return {import('preact').VNode} Info strip.
 */
export const InfoStrip = ( { information } ) => {
	// Cluster-wide values first; per-site settings in their own group, since other sites may differ.
	const global = information.filter( item => item.scope !== 'site' );
	const site = information.filter( item => item.scope === 'site' );
	const renderItem = item => <InfoItem key={ item.key || item.label } item={ item } />;
	return (
		<div className="sdt-info">
			<div className="sdt-info__group">{ global.map( renderItem ) }</div>
			{ site.length
				? (
					<div className="sdt-info__group sdt-info__group--site">
						<span className="sdt-info__group-label" title="These settings apply to the current site only; other sites on the network may differ.">This site:</span>
						{ site.map( renderItem ) }
					</div>
				)
				: null }
		</div>
	);
};
