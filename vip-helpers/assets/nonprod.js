(function() {
	function callback() {
		const nonProdBar = document.getElementById('vip-non-prod-bar');
		if (nonProdBar) {
			nonProdBar.addEventListener('click', function() {
				this.classList.toggle('which-env');
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', callback );
	} else {
		callback();
	}
})();
