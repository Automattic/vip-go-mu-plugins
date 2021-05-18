/**
 * External dependencies
 */
var React = require( 'react/addons' ),
	joinClasses = require( 'fbjs/lib/joinClasses' ),
	omit = require( 'lodash/object/omit' ),
	classNames = require( 'classnames' );

module.exports = React.createClass( {

	displayName: 'FormTextInput',

	getDefaultProps: function() {
		return {
			isPassword: false,
			isError: false,
			isValid: false
		};
	},

	render: function() {
		var otherProps = omit( this.props, [ 'className', 'type' ] ),
			classes = classNames( {
				'form-text-input': true,
				'is-error': this.props.isError,
				'is-valid': this.props.isValid
			} );

		return (
			<input
				{ ...otherProps }
				type={ this.props.isPassword ? 'password' : 'text' }
				className={ joinClasses( this.props.className, classes ) } />
		);
	}
} );
