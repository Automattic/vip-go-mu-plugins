import { h } from 'preact';
import { useEffect, useMemo } from 'preact/hooks';

import './style.scss';
import close from '../../assets/close.svg';

/**
 * Overlay. Borrowed from Jetpack Instant Search.
 *
 * @param {Object} props needed by the component
 * @return {import('preact').VNode} Overlay that contains Dev Tools UI
 */
const Overlay = props => {
	const { children, closeOverlay, colorTheme = 'light', isVisible } = props;
	const closeWithEscape = useMemo( () => (event => event.key === 'Escape' && closeOverlay()), [ closeOverlay ] );
	useEffect( () => {
		window.addEventListener( 'keydown', closeWithEscape );
		return () => {
			// Remove event listener to avoid memory leaks
			window.removeEventListener( 'keydown', closeWithEscape );
		};
	}, [ closeWithEscape ] );

	return isVisible
		? (
			<div
				className={ `search-dev-tools__overlay search-dev-tools__overlay--${ colorTheme }` }
				role="dialog"
			>
				<button aria-label="Close VIP Search Dev Tools" className="search-dev-tools__overlay__close" onClick={ closeOverlay }><img src={ close } alt="Close" /></button>
				{ children }
			</div>
		)
		: null;
};

export default Overlay;
