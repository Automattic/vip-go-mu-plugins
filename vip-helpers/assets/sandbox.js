(function() {
	function callback() {
		const bar = document.querySelector('#wpcom-sandboxed-bar');
		if (bar) {
			bar.addEventListener('click', function() {
				this.classList.toggle('sbx-debug');
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', callback);
	} else {
		callback();
	}
})();
