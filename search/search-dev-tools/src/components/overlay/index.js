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
	const { children, closeOverlay, colorTheme = 'light', isVisible } = props;
	const closeWithEscape = callOnEscapeKey( closeOverlay );
	useEffect( () => {
		window.addEventListener( 'keydown', closeWithEscape );
		return () => {
			// Remove event listener to avoid memory leaks
			window.removeEventListener( 'keydown', closeWithEscape );
		};
	}, [] );

	return isVisible
		? (
			<div
				className={`search-dev-tools__overlay search-dev-tools__overlay--${ colorTheme }`} 
				role="dialog"
			>
				<button aria-label="Close VIP Search Dev Tools" className="search-dev-tools__overlay__close" onClick={ closeOverlay }><img src={ close } /></button>
				{ children }
			</div>
		)
		: null;
};

export default Overlay;
