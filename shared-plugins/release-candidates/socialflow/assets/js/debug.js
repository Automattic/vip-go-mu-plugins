jQuery(function($){

	var now = new Date(),
		gmtTime = new Date(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(),  now.getUTCHours(), now.getUTCMinutes(), now.getUTCSeconds(), now.getUTCMilliseconds()),
		userTime = new Date(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate(),  now.getUTCHours(), now.getUTCMinutes(), now.getUTCSeconds(), now.getUTCMilliseconds()+( debugData.tzOffset * 1000 ) );


	//console.log(debugData.tzOffset);

	// Change js date to homan readable format

	// Set current mysql time
	$('#js-debug-current-time').html( now.toString('yyyy-MM-dd HH:mm') );
	$('#js-debug-current-gmt-time').html( gmtTime.toString('yyyy-MM-dd HH:mm') );
	$('#js-debug-current-gmt-offset-time').html( userTime.toString('yyyy-MM-dd HH:mm') );

	/**
	 * Format passed date to readable format
	 * @param  {Date} date passed js Date object
	 * @return {string}      formated date string
	 */
	/*
	function formatDate(date) {
		return $.datepicker.formatDate('yy-mm-dd ', now) + now.getHours() + ':' + now.getMinutes();
	}
	*/

});
