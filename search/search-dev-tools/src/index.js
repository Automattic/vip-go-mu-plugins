// Uncomment when developing to enable Preact Dev Tools extension
// require( 'preact/debug' );

// This is important to make Webpack correctly load assets from the plugin url (as opposed to the default behavior to load from the document root)
import './webpack-public-path';

import { h, render } from 'preact';

import SearchDevToolsApp from './components/app';

const renderApp = () => render( <SearchDevToolsApp />, document.querySelector( '[data-widget-host="vip-search-dev-tools"]' ) );

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', renderApp );
} else {
	renderApp();
}
