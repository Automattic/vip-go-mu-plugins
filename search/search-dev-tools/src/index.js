// Uncomment when developing to enable Preact Dev Tools extension
// require( 'preact/debug' );

// This is important to make Webpack correctly load assets from the plugin url (as opposed to the default behavior to load from the document root)
import './webpack-public-path';

import { h } from 'preact';
import habitat from 'preact-habitat';

import SearchDevToolsApp from './components/app';

window.addEventListener( 'DOMContentLoaded', () => {
	const _habitat = habitat( SearchDevToolsApp );

	_habitat.render( {
		selector: '[data-widget-host="vip-search-dev-tools"]',
		clean: true,
	} );
} );
