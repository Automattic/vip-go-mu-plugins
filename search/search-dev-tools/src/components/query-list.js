import cx from 'classnames';
import { useRef } from 'preact/hooks';

import { formatDuration, speedClass } from '../utils';

// Text equivalent of the timing colors (WCAG 1.4.1).
const SPEED_TEXT = { warn: 'slow', bad: 'very slow' };

/**
 * File path that truncates from the left, so the file name and line number always stay visible.
 *
 * @param {Object} props      Props.
 * @param {string} props.path Path such as `themes/foo/inc/related.php:88`.
 * @return {import('preact').VNode} Path.
 */
const CallerPath = ( { path } ) => {
	const slash = path.lastIndexOf( '/' );
	const dir = path.slice( 0, slash + 1 );
	const file = path.slice( slash + 1 );
	return (
		<span className="sdt-list__caller sdt-path sdt-mono" title={ path } style={ { minWidth: `min(${ file.length + 1 }ch, 70%)` } }>
			{ /* The RTL span puts the ellipsis on the left; the LRM keeps the trailing slash on the right. */ }
			{ dir ? <span className="sdt-path__dir">{ dir }{ '\u200E' }</span> : null }
			<span className="sdt-path__file">{ file }</span>
		</span>
	);
};

/**
 * Sidebar list of queries run on the page.
 *
 * @param {Object}   props          Props.
 * @param {Array}    props.items    Query view models (see describeQuery).
 * @param {number}   props.selected Selected index.
 * @param {Function} props.onSelect Selection handler.
 * @param {Function} props.isEdited ( index ) => whether the query has edits.
 * @return {import('preact').VNode} Query list.
 */
export const QueryList = ( { items, selected, onSelect, isEdited } ) => {
	const listRef = useRef( null );

	// Arrow keys move from the focused query, which may differ from the selected one after tabbing.
	const onKeyDown = ( evt, from ) => {
		if ( evt.key !== 'ArrowDown' && evt.key !== 'ArrowUp' ) {
			return;
		}
		evt.preventDefault();
		const next = Math.min( items.length - 1, Math.max( 0, from + ( evt.key === 'ArrowDown' ? 1 : -1 ) ) );
		onSelect( next );
		listRef.current?.querySelectorAll( 'button' )[ next ]?.focus();
	};

	return (
		<nav className="sdt-list" aria-label="Queries">
			<ul ref={ listRef }>
				{ items.map( item => {
					const { took, failed, hitsLabel } = item.summary;
					return (
						<li key={ item.index }>
							<button
								type="button"
								className={ cx( 'sdt-list__item', { 'is-selected': item.index === selected } ) }
								aria-current={ item.index === selected ? 'true' : undefined }
								onClick={ () => onSelect( item.index ) }
								onKeyDown={ evt => onKeyDown( evt, item.index ) }
							>
								<span className="sdt-list__row">
									<span className="sdt-list__label">
										<span className="sdt-list__name">{ item.label }</span>
										{ isEdited( item.index ) ? <span className="sdt-dot" title="Edited"><span className="sdt-visually-hidden">(edited)</span></span> : null }
									</span>
									<span className={ cx( 'sdt-mono', 'sdt-time', `is-${ speedClass( took, failed ) }` ) }>
										{ formatDuration( took ) }
										{ SPEED_TEXT[ speedClass( took, failed ) ] ? <span className="sdt-visually-hidden"> ({ SPEED_TEXT[ speedClass( took, failed ) ] })</span> : null }
									</span>
								</span>
								<span className="sdt-list__row sdt-list__row--sub">
									{ item.caller ? <CallerPath path={ item.caller } /> : <span className="sdt-list__caller sdt-mono">unknown caller</span> }
									<span className={ cx( 'sdt-list__hits', { 'is-bad': failed } ) }>{ item.sitesLabel ? `${ hitsLabel } · ${ item.sitesLabel }` : hitsLabel }</span>
								</span>
							</button>
						</li>
					);
				} ) }
			</ul>
		</nav>
	);
};
