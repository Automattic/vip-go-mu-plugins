/**
 * External dependencies
 */
var React = require( 'react' );

/**
 * Internal dependencies
 */
var Widget = require( '../widget' );

/**
 * Welcome Widget Component
 */
var Widget_Welcome = React.createClass( {
	render: function() {
		return (
			<Widget className="widget__welcome" title="Welcome to WordPress.com VIP">
				<p>WordPress.com VIP is a partnership between WordPress.com and the most high-profile, innovative and smart WordPress websites out there. We’re excited to have you here.</p>

				<h3 className="widget__subtitle">Helpful Links</h3>

				<div className="widget__col-2">
					<ul className="widget__list">
						<li>
							<a href="https://lobby.vip.wordpress.com/" target="_blank">VIP Lobby</a>
							<span>Important service updates</span>
						</li>
						<li>
							<a href="https://docs.wpvip.com/" target="_blank">VIP Documentation</a>
							<span>Launching and developing with VIP</span>
						</li>
						<li>
							<a href="https://wordpressvip.zendesk.com/" target="_blank">VIP Support Portal</a>
							<span>Your organization’s tickets</span>
						</li>
						<li>
							<a href="https://docs.wpvip.com/technical-references/vip-support/general-ticket-guidelines/" target="_blank">Ticket guidelines</a>
							<span>How to open the perfect ticket</span>
						</li>
					</ul>
				</div>

				<div className="widget__col-2">
					<ul className="widget__list">
						<li>
							<a href="https://docs.wpvip.com/how-tos/launch-a-site-with-vip/" target="_blank">Guidebook: Launching with VIP</a>
							<span>Steps to launch</span>
						</li>
						<li>
							<a href="https://wpvip.com/documentation/developing-with-vip/welcome-to-vip-development/" target="_blank">Guidebook: Developing with VIP</a>
							<span>An overview of VIP development</span>
						</li>
						<li>
							<a href="https://wpvip.com/news/" target="_blank">VIP News</a>
							<span>New features, case studies</span>
						</li>
						<li>
							<a href="https://wpvip.com/partners/" target="_blank">Featured Partners</a>
							<span>Agencies and technology partners</span>
						</li>
					</ul>
				</div>
			</Widget>
		);
	}
} );

module.exports = Widget_Welcome;
