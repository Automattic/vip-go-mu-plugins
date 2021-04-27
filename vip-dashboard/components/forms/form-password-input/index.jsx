/**
 * External dependencies
 */
var React = require( 'react/addons' ),
	classNames = require( 'classnames' );

/**
 * Internal dependencies
 */
var FormTextInput = require( 'forms/form-text-input' );

module.exports = React.createClass( {

	displayName: 'FormPasswordInput',

	getInitialState: function() {
		return {
			hidePassword: false
		};
	},

	togglePasswordVisibility: function() {
		this.setState( { hidePassword: ! this.state.hidePassword } );
	},

	hidden: function() {
		return this.props.submitting || this.state.hidePassword;
	},

	render: function() {
		var toggleVisibilityClasses = classNames( {
			'form-password-input__toggle-visibility': true,
			'is-hidden': this.props.submitting,
			'is-visible': ! this.props.submitting
		} );

		return (
			<div className="form-password-input">
				<FormTextInput { ...this.props } isPassword={ this.hidden() } />
				<span className={ toggleVisibilityClasses } onClick={ this.togglePasswordVisibility } />
			</div>
		);
	}
} );
