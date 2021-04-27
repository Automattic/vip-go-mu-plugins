/**
 * External dependencies
 */
var React = require( 'react' ),
	ReactDOM = require( 'react-dom' ),
	// debug = require( 'debug' )( 'vip-dashboard' ),
	Chart = require( 'chart.js' );
	// LineChart = require( 'react-chartjs' ).Line;

/**
 * Internal dependencies
 */
var Main = require( './main' ),
	Header = require( './header' ),
	// Stats = require( './stats' ),
	// Stats_Charts = require( './stats-charts' ),
	// Stats_Numbers = require( './stats-numbers' ),
	Widget_Contact = require( './widget-contact' ),
	Widget_Welcome = require( './widget-welcome' );
	// Widget_Editorial = require( './widget-editorial' ),
	// Widget_Promo = require( './widget-promo' );

/**
 * Settings
 */
Chart.defaults.global.responsive = true;

var VIPdashboard = React.createClass( {
	getInitialState: function() {
		return {
			lineChartData: {
				labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
				datasets: [
					{
						label: 'Posts',
						fillColor: 'rgba(45,173,227,0.2)',
						strokeColor: 'rgba(45,173,227,1)',
						pointColor: 'rgba(220,220,220,1)',
						pointStrokeColor: '#fff',
						pointHighlightFill: '#fff',
						pointHighlightStroke: 'rgba(220,220,220,1)',
						data: [10, 35, 28, 50, 20, 50, 42]
					},
					{
						label: 'Comments',
						fillColor: 'rgba(245,169,28,0.2)',
						strokeColor: 'rgba(245,169,28,1)',
						pointColor: 'rgba(151,187,205,1)',
						pointStrokeColor: '#fff',
						pointHighlightFill: '#fff',
						pointHighlightStroke: 'rgba(151,187,205,1)',
						data: [16, 25, 22, 38, 46, 24, 50]
					}
				]
			}
		};
	},
	render: function() {
		return (
			<Main className="page-dashboard clearfix">

				<Header />

				{/** disabled for first version
				<Stats>
					<div className="stats__module">
						<LineChart data={this.state.lineChartData} />
						<div className="stats__numbers">
							<Stats_Numbers className="stats__posts" value={256} trend={10} description="New Published Posts" />
							<Stats_Numbers className="stats__comments" value={34} trend={6} description="New Comments" />
						</div>
					</div>

					<Stats_Charts />

					<div className="stats__module">
						<div className="stats__numbers numbers-data">
							<Stats_Numbers className="stats__total-posts" value={7632} trend={2} description="Total Published Posts" />
							<Stats_Numbers className="stats__total-users" value={123} trend={0} description="Total Users" />
							<Stats_Numbers className="stats__total-media" value={512} trend={2} description="Media Library (GB)" />
							<Stats_Numbers className="stats__total-loc" value={3759} trend={-5} description="Total Lines of Code" />
						</div>
					</div>
				</Stats>
				**/}

				<div className="widgets-area">

					<Widget_Welcome />

					<Widget_Contact />

					{/*
					<Widget_Promo />

					<Widget_Editorial />
					*/}
				</div>

			</Main>
		);
	}
} );

ReactDOM.render( <VIPdashboard />, document.getElementById( 'app' ) );
