import { useCallback, useEffect, useMemo, useRef, useState } from 'preact/hooks';

import { buildTreeLines } from '../tree-lines';

/**
 * Read-only, collapsible JSON viewer with line numbers.
 *
 * @param {Object} props       Props.
 * @param {*}      props.value JSON value to render.
 * @param {string}   props.mode     Fold mode: auto|expanded|collapsed. Remount (change `key`) to drop manual toggles.
 * @param {Function} props.annotate Optional ( path, value ) => note shown on a container's opening line.
 * @param {number}   props.maxLines Optional line limit; above it `renderTooLarge( lineCount )` renders instead.
 * @param {Function} props.renderTooLarge Fallback renderer for trees over `maxLines`.
 * @return {import('preact').VNode} JSON tree.
 */
export const JsonTree = ( { value, mode = 'auto', annotate, maxLines, renderTooLarge } ) => {
	const [ overrides, setOverrides ] = useState( {} );
	// Roving tabindex: only one toggle is a Tab stop; arrow keys move between toggles. The stop is moved on
	// the DOM directly, so moving focus doesn't re-render every line of a large tree.
	const rootRef = useRef( null );

	// The builder stops at maxLines, so a huge payload never allocates every row before falling back.
	// Depends on the limit, not on `renderTooLarge`, whose identity changes on every parent render.
	const lineLimit = renderTooLarge ? maxLines : undefined;
	const { lines, truncated } = useMemo(
		() => buildTreeLines( value, { mode, overrides, annotate, maxLines: lineLimit } ),
		[ value, overrides, mode, annotate, lineLimit ],
	);

	const toggle = useCallback( ( path, collapsed ) => {
		setOverrides( prev => ( { ...prev, [ path ]: ! collapsed } ) );
	}, [] );

	// After a re-render (rows are reused by index), keep exactly one toggle as the Tab stop, preferring the focused one.
	useEffect( () => {
		const stops = Array.from( rootRef.current?.querySelectorAll( 'button[data-path][tabindex="0"]' ) ?? [] );
		const keep = stops.find( btn => btn === document.activeElement ) ?? stops[ 0 ] ?? rootRef.current?.querySelector( 'button[data-path]' );
		stops.filter( btn => btn !== keep ).forEach( btn => btn.setAttribute( 'tabindex', '-1' ) );
		keep?.setAttribute( 'tabindex', '0' );
	}, [ lines ] );

	// Building line objects is cheap; drawing hundreds of thousands of rows is what freezes the page.
	if ( truncated ) {
		return renderTooLarge();
	}

	const moveTabStop = button => {
		rootRef.current?.querySelectorAll( 'button[data-path][tabindex="0"]' ).forEach( btn => {
			btn.setAttribute( 'tabindex', '-1' );
		} );
		button.setAttribute( 'tabindex', '0' );
	};

	const onKeyDown = evt => {
		const current = evt.target.closest?.( '[data-path]' );
		if ( ! current || ! rootRef.current ) {
			return;
		}
		const toggles = Array.from( rootRef.current.querySelectorAll( 'button[data-path]' ) );
		const idx = toggles.indexOf( current );
		const path = current.dataset.path;
		const expanded = current.getAttribute( 'aria-expanded' ) === 'true';
		let target = null;

		switch ( evt.key ) {
			case 'ArrowDown':
				target = toggles[ idx + 1 ];
				break;
			case 'ArrowUp':
				target = toggles[ idx - 1 ];
				break;
			case 'Home':
				target = toggles[ 0 ];
				break;
			case 'End':
				target = toggles.at( -1 );
				break;
			case 'ArrowRight':
				if ( ! expanded ) {
					toggle( path, true );
				} else {
					target = toggles[ idx + 1 ];
				}
				break;
			case 'ArrowLeft':
				if ( expanded && path !== '$' ) {
					toggle( path, false );
				} else if ( current.dataset.parent ) {
					// Move to the parent node's toggle.
					target = toggles.find( btn => btn.dataset.path === current.dataset.parent ) || null;
				}
				break;
			default:
				return;
		}
		evt.preventDefault();
		if ( target ) {
			target.focus();
		}
	};

	return (
		// eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions -- keydown delegation for the roving toggles
		<fieldset className="sdt-json" ref={ rootRef } onKeyDown={ onKeyDown } aria-label="Response JSON. Use arrow keys to move between nodes, Right to expand, Left to collapse.">
			{ lines.map( ( line, idx ) => (
				<div className="sdt-json__line" key={ line.key }>
					<span className="sdt-gutter-num" aria-hidden="true">{ idx + 1 }</span>
					<span className="sdt-json__toggle">
						{ line.path
							? (
								<button
									type="button"
									data-path={ line.path }
									data-parent={ line.parent ?? undefined }
									tabIndex={ line.path === '$' ? 0 : -1 }
									aria-expanded={ ! line.collapsed }
									aria-label={ `${ line.label }${ line.collapsed ? ', collapsed' : '' }` }
									onClick={ () => toggle( line.path, line.collapsed ) }
									onFocus={ evt => moveTabStop( evt.currentTarget ) }
								>
									{ line.collapsed ? '▸' : '▾' }
								</button>
							)
							: null }
					</span>
					<span className="sdt-json__code" style={ { paddingLeft: `${ line.depth * 2 }ch` } }>
						{ line.parts.map( ( [ type, text, slot ] ) => (
							type === 'summary'
								// Mouse shortcut only; keyboard users expand with the toggle.
								? <button type="button" tabIndex={ -1 } aria-hidden="true" key={ slot } className="sdt-tok-summary" onClick={ () => toggle( line.path, true ) }>{ text }</button>
								: <span key={ slot } className={ `sdt-tok-${ type }` }>{ text }</span>
						) ) }
					</span>
				</div>
			) ) }
		</fieldset>
	);
};
