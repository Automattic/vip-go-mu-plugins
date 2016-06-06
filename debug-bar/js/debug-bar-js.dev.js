(function() {
	var count, list, rawCount = 0;

	window.onerror = function( errorMsg, url, lineNumber ) {

		var errorLine, place, button, tab;

		rawCount++;

		if ( !count )
			count = document.getElementById( 'debug-bar-js-error-count' );
		if ( !list )
			list = document.getElementById( 'debug-bar-js-errors' );

		if ( !count || !list )
			return; // threw way too early... @todo cache these?

		if ( 1 == rawCount ) {
			button = document.getElementById( 'wp-admin-bar-debug-bar' );
			if ( !button )
				return; // how did this happen?
			if ( button.className.indexOf( 'debug-bar-php-warning-summary' ) === -1 )
				button.className = button.className + ' debug-bar-php-warning-summary';

			tab = document.getElementById('debug-menu-link-Debug_Bar_JS');
			if ( tab )
				tab.style.display = 'block';
		}

		count.textContent = rawCount;
		errorLine = document.createElement( 'li' );
		errorLine.className = 'debug-bar-js-error';
		errorLine.textContent = errorMsg;
		place = document.createElement( 'span' );
		place.textContent = url + ' line ' + lineNumber;
		errorLine.appendChild( place );
		list.appendChild( errorLine );

		return false;
	};

	// suppress error handling
	window.addEventListener( 'error', function( e ) {
		e.preventDefault();
	}, true );
})();