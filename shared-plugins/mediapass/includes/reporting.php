<div class="wrap">
	<h2 class="header"><img src="<?php echo plugins_url('/images/logo-icon.png', dirname(__FILE__)) ?>" class="mp-icon" /><span>Reporting</span></h2>
	<p class="subtitle" style="padding-left:0px;">View your reports by month or year.</p>
	<br/>
		<form class="form-inline">
    <span>Charts for : </span>
        <select class="span2" id="charts_select">
            <option value="1">This Month</option>
            <option value="2">This Year</option>
    </select> 
</form>
</div>

<div id="chart_ecpm" style="height:350px;width:550px; "></div>
<div id="chart_impressions" style="height:350px;width:550px; "></div>
<div id="chart_sales" style="height:350px;width:550px; "></div>


<script type="text/javascript">
var monthly_data = <?php echo  $data['monthly'] ?>;

var yearly_data = <?php echo $data['yearly'] ?>;
</script>

<script type="text/javascript">
    jQuery(document).ready(function () {
        window.Dashboard = new MM.FE.UI.Dashboard();
        window.Dashboard.init();
    });

</script>
