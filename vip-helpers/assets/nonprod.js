(function() {
	function callback() {
		const nonProdBar = document.getElementById('vip-non-prod-bar');
		if (nonProdBar) {
			nonProdBar.addEventListener('click', function() {
				this.classList.toggle('which-env');
			} );

			const debugBar = document.getElementById('a8c-debug-flag');
			if (debugBar) {
				// Account for proper stacking of the debug bar
				nonProdBar.style.bottom = '180px';
			}
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', callback );
	} else {
		callback();
	}
})();
