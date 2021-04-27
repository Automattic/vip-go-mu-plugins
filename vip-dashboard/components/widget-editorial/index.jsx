/**
 * External dependencies
 */
var React = require( 'react' );

/**
 * Internal dependencies
 */
var Widget = require( '../widget' );

/**
 * Editorial Widget Component
 */
var Widget_Editorial = React.createClass( {
	render: function() {
		return (
			<Widget className="widget-small widget__tips" title="Editorial Tips">
				<div className="widget__content">
					<p>Placeholder</p>
				</div>

			</Widget>
		);
	}
} );

module.exports = Widget_Editorial;
