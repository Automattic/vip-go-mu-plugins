(function( $ ){
	Widget = function( options ) {
		this.widget_id = options.handle;
		this.money_format = options.money_format;
		this.myshopify_domain = options.myshopify_domain;
		this.product_handle = options.product_handle;
		this.referer = options.referer;
		this.size = options.size;
		this.widget = $( "#" + this.widget_id );
		this.widget_container = $( "#" + this.widget_id + "-container" );
	};

	Widget.prototype.fetchProduct = function() {
		var that = this;
		$.ajax({
			url: 'https://' + this.myshopify_domain + '/products/' + this.product_handle + '.json',
			dataType: 'jsonp',
			success: function ( data, jqhxr, status ) {
				that.product = data.product;
				that.updateWidget();
			}
		});
	};

	Widget.prototype.updateWidget = function() {
		this.widget.find( ".widget-title" ).text( this.product.title );
		this.widget.find( ".this.product-price" ).text( this.product.variants[0].price );
		if ( this.product.variants.length > 1 ) {
			this.widget.find( ".select-price" ).addClass( "show" );
			this.widget_container.addClass( "with-options" );
		}
		this.widget.find( ".selected-variant" ).val( this.product.variants[0].id ).empty();
		this.widget.find( ".select-price" ).empty();
		for ( var i in this.product.variants ) {
			var variant = this.product.variants[i];
			var option = $( '<option value="' + variant.id + '" data-price="' + Shopify.formatMoney( variant.price, this.money_format ) + '">' + variant.title + '</option>' );
			this.widget.find( ".select-price" ).append( option );
		}
		this.updateImage();
		this.updateVisablePrice();
	};

	Widget.prototype.updateImage = function() {
		if ( this.product.images.length > 0 ) {
			var src = this.product.images[0].src;
			var ext = "." + this.getLocation( src ).pathname.split( "." ).pop();
			var img = src.split( ext )[0] + "_" + this.size + ext;
			img = img.replace( "http:", "https:" );
			var img_elm = this.widget.find( ".widget-image img" );
			img_elm.on('load', $.proxy( this.updateWidgetSize, this ));
			img_elm.attr( 'src', img ).attr( 'alt', this.product.title );
			window.setTimeout( $.proxy( this.updateWidgetSize, this ), 2000); //just in case on load doesn't work
		}
	};

	Widget.prototype.updateWidgetSize = function() {
		var img_elm = this.widget.find( ".widget-image img" );
		if( this.size !== "small" ){
			this.widget.css( "width", img_elm.width() );
		} else {
			this.widget.css( "width", 120 );
		}
	};

	Widget.prototype.updateVisablePrice = function() {
		var el = this.widget.find( ".select-price" )[0];
		var selected = el.children[el.selectedIndex];
		var price_el = this.widget.find( ".product-price" )[0];
		var price = selected.getAttribute( 'data-price' );
		price_el.innerHTML = price;
		this.widget.find( ".selected-variant" ).val( selected.value );
	};

	Widget.prototype.getLocation = function( href ) {
		var l = document.createElement( "a" );
		l.href = href;
		return l;
	};


	Widget.prototype.buyNow = function() {
		var variant_id = $( '#' + this.widget_id + ' [name=variant-id]:first' ).val();
		var qty = 1;
		if ( $( '#' + this.widget_id + ' .destination' ).val() === 'product' ) {
			// redirect to the product page
			window.parent.location.href = "https://" + this.myshopify_domain + "/products/" + this.product_handle + "?referer=" + this.referer + "&wp_refer=true";
		} else {
			// redirect to checkout endout with parameters => /checkout?variant_id:qty
			window.parent.location.href = "https://" + this.myshopify_domain + "/cart/" + variant_id + ":" + qty + "?referer=" + this.referer + "&wp_refer=true";
		}
		return false;
	};

	Shopify.Widget = Widget;
	Shopify.allWidgets = {};
} )( jQuery );
