/**
 * External dependencies
 */
var React = require( 'react' );

/**
 * Internal dependencies
 */

/**
 * Counter component
 */
var CountTo = React.createClass( {
	propTypes: {
		from: React.PropTypes.number,
		to: React.PropTypes.number.isRequired,
		speed: React.PropTypes.number.isRequired,
		delay: React.PropTypes.number,
		onComplete: React.PropTypes.func
	},

	getInitialState: function() {
		return {
			counter: this.props.from || 0
		};
	},

	componentDidMount: function() {
		var delay = this.props.delay || 100;
		this.loopsCounter = 0;
		this.loops = Math.ceil( this.props.speed / delay );
		this.increment = ( this.props.to - this.state.counter ) / this.loops;
		this.interval = setInterval( this.next, delay );
	},

	componentWillUnmount: function() {
		this.clear();
	},

	componentWillUpdate: function() {
		//alert( this.state.counter );
		//delay = this.props.delay || 100;
		//this.interval = setInterval(this.next, delay);
	},

	next: function() {
		if ( this.loopsCounter < this.loops ) {
			this.loopsCounter++;
			this.setState( {
				counter: this.state.counter + this.increment
			} );
		} else {
			this.clear();
			if ( this.props.onComplete ) {
				this.props.onComplete();
			}
		}
	},

	clear: function() {
		clearInterval( this.interval );
	},

	render: function() {
		return (
			<span>{this.state.counter.toFixed()}</span>
		);
	}
} );

module.exports = CountTo;
