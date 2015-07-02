var dsq_old_onload = window.onload;

function dsq_tab_func(clicked_tab) {
	function _dsq_tab_func(e) {
		var tabs = document.getElementById('dsq-tabs').getElementsByTagName('li');
		var contents = document.getElementById('dsq-wrap').getElementsByTagName('div');

		for(var i = 0; i < tabs.length; i++) {
			tabs[i].className = '';
		}

		for(var i = 0; i < contents.length; i++) {
			if(contents[i].className == 'dsq-content') {
				contents[i].style.display = 'none';
			}
		}

		document.getElementById('dsq-tab-' + clicked_tab).className = 'selected';
		document.getElementById('dsq-' + clicked_tab).style.display = 'block';

	}

	return _dsq_tab_func;
}

window.onload = function(e) {
	// Tabs have an ID prefixed with "dsq-tab-".
	// Content containers have an ID prefixed with "dsq-" and a class name of "dsq-content".
	var tabs = document.getElementById('dsq-tabs').getElementsByTagName('li');

	for(var i = 0; i < tabs.length; i++) {
		tabs[i].onclick = dsq_tab_func(tabs[i].id.substr(tabs[i].id.lastIndexOf('-') + 1));
	}

	if(dsq_old_onload) {
		dsq_old_onload(e);
	}
}
