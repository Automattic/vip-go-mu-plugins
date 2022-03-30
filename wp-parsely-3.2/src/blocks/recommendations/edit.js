/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { createBlock, registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import ParselyRecommendations from './components/parsely-recommendations';
import ParselyRecommendationsInspectorControls from './components/parsely-recommendations-inspector-controls';
import RecommendationsStore from './recommendations-store';
import { ReactComponent as LeafIcon } from './parsely-logo.svg';

import './style.scss';

export const ParselyRecommendationsEdit = ( editProps ) => (
	<div { ...useBlockProps() }>
		<RecommendationsStore clientId={ editProps.clientId }>
			<ParselyRecommendationsInspectorControls { ...editProps } />
			<ParselyRecommendations { ...editProps.attributes } isEditMode="true" />
		</RecommendationsStore>
	</div>
);

registerBlockType( 'wp-parsely/recommendations', {
	apiVersion: 2,
	title: __( 'Parse.ly Recommendations', 'wp-parsely' ),
	icon: LeafIcon,
	category: 'widgets',
	edit: ParselyRecommendationsEdit,
	transforms: {
		from: [
			{
				type: 'block',
				blocks: [ 'core/legacy-widget' ],
				isMatch: ( { idBase, instance } ) => {
					if ( ! instance?.raw ) {
						// Can't transform if raw instance is not shown in REST API.
						return false;
					}
					return idBase === 'Parsely_Recommended_Widget';
				},
				transform: ( { instance } ) => {
					return createBlock( 'wp-parsely/recommendations', {
						name: instance.raw.name,
					} );
				},
			},
		],
	},
} );
