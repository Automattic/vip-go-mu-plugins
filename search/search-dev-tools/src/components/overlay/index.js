import { h } from 'preact';
import { useEffect } from 'preact/hooks';

import './style.scss';
import { callOnEscapeKey } from '../../utils';
import close from '../../assets/close.svg';

/**
 * Overlay. Borrowed from Jetpack Instant Search.
 * @param {Object} props
 * @returns {preact.VNode} Overlay that contains Dev Tools UI
 */
const Overlay = props => {
	const { children, closeOverlay, colorTheme, isVisible, opacity } = props;
	const closeWithEscape = callOnEscapeKey( closeOverlay );
	useEffect( () => {
		window.addEventListener( 'keydown', closeWithEscape );
		return () => {
			// Cleanup after event
			window.removeEventListener( 'keydown', closeWithEscape );
		};
	}, [] );

	return (
		<div
			aria-labelledby="search-dev-tools__overlay-title"
			className={[
				'search-dev-tools__overlay',
				`search-dev-tools__overlay--${ colorTheme }`,
				isVisible ? '' : 'is-hidden',
			].join( ' ' )}
			role="dialog"
			style={{ opacity: isVisible ? opacity / 100 : 0 }}
		>
			<button aria-label="Close VIP Search Dev Tools" className="search-dev-tools__overlay__close" onClick={ closeOverlay }><img src={ close } /></button>
			{ children }
		</div>
	);
};

export default Overlay;
