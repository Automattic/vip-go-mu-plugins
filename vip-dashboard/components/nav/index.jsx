/**
 * External dependencies
 */
var React = require( 'react' );

/**
 * Internal dependencies
 */
var Config = require( '../config.js' );

/**
 * Navigation component
 */
var Nav = React.createClass( {
	getInitialState: function() {
		return {
			focused: 0
		};
	},

	clicked: function( index ) {
		this.setState( { focused: index } );
	},

	render: function() {
		var self = this;

		// loop over the array of menu entries,
		return (
			<div className="top-header__menu">
				<ul>{ this.props.items.map( function( m, index ) {
					var style = '';

					if ( self.state.focused === index ) {
						style = 'active';
					}

					return <li key={index}>
						<a className={ style } href={ Config.adminurl + '?page=' + m.url } onClick={ self.clicked.bind( self, index ) }>{ m.title }</a>
					</li>;
				} ) }
				</ul>
			</div>
		);
	}
} );

module.exports = Nav;
