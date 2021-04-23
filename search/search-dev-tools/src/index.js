// TODO: disable Preact dev tools for production builds
require( 'preact/debug' );
import './webpack-public-path';

import { h } from 'preact';
import habitat from 'preact-habitat';

import Widget from './components/app';

window.addEventListener( 'DOMContentLoaded', () => {
	const _habitat = habitat( Widget );

	_habitat.render( {
		selector: '[data-widget-host="vip-search-dev-tools"]',
		clean: true,
	} );
} );
