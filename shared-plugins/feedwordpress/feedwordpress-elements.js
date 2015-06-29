(function($) {
var fs = {add:'ajaxAdd',del:'ajaxDel',dim:'ajaxDim',process:'process',recolor:'recolor'}, fwpList;

fwpList = {
	settings: {
		url: ajaxurl, type: 'POST',
		response: 'ajax-response',

		what: '',
		alt: 'alternate', altOffset: 0,
		addColor: null, delColor: null, dimAddColor: null, dimDelColor: null,

		confirm: null,
		addBefore: null, addAfter: null,
		delBefore: null, delAfter: null,
		dimBefore: null, dimAfter: null
	},

	nonce: function(e,s) {
		var url = wpAjax.unserialize(e.attr('href'));
		return s.nonce || url._ajax_nonce || $('#' + s.elementbox + ' input[name=_ajax_nonce]').val() || url._wpnonce || $('#' + s.element + ' input[name=_wpnonce]').val() || 0;
	},

	parseClass: function(e,t) {
		var c = [], cl;
		try {
			cl = $(e).attr('class') || '';
			cl = cl.match(new RegExp(t+':[\\S]+'));
			if ( cl ) { c = cl[0].split(':'); }
		} catch(r) {}
		return c;
	},

	pre: function(e,s,a) {
		var bg, r;
		s = $.extend( {}, this.fwpList.settings, {
			element: null,
			nonce: 0,
			target: e.get(0)
		}, s || {} );
		if ( $.isFunction( s.confirm ) ) {
			if ( 'add' != a ) {
				bg = $('#' + s.element).css('backgroundColor');
				$('#' + s.element).css('backgroundColor', '#FF9966');
			}
			r = s.confirm.call(this,e,s,a,bg);
			if ( 'add' != a ) { $('#' + s.element).css('backgroundColor', bg ); }
			if ( !r ) { return false; }
		}
		return s;
	},

	ajaxAdd: function( e, s ) {
		e = $(e);
		s = s || {};
		var list = this, cls = fwpList.parseClass(e,'add'), es, valid, formData;
		s = fwpList.pre.call( list, e, s, 'add' );

		s.element = cls[2] || e.attr( 'id' ) || s.element || null;
		if ( cls[3] ) { s.addColor = '#' + cls[3]; }
		else { s.addColor = s.addColor || '#FFFF33'; }

		if ( !s ) { return false; }

		if ( !e.is("[class^=add:" + list.id + ":]") ) { return !fwpList.add.call( list, e, s ); }

		if ( !s.element ) { return true; }

		s.action = 'add-' + s.what;

		s.nonce = fwpList.nonce(e,s);

		es = $('#' + s.elementbox + ' :input').not('[name=_ajax_nonce], [name=_wpnonce], [name=action]');
		valid = wpAjax.validateForm( '#' + s.element );
		if ( !valid ) { return false; }

		s.data = $.param( $.extend( { _ajax_nonce: s.nonce, action: s.action }, wpAjax.unserialize( cls[4] || '' ) ) );
		formData = $.isFunction(es.fieldSerialize) ? es.fieldSerialize() : es.serialize();
		if ( formData ) { s.data += '&' + formData; }

		if ( $.isFunction(s.addBefore) ) {
			s = s.addBefore( s );
			if ( !s ) { return true; }
		}
		if ( !s.data.match(/_ajax_nonce=[a-f0-9]+/) ) { return true; }

		s.success = function(r) {
			var res = wpAjax.parseAjaxResponse(r, s.response, s.element), o;
			if ( !res || res.errors ) { return false; }

			if ( true === res ) { return true; }

			jQuery.each( res.responses, function() {
				fwpList.add.call( list, this.data, $.extend( {}, s, { // this.firstChild.nodevalue
					pos: this.position || 0,
					id: this.id || 0,
					oldId: this.oldId || null
				} ) );
			} );

			if ( $.isFunction(s.addAfter) ) {
				o = this.complete;
				this.complete = function(x,st) {
					var _s = $.extend( { xml: x, status: st, parsed: res }, s );
					s.addAfter( r, _s );
					if ( $.isFunction(o) ) { o(x,st); }
				};
			}
			list.fwpList.recolor();
			$(list).trigger( 'fwpListAddEnd', [ s, list.fwpList ] );
			fwpList.clear.call(list,'#' + s.element);
		};

		$.ajax( s );
		return false;
	},

	ajaxDel: function( e, s ) {
		e = $(e); s = s || {};
		var list = this, cls = fwpList.parseClass(e,'delete'), element;
		s = fwpList.pre.call( list, e, s, 'delete' );

		s.element = cls[2] || s.element || null;
		if ( cls[3] ) { s.delColor = '#' + cls[3]; }
		else { s.delColor = s.delColor || '#faa'; }

		if ( !s || !s.element ) { return false; }

		s.action = 'delete-' + s.what;

		s.nonce = fwpList.nonce(e,s);

		s.data = $.extend(
			{ action: s.action, id: s.element.split('-').pop(), _ajax_nonce: s.nonce },
			wpAjax.unserialize( cls[4] || '' )
		);

		if ( $.isFunction(s.delBefore) ) {
			s = s.delBefore( s, list );
			if ( !s ) { return true; }
		}
		if ( !s.data._ajax_nonce ) { return true; }

		element = $('#' + s.element);

		if ( 'none' != s.delColor ) {
			element.css( 'backgroundColor', s.delColor ).fadeOut( 350, function(){
				list.fwpList.recolor();
				$(list).trigger( 'fwpListDelEnd', [ s, list.fwpList ] );
			});
		} else {
			list.fwpList.recolor();
			$(list).trigger( 'fwpListDelEnd', [ s, list.fwpList ] );
		}

		s.success = function(r) {
			var res = wpAjax.parseAjaxResponse(r, s.response, s.element), o;
			if ( !res || res.errors ) {
				element.stop().stop().css( 'backgroundColor', '#faa' ).show().queue( function() { list.fwpList.recolor(); $(this).dequeue(); } );
				return false;
			}
			if ( $.isFunction(s.delAfter) ) {
				o = this.complete;
				this.complete = function(x,st) {
					element.queue( function() {
						var _s = $.extend( { xml: x, status: st, parsed: res }, s );
						s.delAfter( r, _s );
						if ( $.isFunction(o) ) { o(x,st); }
					} ).dequeue();
				};
			}
		};
		$.ajax( s );
		return false;
	},

	ajaxDim: function( e, s ) {
		if ( $(e).parent().css('display') == 'none' ) // Prevent hidden links from being clicked by hotkeys
			return false;
		e = $(e); s = s || {};
		var list = this, cls = fwpList.parseClass(e,'dim'), element, isClass, color, dimColor;
		s = fwpList.pre.call( list, e, s, 'dim' );

		s.element = cls[2] || s.element || null;
		s.dimClass =  cls[3] || s.dimClass || null;
		if ( cls[4] ) { s.dimAddColor = '#' + cls[4]; }
		else { s.dimAddColor = s.dimAddColor || '#FFFF33'; }
		if ( cls[5] ) { s.dimDelColor = '#' + cls[5]; }
		else { s.dimDelColor = s.dimDelColor || '#FF3333'; }

		if ( !s || !s.element || !s.dimClass ) { return true; }

		s.action = 'dim-' + s.what;

		s.nonce = fwpList.nonce(e,s);

		s.data = $.extend(
			{ action: s.action, id: s.element.split('-').pop(), dimClass: s.dimClass, _ajax_nonce : s.nonce },
			wpAjax.unserialize( cls[6] || '' )
		);

		if ( $.isFunction(s.dimBefore) ) {
			s = s.dimBefore( s );
			if ( !s ) { return true; }
		}

		element = $('#' + s.element);
		isClass = element.toggleClass(s.dimClass).is('.' + s.dimClass);
		color = fwpList.getColor( element );
		element.toggleClass( s.dimClass )
		dimColor = isClass ? s.dimAddColor : s.dimDelColor;
		if ( 'none' != dimColor ) {
			element
				.animate( { backgroundColor: dimColor }, 'fast' )
				.queue( function() { element.toggleClass(s.dimClass); $(this).dequeue(); } )
				.animate( { backgroundColor: color }, { complete: function() { $(this).css( 'backgroundColor', '' ); $(list).trigger( 'fwpListDimEnd', [ s, list.fwpList ] ); } } );
		} else {
			$(list).trigger( 'fwpListDimEnd', [ s, list.fwpList ] );
		}

		if ( !s.data._ajax_nonce ) { return true; }

		s.success = function(r) {
			var res = wpAjax.parseAjaxResponse(r, s.response, s.element), o;
			if ( !res || res.errors ) {
				element.stop().stop().css( 'backgroundColor', '#FF3333' )[isClass?'removeClass':'addClass'](s.dimClass).show().queue( function() { list.fwpList.recolor(); $(this).dequeue(); } );
				return false;
			}
			if ( $.isFunction(s.dimAfter) ) {
				o = this.complete;
				this.complete = function(x,st) {
					element.queue( function() {
						var _s = $.extend( { xml: x, status: st, parsed: res }, s );
						s.dimAfter( r, _s );
						if ( $.isFunction(o) ) { o(x,st); }
					} ).dequeue();
				};
			}
		};

		$.ajax( s );
		return false;
	},

	// From jquery.color.js: jQuery Color Animation by John Resig
	getColor: function( el ) {
		if ( el.constructor == Object )
			el = el.get(0);
		var elem = el, color, rgbaTrans = new RegExp( "rgba\\(\\s*0,\\s*0,\\s*0,\\s*0\\s*\\)", "i" );
		do {
			color = jQuery.curCSS(elem, 'backgroundColor');
			if ( color != '' && color != 'transparent' && !color.match(rgbaTrans) || jQuery.nodeName(elem, "body") )
				break;
		} while ( elem = elem.parentNode );
		return color || '#ffffff';
	},

	add: function( e, s ) {
		e = $(e);

		var list = $(this),
			old = false,
			_s = { pos: 0, id: 0, oldId: null },
			ba, ref, color;

		if ( 'string' == typeof s ) {
			s = { what: s };
		}

		s = $.extend(_s, this.fwpList.settings, s);
		if ( !e.size() || !s.what ) { return false; }
		if ( s.oldId ) { old = $('#' + s.what + '-' + s.oldId); }
		if ( s.id && ( s.id != s.oldId || !old || !old.size() ) ) { $('#' + s.what + '-' + s.id).remove(); }

		if ( old && old.size() ) {
			old.before(e);
			old.remove();
		} else if ( isNaN(s.pos) ) {
			ba = 'after';
			if ( '-' == s.pos.substr(0,1) ) {
				s.pos = s.pos.substr(1);
				ba = 'before';
			}
			ref = list.find( '#' + s.pos );
			if ( 1 === ref.size() ) { ref[ba](e); }
			else { list.append(e); }
		} else if ( s.pos < 0 ) {
			list.prepend(e);
		} else {
			list.append(e);
		}

		if ( s.alt ) {
			if ( ( list.children(':visible').index( e[0] ) + s.altOffset ) % 2 ) { e.removeClass( s.alt ); }
			else { e.addClass( s.alt ); }
		}

		if ( 'none' != s.addColor ) {
			color = fwpList.getColor( e );
			e.css( 'backgroundColor', s.addColor ).animate( { backgroundColor: color }, { complete: function() { $(this).css( 'backgroundColor', '' ); } } );
		}
		list.each( function() { this.fwpList.process( e ); } );
		return e;
	},

	clear: function(e) {
		var list = this, t, tag;
		e = $(e);
		if ( list.fwpList && e.parents( '#' + list.id ).size() ) { return; }
		e.find(':input').each( function() {
			if ( $(this).parents('.form-no-clear').size() )
				return;
			t = this.type.toLowerCase();
			tag = this.tagName.toLowerCase();
			if ( 'text' == t || 'password' == t || 'textarea' == tag ) { this.value = ''; }
			else if ( 'checkbox' == t || 'radio' == t ) { this.checked = false; }
			else if ( 'select' == tag ) { this.selectedIndex = null; }
		});
	},

	process: function(el) {
		var list = this;

		$('[class^="add:' + list.id + ':"]', el || null)
			.filter('form').submit( function() { return list.fwpList.add(this); } ).end()
			.not('form').click( function() { return list.fwpList.add(this); } );
		$('[class^="delete:' + list.id + ':"]', el || null).click( function() { return list.fwpList.del(this); } );
		$('[class^="dim:' + list.id + ':"]', el || null).click( function() { return list.fwpList.dim(this); } );
	},

	recolor: function() {
		var list = this, items, eo;
		if ( !list.fwpList.settings.alt ) { return; }
		items = $('.list-item:visible', list);
		if ( !items.size() ) { items = $(list).children(':visible'); }
		eo = [':even',':odd'];
		if ( list.fwpList.settings.altOffset % 2 ) { eo.reverse(); }
		items.filter(eo[0]).addClass(list.fwpList.settings.alt).end().filter(eo[1]).removeClass(list.fwpList.settings.alt);
	},

	init: function() {
		var lists = this;

		lists.fwpList.process = function(a) {
			lists.each( function() {
				this.fwpList.process(a);
			} );
		};
		lists.fwpList.recolor = function() {
			lists.each( function() {
				this.fwpList.recolor();
			} );
		};
	}
};

$.fn.fwpList = function( settings ) {
	this.each( function() {
		var _this = this;
		this.fwpList = { settings: $.extend( {}, fwpList.settings, { what: fwpList.parseClass(this,'list')[1] || '' }, settings ) };
		$.each( fs, function(i,f) { _this.fwpList[i] = function( e, s ) { return fwpList[f].call( _this, e, s ); }; } );
	} );
	fwpList.init.call(this);
	this.fwpList.process();
	return this;
};

})(jQuery);

