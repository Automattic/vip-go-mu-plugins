/**
 * This JS separately included in the containing site.
 * rp_resize is called from static file /rp-metro-smartfile.html
 * to allow Rubicon to resize iframes in which their ads are served
 */

function rp_resize(id, sz) {
	var el, w, endH, startH;
	w = sz.substring(0, sz.indexOf('x'));
	endH = sz.substring(sz.indexOf('x') + 1, sz.length);
	el = document.getElementById(id);

	if (el.getBoundingClientRect().height) {
		startH = el.getBoundingClientRect().height; // for modern browsers
	} else {
		startH = el.offsetHeight; // for oldIE
	}

	document.getElementById(id).width = parseInt(w);
	rubicon_animate(el, 'height', 'px', startH, endH, 500);
}

function rubicon_animate( elem, style, unit, from, to, time ) {
    if( !elem ) return;
    var start = new Date().getTime(),
        timer = setInterval( function() {
            var step = Math.min( 1, ( new Date().getTime() - start ) / time );
            elem.style[style] = ( from + step * (to - from) ) + unit;
            if ( step == 1 ) clearInterval( timer );
        }, 25 );
	elem.style[style] = from + unit;
}