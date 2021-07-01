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
var Widget = React.createClass( {
	maybeRenderTitle: function() {
		if ( this.props.title ) {
			return <h2 className="widget__title">{this.props.title}</h2>;
		}
	},
	render: function() {
		return (
			<div className={ joinClasses( this.props.className, 'widget' ) }>
				{ this.maybeRenderTitle() }
				{ this.props.children }
			</div>
		);
	}
} );

module.exports = Widget;
