/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable wpcalypso/import-docblock */
import './style';
import { h, } from 'preact';
import habitat from 'preact-habitat';

import Widget from './components/app';

let _habitat = habitat( Widget );

_habitat.render({
	selector: '[data-widget-host="vip-search-dev-tools"]',
	clean: true
});