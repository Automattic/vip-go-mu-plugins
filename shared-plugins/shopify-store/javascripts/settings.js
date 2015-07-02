( function( $ ){
	Shopify.connectStore = function() {
		var address = $( "#login-shopify-url" ).val().replace( "https://", "" ).replace( "http://", "" ).split( "." )[0];
		Shopify.getShopifySettings( address + ".myshopify.com" );
	};

	Shopify.submitSettingsForm = function( e ) {
		e.preventDefault();
		Shopify.getShopifySettings( $( "#shopify_myshopify_domain" ).val() );
		return false;
	};

	Shopify.getShopifySettings = function( address ){
		if( typeof Shopify.timeoutId === "undefined" ) {
			Shopify.timeoutId = window.setTimeout(Shopify.jsonpFail, 700);
		}
		$.ajax({
			url: "//" + address + "/meta.json",
			dataType: "jsonp",
			success: Shopify.jsonpSuccess
		});
	};

	Shopify.jsonpFail = function(){
		$( "#shopify-error" ).empty().append( "<b>Invalid store address</b>. Please try again or contact <a href='http://docs.shopify.com/support'>Shopify Support</a>." ).fadeIn(300).fadeOut(200).fadeIn(300);
	};

	Shopify.jsonpSuccess = function( json ) {
		window.clearTimeout(Shopify.timeoutId);
		delete Shopify.timeoutId;

		if( $( "#shopify_setup" ).val() === "false" ) {
			Shopify.setCookie( "redirectToShopifyApp" );
		}

		$( "#shopify_myshopify_domain" ).val( json.myshopify_domain );
		$( "#shopify_primary_shopify_domain" ).val( json.domain );
		$( "#shopify_money_format" ).val( json.money_format );
		$( "#shopify_setup" ).val( "true" );
		$( "#shopify-settings-form" ).submit();
	};

	Shopify.setCookie = function( name ) {
		var d = new Date();
		d.setTime( d.getTime() + ( 5*365*24*60*60*1000 ) ); //5 year cookie
		var expires = "expires=" + d.toGMTString();
		document.cookie = "" + name + "=true; " + expires;
	};

	Shopify.checkCookie = function( name ) {
		return document.cookie.indexOf( name ) !== -1;
	};

	Shopify.deleteCookie = function( name ) {
		document.cookie = "" + name + "=true;expires=Thu, 01 Jan 1970 00:00:01 GMT;";
	};

	Shopify.hideTip = function(){
		$( "#shopify-getting-started" ).fadeOut();
		$( "#shopify-help-link" ).fadeIn();
		Shopify.setCookie( "shopifyHideTip" );
	};

	Shopify.updateWidgetPreview = function( element ){
		var widget_id = $( "#shopify-widget-preview form" ).attr( "id" );
		var widget = Shopify.allWidgets[widget_id];
		switch( element.id ) {
			case "shopify_text_color":
				if ( widget.widget_container.hasClass( "centered" )) {
					widget.widget_container.css( "color", element.value );
				} else {
					widget.widget_container.find( ".widget-price" ).css( "color", element.value );
				}
				break;
			case "shopify_button_text_color":
				widget.widget_container.find( ".widget-buttons input[type='submit']" ).css( "color", element.value );
				break;
			case "shopify_button_background":
				widget.widget_container.find( ".widget-buttons input[type='submit']" ).css( "background", element.value );
				break;
			case "shopify_button_text":
				widget.widget_container.find( ".widget-buttons input[type='submit']" ).val( element.value );
				break;
			case "shopify_background_color":
				widget.widget_container.css( "background", element.value );
				break;
			case "shopify_border_color":
				widget.widget_container.css( "border", element.value + " 1px solid" );
				break;
			case "shopify_border_padding":
				widget.widget_container.css( "padding", element.value );
				break;
			case "shopify_style":
				widget.widget_container.removeClass( "simple centered" ).addClass( element.value );
				break;
			case "shopify_image_size":
				widget.widget_container.removeClass( "small medium large grande" ).addClass( element.value );
				widget.size = element.value;
				widget.updateImage();
				break;
			case "shopify_money_format":
				widget.money_format = element.value;
				widget.updateWidget();
				break;
			case "shopify_destination":
				var button = "";
				var button_text = $( "#shopify_button_text" ).attr( "value" );
				if( element.value === "cart" ){
					button = "<input type='hidden' class='selected-variant' name='id' value=''/> <input type='submit' class='widget-buy-button' value='" + button_text + "' target='#'/>";
				} else {
					button = "<input type='hidden' name='return-to' value='/checkout'/><input type='submit' class='widget-buy-button' value='" + button_text + "' target='#' onclick='Shopify.allWidgets." + widget_id + ".buyNow();return false;'/>";
				}
				widget.widget_container.find( ".destination" ).attr( "value", element.value );
				widget.widget_container.find( ".widget-buttons" ).empty().append( button );
				widget.updateVisablePrice(); //set id posted by form properly
				break;
			default:
				break;
		}
	};

	Shopify.toggleEditMyshopify = function() {
		$( "#shopify_myshopify_domain" ).toggle();
		$( "#edit-myshopify-domain" ).toggle();
		return false;
	};

	$( function(){
		if( $( "#shopify_setup" ).val() === "true" ){

			if( Shopify.checkCookie( "redirectToShopifyApp" ) ){
				Shopify.deleteCookie( "redirectToShopifyApp" );
				window.location.href =  "https://wordpress-shortcode-generator.shopifyapps.com/login?shop=" + $( "#shopify_myshopify_domain" ).val() + "&wordpress_admin_url=" + $( "#shopify-store-signin" ).data( "wordpressdomain" );
			}

			if( !Shopify.checkCookie( "shopifyHideTip" ) ){
				$( "#shopify-getting-started" ).show();
				$( "#shopify-help-link" ).hide();
			}

			$( "#shopify-settings-form" ).keyup( function( e ) {
				var code = e.keyCode || e.which;
				if ( code == 13 ) {
					return Shopify.submitSettingsForm( e );
				}
			});

			$( "#save-shopify-settings" ).click(function( e ){
				return Shopify.submitSettingsForm( e );
			});

			$( "#shopify-settings-form" ).bind( "change keyup", function( e ){
				Shopify.updateWidgetPreview( e.target );
			});

			$( ".color-picker" ).iris({
				hide: true,
				size: 140,
				change: function( event, ui ) {
					Shopify.updateWidgetPreview( event.target );
				}
			});
			$( ".color-picker" ).click( function( event ){
				$( ".color-picker" ).iris( "hide" );
				$( event.target ).iris( "show" );
			});

			$( "#shopify-settings-form .form-table" ).last().hide();

			var domain = $( "#shopify_myshopify_domain" );
			domain.hide();
			domain.after(
					"<div id='edit-myshopify-domain'>" + domain.val() + "</div>"
			);

		} else {
			$( "#login-shopify-url" ).keyup( function( e ) {
				var code = e.keyCode || e.which;
				if ( code == 13 ) {
					e.preventDefault();
					Shopify.connectStore();
					return false;
				}});
			$( "#shopify-connect-banner" ).hide();
		}
	});
})( jQuery );

