<?php

function render_vip_dashboard_widget_welcome() {
	?>
	<div class="widget">
		<h2 class="widget__title">Welcome to WordPress VIP</h2>

    <p>WordPress VIP is a partnership between Automattic and the most high-profile, innovative and smart WordPress websites out there. We’re excited to have you here.</p>

    <h3 class="widget__subtitle">WPVIP Dashboard Links</h3>

    <a href="https://dashboard.wpvip.com/apps/<?= VIP_GO_APP_ID . "/" . VIP_GO_APP_ENVIRONMENT ?>" target="_blank">WPVIP Dashboard</a>

    <div class="widget__col-2">
      <ul class="widget__list">
        <li>
          <a href="https://dashboard.wpvip.com/apps/<?= VIP_GO_APP_ID . "/" . VIP_GO_APP_ENVIRONMENT ?>/perfomance/insights/http" target"_blank">HTTP Performance metrics</a>
          <span>Origin and Edge http performance metrics</span>
        </li>
        <li>
          <a href="https://dashboard.wpvip.com/apps/<?= VIP_GO_APP_ID . "/" . VIP_GO_APP_ENVIRONMENT ?>/perfomance/insights/database" target"_blank">Database Performance metrics</a>
          <span>Database performance metrics</span>
        </li>
      </ul>
    </div>
    <div class="widget__col-2">
      <ul class="widget__list">
        <li>
          <a href="https://dashboard.wpvip.com/apps/<?= VIP_GO_APP_ID . "/" . VIP_GO_APP_ENVIRONMENT ?>/perfomance/insights/resource-usage" target"_blank">Resource Usage</a>
          <span>Application Resource Usage</span>
        </li>
        <li>
          <a href="https://dashboard.wpvip.com/apps/<?= VIP_GO_APP_ID . "/" . VIP_GO_APP_ENVIRONMENT ?>/perfomance/insights/cache" target"_blank">Cache Performance metrics</a>
          <span>Cache performance metrics</span>
        </li>
      </ul>
    </div>

		<h3 class="widget__subtitle">Helpful Links</h3>

		<div class="widget__col-2">
			<ul class="widget__list">
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
					<a href="https://docs.wpvip.com/technical-references/vip-support/#h-submitting-a-ticket" target="_blank">Ticket guidelines</a>
					<span>How to open the perfect ticket</span>
				</li>
			</ul>
		</div>

		<div class="widget__col-2">
			<ul class="widget__list">
				<li>
					<a href="https://docs.wpvip.com/how-tos/launch-a-site/" target="_blank">Guidebook: Launching with VIP</a>
					<span>Steps to launch</span>
				</li>
				<li>
					<a href="https://docs.wpvip.com/technical-references/development-workflow/" target="_blank">Development Workflow on VIP</a>
					<span>An overview of VIP development</span>
				</li>
				<li>
					<a href="https://wpvip.com/blog/" target="_blank">VIP Blog</a>
					<span>New features, case studies</span>
				</li>
				<li>
					<a href="https://wpvip.com/partners/" target="_blank">Featured Partners</a>
					<span>Agencies and technology partners</span>
				</li>
			</ul>
		</div>
	</div>
	<?php
}
