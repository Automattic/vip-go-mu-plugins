/**
 * External dependencies
 */
var React = require( 'react' ),
	joinClasses = require( 'fbjs/lib/joinClasses' );

/**
 * Internal dependencies
 */
//var CounTo = require( '../count' );

/**
 * Stats Number Component
 */
var Stats_Numbers = React.createClass( {
	getInitialState: function() {
		return {
			value: this.props.value,
			trend: this.props.trend,
			type: this.props.type,
		};
	},

	spin: function( e ) {
		this.setState( {
			value: ( Math.floor( Math.random() * 10000 ) + 1 ),
			trend: Math.floor( Math.random() * 20 ) - 10
		} );
	},
	render: function() {
		var trend = '';

		if ( this.state.trend > 0 ) {
			trend = 'trend-positive';
		} else if ( this.state.trend < 0 ) {
			trend = 'trend-negative';
		} else {
			trend = 'trend-neutral';
		}

		if ( this.state.type === 'chart' ) {
			return (
				<div className={ this.props.className } onClick={this.spin}>
					<span className="numbers__value">{ this.state.value + '%' }</span>
					<span className="numbers__description">{ this.props.description }</span>
					<span className={ joinClasses( trend, 'numbers__trend trend-center' )}>{ this.state.trend + '%' }</span>
				</div>
			);
		} else {
			return (
				<div className={ this.props.className } onClick={this.spin}>
					<span className="numbers__value"><CountTo to={ this.state.value } from={0} speed={ 500 } /></span>
					<span className={ joinClasses( trend, 'numbers__trend' )}>{ this.state.trend + '%' }</span>
					<span className="numbers__description">{ this.props.description }</span>
				</div>
			);
		}
	}
} );

module.exports = Stats_Numbers;