jQuery(document).ready( function($) {
	// Category boxes
	$('.feedwordpress-category-div').each( function () {
		var this_id = $(this).attr('id');
		var catAddBefore, catAddAfter;
		var taxonomyParts, taxonomy, settingName;
		
		taxonomyParts = this_id.split('-');
		taxonomyParts.shift();	taxonomyParts.shift();
		taxonomy = taxonomyParts.join('-');

		settingName = taxonomy + '_tab';
		if ( taxonomy == 'category' )
			settingName = 'cats';
			
		// No need to worry about tab stuff for our purposes
			
		// Ajax Cat
		var containerId = $(this).attr('id');
		var checkboxId = $(this).find('.categorychecklist').attr('id');
		var newCatId = $(this).find('.newcategory').attr('id');
		var responseId = $(this).find('.'+taxonomy+'-ajax-response').attr('id');
		var taxAdderId = $(this).find('.'+taxonomy+'-adder').attr('id');

		$(this).find('.newcategory').one( 'focus', function () { $(this).val('').removeClass('form-input-tip'); } );
		$(this).find('.add-categorychecklist-category-add').click( function() {
			$(this).parent().children('.newcategory').focus();
		} );
		
		catAddBefore = function (s) {
			if ( !$('#'+newCatId).val() )
				return false;
			s.data += '&' + $( ':checked', '#'+checkboxId ).serialize();
			return s;
		}
		catAddAfter = function (r, s) {
			// Clear out input box
			$('.newcategory', '#'+this_id).val('');
			
			// Clear out parent dropbox
			var sup, drop = $('.newcategory-parent', '#'+this_id);
			
			if ( 'undefined' != s.parsed.responses[0] && (sup = s.parsed.responses[0].supplemental.newcat_parent) ) {
				drop.before(sup);
				drop.remove();
			}
		};

		$('#' + checkboxId).fwpList({
			alt: '',
			elementbox: taxAdderId,
			response: responseId,
			addBefore: catAddBefore,
			addAfter: catAddAfter
		});
		
		$(this).find('.category-add-toggle').click( function () {
			$('#' + taxAdderId).toggleClass('wp-hidden-children');
			$('#' + newCatId).focus();
			return false;
		} ); /* $(this).find('.category-add-toggle').click() */

	} ); /* $('.feedwordpress-category-div').each() */
} ); /* jQuery(document).ready() */

jQuery(document).ready(function($){
	$('.fwpfs').toggle(
		function(){$('.fwpfs').removeClass('slideUp').addClass('slideDown'); setTimeout(function(){if ( $('.fwpfs').hasClass('slideDown') ) { $('.fwpfs').addClass('slide-down'); }}, 10) },
		function(){$('.fwpfs').removeClass('slideDown').addClass('slideUp'); setTimeout(function(){if ( $('.fwpfs').hasClass('slideUp') ) { $('.fwpfs').removeClass('slide-down'); }}, 10) }
	);
	$('.fwpfs').bind(
		'change',
		function () { this.form.submit(); }
	);
	$('#post-search .button').css( 'display', 'none' );
});

