import cx from 'classnames';
import { createPortal, forwardRef } from 'preact/compat';
import { useCallback, useContext, useEffect, useMemo, useRef, useState } from 'preact/hooks';

// Global styles
import '../style/style.scss';

import { InfoStrip } from './info-strip';
import { QueryDetail } from './query-detail';
import { QueryList } from './query-list';
import { AnnounceContext, SearchContext } from '../context';
import { describeQuery, formatDuration, isDraftEdited } from '../utils';

const THEME_KEY = 'vip-search-dev-tools-theme';

const readStoredTheme = () => {
	try {
		const stored = window.localStorage.getItem( THEME_KEY );
		if ( stored === 'light' || stored === 'dark' ) {
			return stored;
		}
	} catch {
		// Storage unavailable; fall through to the system preference.
	}
	return window.matchMedia?.( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
};

const storeTheme = theme => {
	try {
		window.localStorage.setItem( THEME_KEY, theme );
	} catch {
		// Not critical.
	}
};

/**
 * Pull the ES major version out of the info list, for the admin bar label during migrations.
 *
 * @param {Array} information Info items.
 * @return {string} e.g. " (ES8)" or "".
 */
const migrationSuffix = information => {
	const esInfo = information?.find( info => info.key === 'es_version' || info.label === 'Elasticsearch Version' );
	const value = typeof esInfo?.value === 'string' ? esInfo.value : '';
	if ( ! value.includes( 'Migration' ) ) {
		return '';
	}
	return value.includes( 'ES8' ) ? ' (ES8)' : ' (ES7)';
};

const AdminBarButton = forwardRef( ( { onClick, expanded, hasFailures }, ref ) => {
	const { queries, information } = useContext( SearchContext );
	return (
		<button
			ref={ ref }
			type="button"
			className="sdt-ab-btn"
			onClick={ onClick }
			aria-expanded={ expanded }
			aria-haspopup="dialog"
		>
			Search: { queries.length }Q{ migrationSuffix( information ) }
			{ hasFailures ? <span className="sdt-ab-btn__alert" title="A query failed"><span className="screen-reader-text">, a query failed</span></span> : null }
		</button>
	);
} );

const CloseIcon = () => (
	<svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
		<path d="M3.5 3.5l9 9m0-9l-9 9" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
	</svg>
);

/**
 * Visible, keyboard-focusable elements inside the panel, in DOM order.
 *
 * @param {HTMLElement} panel Panel element.
 * @return {HTMLElement[]} Focusable elements.
 */
const panelFocusables = panel => Array.from( panel?.querySelectorAll( 'button:not([disabled]):not([tabindex="-1"]), textarea, input, select, a[href], [tabindex="0"]' ) ?? [] )
	.filter( el => el.offsetParent !== null );

/**
 * The full-screen Dev Tools panel.
 * Selection and drafts live in App so closing the panel doesn't discard edits.
 *
 * @param {Object}   props               Props.
 * @param {Array}    props.items         Query view models (see describeQuery).
 * @param {number}   props.selected      Selected query index.
 * @param {Function} props.onSelect      Selection handler.
 * @param {Object}   props.drafts        Drafts keyed by query index.
 * @param {Function} props.onDraftChange ( index, draft | updater | undefined ) => void.
 * @param {Function} props.onClose       Close handler.
 * @return {import('preact').VNode} Panel.
 */
const Panel = ( { items, selected, onSelect, drafts, onDraftChange, onClose } ) => {
	const { queries, information } = useContext( SearchContext );
	const [ theme, setTheme ] = useState( readStoredTheme );
	const [ announcement, setAnnouncement ] = useState( '' );
	const panelRef = useRef( null );

	// Clear first so repeating the same message is announced again; the pending timer is cancelled on close.
	const [ announce ] = useState( () => {
		let timer;
		const announceMessage = message => {
			clearTimeout( timer );
			setAnnouncement( '' );
			timer = setTimeout( () => setAnnouncement( message ), 50 );
		};
		announceMessage.cancel = () => clearTimeout( timer );
		return announceMessage;
	} );
	useEffect( () => () => announce.cancel(), [ announce ] );

	// aria-modal: keep Tab inside the panel. The editor handles (and prevents) Tab itself while it captures it.
	const trapFocus = evt => {
		if ( evt.key !== 'Tab' || evt.defaultPrevented ) {
			return;
		}
		const focusables = panelFocusables( evt.currentTarget );
		if ( ! focusables.length ) {
			return;
		}
		const index = focusables.indexOf( document.activeElement );
		// Shift+Tab from the first control, or from the panel itself (focused on open), wraps to the last.
		if ( evt.shiftKey && ( index === 0 || evt.target === evt.currentTarget ) ) {
			evt.preventDefault();
			focusables.at( -1 ).focus();
		} else if ( ! evt.shiftKey && index === focusables.length - 1 ) {
			evt.preventDefault();
			focusables[ 0 ].focus();
		}
	};

	const totalTime = items.reduce( ( sum, item ) => sum + ( item.summary.took || 0 ), 0 );

	// Containment for focus that leaves the panel (e.g. clicking an Admin Bar control, which sits above it):
	// bring it back, and route Tab from outside into the panel. The Admin Bar's Search button stays usable
	// so it can still close the panel.
	useEffect( () => {
		const panel = panelRef.current;
		const outside = el => el && panel && ! panel.contains( el ) && ! el.closest?.( '#wp-admin-bar-vip-search-dev-tools' );
		const onFocusIn = evt => {
			if ( outside( evt.target ) ) {
				( panelFocusables( panel )[ 0 ] ?? panel ).focus();
			}
		};
		const onTab = evt => {
			if ( evt.key !== 'Tab' || ! panel || panel.contains( document.activeElement ) ) {
				return;
			}
			evt.preventDefault();
			const focusables = panelFocusables( panel );
			( evt.shiftKey ? focusables.at( -1 ) : focusables[ 0 ] )?.focus();
		};
		document.addEventListener( 'focusin', onFocusIn, true );
		document.addEventListener( 'keydown', onTab, true );
		return () => {
			document.removeEventListener( 'focusin', onFocusIn, true );
			document.removeEventListener( 'keydown', onTab, true );
		};
	}, [] );

	useEffect( () => {
		const onKey = evt => {
			// Escape inside the request editor releases its Tab capture; don't close the panel for it.
			if ( evt.key !== 'Escape' || evt.target?.closest?.( '.sdt-code' ) ) {
				return;
			}
			// Don't check defaultPrevented: the admin bar cancels Escape when its button has focus.
			onClose();
		};
		window.addEventListener( 'keydown', onKey );
		const { overflow } = document.documentElement.style;
		document.documentElement.style.overflow = 'hidden';
		panelRef.current?.focus();
		return () => {
			window.removeEventListener( 'keydown', onKey );
			document.documentElement.style.overflow = overflow;
		};
	}, [ onClose ] );

	const chooseTheme = value => {
		setTheme( value );
		storeTheme( value );
	};

	return (
		<dialog
			open
			className="sdt"
			data-theme={ theme }
			aria-modal="true"
			aria-labelledby="sdt-title"
			tabIndex={ -1 }
			ref={ panelRef }
			onKeyDown={ trapFocus }
		>
			<div className="sdt-visually-hidden" aria-live="polite" aria-atomic="true" data-testid="sdt-live">{ announcement }</div>
			<AnnounceContext.Provider value={ announce }>
				{ /* A div, not <header>: a header outside sectioning content is a second page banner landmark. */ }
				<div className="sdt-header">
					<div className="sdt-header__title">
						<h1 id="sdt-title">Enterprise Search Dev Tools</h1>
						<span className="sdt-muted">
							{ queries.length } { queries.length === 1 ? 'query' : 'queries' } · { formatDuration( totalTime ) }
						</span>
					</div>
					<div className="sdt-header__actions">
						<fieldset className="sdt-segmented" aria-label="Color theme">
							{ [ 'light', 'dark' ].map( value => (
								<button
									key={ value }
									type="button"
									aria-pressed={ theme === value }
									className={ cx( { 'is-active': theme === value } ) }
									onClick={ () => chooseTheme( value ) }
							>
									{ value === 'light' ? 'Light' : 'Dark' }
								</button>
						) ) }
						</fieldset>
						<button type="button" className="sdt-icon-btn" onClick={ onClose } aria-label="Close VIP Search Dev Tools">
							<CloseIcon />
						</button>
					</div>
				</div>

				<InfoStrip information={ information } />

				{ items.length
				? (
					<div className="sdt-body">
						<QueryList
							items={ items }
							selected={ selected }
							onSelect={ onSelect }
							isEdited={ idx => isDraftEdited( drafts[ idx ], items[ idx ].requestText ) }
						/>
						<QueryDetail
							key={ selected }
							query={ queries[ selected ] }
							meta={ items[ selected ] }
							draft={ drafts[ selected ] }
							onDraftChange={ change => onDraftChange( selected, change ) }
						/>
					</div>
				)
				: (
					<div className="sdt-body sdt-body--empty">
						<p>No Elasticsearch queries ran on this page.</p>
					</div>
				) }
			</AnnounceContext.Provider>
		</dialog>
	);
};

/**
 * The Main app component.
 * It mounts onto an existing DOM node in the Admin Bar and then renders into a Portal
 * to avoid any interference of Admin Bar CSS.
 *
 * @return {import('preact').VNode} Top-level app component
 */
const App = () => {
	const [ visible, setVisible ] = useState( false );
	const buttonRef = useRef( null );
	const close = useCallback( () => {
		setVisible( false );
		buttonRef.current?.focus();
	}, [] );
	const toggle = useCallback( () => setVisible( prev => ! prev ), [] );
	const [ selected, setSelected ] = useState( 0 );
	const [ drafts, setDrafts ] = useState( {} );
	const data = window?.VIPSearchDevTools || { status: 'disabled', queries: [], information: [] };
	const items = useMemo( () => data.queries.map( describeQuery ), [ data.queries ] );
	const hasFailures = items.some( item => item.summary.failed );
	const portal = document.getElementById( 'search-dev-tools-portal' );

	/**
	 * Update one query's draft. `change` is a draft, an updater ( prev ) => draft, or undefined to discard.
	 * Updaters let async runs merge into whatever the user typed meanwhile.
	 */
	const updateDraft = useCallback( ( index, change ) => {
		setDrafts( prev => {
			const value = typeof change === 'function' ? change( prev[ index ] ) : change;
			const next = { ...prev };
			if ( value === undefined ) {
				delete next[ index ];
			} else {
				next[ index ] = value;
			}
			return next;
		} );
	}, [] );

	const panel = (
		<Panel
			items={ items }
			selected={ selected }
			onSelect={ setSelected }
			drafts={ drafts }
			onDraftChange={ updateDraft }
			onClose={ close }
		/>
	);

	return (
		<SearchContext.Provider value={ data }>
			<AdminBarButton ref={ buttonRef } onClick={ toggle } expanded={ visible } hasFailures={ hasFailures } />
			{ visible && portal ? createPortal( panel, portal ) : null }
		</SearchContext.Provider>
	);
};

export default App;
