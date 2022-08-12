/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	PanelRow,
	RadioControl,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { RecommendationsAttributes } from '../models/RecommendationsAttributes';

interface ParselyRecommendationsInspectorControlsProps {
	attributes: RecommendationsAttributes,
	setAttributes: ( attr: Partial<RecommendationsAttributes> ) => void,
}

const ParselyRecommendationsInspectorControls = ( {
	attributes: { boost, imagestyle, limit, openlinksinnewtab, showimages, sort, title },
	setAttributes,
} : ParselyRecommendationsInspectorControlsProps ) => (
	<InspectorControls>
		<PanelBody title="Settings" initialOpen={ true }>
			<PanelRow>
				<TextControl
					label={ __( 'Title', 'wp-parsely' ) }
					value={ title }
					onChange={ ( newval ) => setAttributes( { title: newval } ) }
				/>
			</PanelRow>
			<PanelRow>
				<RangeControl
					label={ __( 'Maximum Results', 'wp-parsely' ) }
					min={ 1 }
					max={ 25 }
					onChange={ ( newval ) => setAttributes( { limit: newval } ) }
					value={ limit }
				/>
			</PanelRow>
			<PanelRow>
				<ToggleControl
					label={ __( 'Open Links in New Tab', 'wp-parsely' ) }
					checked={ openlinksinnewtab }
					onChange={ () => setAttributes( { openlinksinnewtab: ! openlinksinnewtab } ) }
				/>
			</PanelRow>
			<PanelRow>
				<ToggleControl
					label={ __( 'Show Images', 'wp-parsely' ) }
					help={
						showimages
							? __( 'Showing images', 'wp-parsely' )
							: __( 'Not showing images', 'wp-parsely' )
					}
					checked={ showimages }
					onChange={ () => setAttributes( { showimages: ! showimages } ) }
				/>
			</PanelRow>
			{ showimages && (
				<PanelRow>
					<RadioControl
						label={ __( 'Image style', 'wp-parsely' ) }
						selected={ imagestyle }
						options={ [
							{ label: __( 'Original image', 'wp-parsely' ), value: 'original' },
							{ label: __( 'Thumbnail from Parse.ly', 'wp-parsely' ), value: 'thumbnail' },
						] }
						onChange={ ( newval ) =>
							setAttributes( {
								imagestyle: newval === 'original' ? 'original' : 'thumbnail',
							} )
						}
					/>
				</PanelRow>
			) }
			<PanelRow>
				<SelectControl
					label={ __( 'Sort Recommendations', 'wp-parsely' ) }
					value={ sort }
					options={ [
						{
							label: __( 'Score', 'wp-parsely' ),
							value: 'score',
						},
						{
							label: __( 'Publication Date', 'wp-parsely' ),
							value: 'pub_date',
						},
					] }
					onChange={ ( newval ) => setAttributes( { sort: newval } ) }
				/>
			</PanelRow>
			<PanelRow>
				<SelectControl
					label={ __( 'Boost', 'wp-parsely' ) }
					value={ boost }
					options={ [
						{
							label: __( 'Page views', 'wp-parsely' ),
							value: 'views',
						},
						{
							label: __( 'Page views on mobile devices', 'wp-parsely' ),
							value: 'mobile_views',
						},
						{
							label: __( 'Page views on tablet devices', 'wp-parsely' ),
							value: 'tablet_views',
						},
						{
							label: __( 'Page views on desktop devices', 'wp-parsely' ),
							value: 'desktop_views',
						},
						{
							label: __( 'Unique page visitors', 'wp-parsely' ),
							value: 'visitors',
						},
						{
							label: __( 'New visitors', 'wp-parsely' ),
							value: 'visitors_new',
						},
						{
							label: __( 'Returning visitors', 'wp-parsely' ),
							value: 'visitors_returning',
						},
						{
							label: __( 'Total engagement time in minutes', 'wp-parsely' ),
							value: 'engaged_minutes',
						},
						{
							label: __( 'Engaged minutes spent by total visitors', 'wp-parsely' ),
							value: 'avg_engaged',
						},
						{
							label: __( 'Average engaged minutes spent by new visitors', 'wp-parsely' ),
							value: 'avg_engaged_new',
						},
						{
							label: __( 'Average engaged minutes spent by returning visitors', 'wp-parsely' ),
							value: 'avg_engaged_returning',
						},
						{
							label: __( 'Total social interactions', 'wp-parsely' ),
							value: 'social_interactions',
						},
						{
							label: __( 'Count of Facebook shares, likes, and comments', 'wp-parsely' ),
							value: 'fb_interactions',
						},
						{
							label: __( 'Count of Twitter tweets and retweets', 'wp-parsely' ),
							value: 'tw_interactions',
						},
						{
							label: __( 'Count of Pinterest pins', 'wp-parsely' ),
							value: 'pi_interactions',
						},
						{
							label: __( 'Page views where the referrer was any social network', 'wp-parsely' ),
							value: 'social_referrals',
						},
						{
							label: __( 'Page views where the referrer was facebook.com', 'wp-parsely' ),
							value: 'fb_referrals',
						},
						{
							label: __( 'Page views where the referrer was twitter.com', 'wp-parsely' ),
							value: 'tw_referrals',
						},
						{
							label: __( 'Page views where the referrer was pinterest.com', 'wp-parsely' ),
							value: 'pi_referrals',
						},
					] }
					onChange={ ( newval ) => setAttributes( { boost: newval } ) }
				/>
			</PanelRow>
		</PanelBody>
	</InspectorControls>
);

export default ParselyRecommendationsInspectorControls;
