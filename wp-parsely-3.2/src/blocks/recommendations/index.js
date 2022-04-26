/**
 * External dependencies
 */
import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import ParselyRecommendations from './components/parsely-recommendations';
import RecommendationsStore from './recommendations-store';

domReady( () => {
	const blocks = document.querySelectorAll( '.wp-block-wp-parsely-recommendations' );
	blocks.forEach( ( block, i ) =>
		render(
			<RecommendationsStore>
				<ParselyRecommendations { ...block.dataset } key={ i } />
			</RecommendationsStore>,
			block
		)
	);
} );
