/**
 * External dependencies
 */
var React = require( 'react/addons' ),
	joinClasses = require( 'fbjs/lib/joinClasses' ),
	omit = require( 'lodash/object/omit' );

module.exports = React.createClass( {

	displayName: 'FormUl',

	render: function() {
		return (
			<ul { ...omit( this.props, 'className' ) } className={ joinClasses( this.props.className, 'form-ul' ) } >
				{ this.props.children }
			</ul>
		);
	}
} );
