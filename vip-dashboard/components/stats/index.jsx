/**
 * External dependencies
 */
var React = require( 'react' ),
	joinClasses = require( 'fbjs/lib/joinClasses' );

/**
 * Internal dependencies
 */

/**
 * Widget Component
 */
var Stats = React.createClass( {
	render: function() {
		return (
			<div className={ joinClasses( this.props.className, 'stats' ) }>
				{ this.props.children }
			</div>
		);
	}
} );

module.exports = Stats;
