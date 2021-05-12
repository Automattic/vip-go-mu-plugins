/**
 * External dependencies
 */
var React = require( 'react' ),
	ReactDOM = require( 'react-dom' );

/**
 * Internal dependencies
 */
var Main = require( './main' ),
	Header = require( './header' ),
	Widget_Contact = require( './widget-contact' ),
	Widget_Welcome = require( './widget-welcome' );

var VIPdashboard = React.createClass( {
	render: function() {
		return (
			<Main className="page-dashboard clearfix">
				<Header />

				<div className="widgets-area">

					<Widget_Welcome />

					<Widget_Contact />

				</div>
			</Main>
		);
	}
} );

ReactDOM.render( <VIPdashboard />, document.getElementById( 'app' ) );
