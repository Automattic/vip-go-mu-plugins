// Uncomment when developing to enable Preact Dev Tools extension
// require( 'preact/debug' );

import { render } from 'preact';

import SearchDevToolsApp from './components/app';
import './style/admin-bar.scss';

const renderApp = () => render( <SearchDevToolsApp />, document.querySelector( '[data-widget-host="vip-search-dev-tools"]' ) );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', renderApp );
} else {
	renderApp();
}
