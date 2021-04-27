/**
 * External dependencies
 */
var React = require( 'react' );

/**
 * Internal dependencies
 */
var Config = require( '../config.js' ),
	Widget = require( '../widget' );

/**
 * Promo Widget Component
 */
var Widget_Promo = React.createClass( {
	render: function() {
		return (
			<Widget className="widget-small widget__promo">
				<div className="widget__content">
					<a href="https://wpvip.com/events/" title="VIP Events">
						<img src={ Config.asseturl + 'img/vip-workshop-logo.svg' } alt="VIP Events" className="promo-logo" />
						<h3 className="promo-text">WordPress.com VIP Training Days</h3>
					</a>
				</div>
			</Widget>
		);
	}
} );

module.exports = Widget_Promo;
