/**
 * External dependencies
 */
var React = require( 'react/addons' ),
	joinClasses = require( 'fbjs/lib/joinClasses' ),
	omit = require( 'lodash/object/omit' ),
	isEmpty = require( 'lodash/lang/isEmpty' );

module.exports = React.createClass( {

	displayName: 'FormsButton',

	getDefaultProps: function() {
		return {
			isSubmitting: false,
			isPrimary: true
		};
	},

	getDefaultButtonAction: function() {
		return this.props.isSubmitting ? this.translate( 'Saving…' ) : this.translate( 'Save Settings' );
	},

	render: function() {
		var buttonClasses = React.addons.classSet( {
			button: true,
			'form-button': true,
			'is-primary': this.props.isPrimary
		} );

		return (
			<button
				{ ...omit( this.props, 'className' ) }
				className={ joinClasses( this.props.className, buttonClasses ) } >
				{ isEmpty( this.props.children ) ? this.getDefaultButtonAction() : this.props.children }
			</button>
		);
	}
} );
