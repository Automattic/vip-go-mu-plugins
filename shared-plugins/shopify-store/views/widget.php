<div id='<?php echo esc_attr( $widget_id ); ?>-container' class='shopify-widget <?php echo esc_attr( $settings["style"] ); ?> <?php echo esc_attr( $settings["image_size"] ); ?>'>
	<form id='<?php echo esc_attr( $widget_id ); ?>' action='<?php echo esc_url( "https://" . $settings["myshopify_domain"] . "/cart/add"); ?>' method='post' target=''>
		 <input class='destination' type='hidden' name='destination' value='<?php echo esc_attr( $settings["destination"] ); ?>'>
		 <input class='handle' type='hidden' name='handle' value='<?php echo esc_attr( $product_handle ); ?>'>

		<div class='widget-image'>
			<img src='<?php echo esc_url( plugins_url( '../images/demo-product-image.jpg', __FILE__ ) ); ?>'>
				</div>

		<div class='widget-caption'>
			<span class='widget-title'></span>
			<div class='widget-price'>
				<span class='product-price'></span>
			</div>

			<div class='widget-options'>
				<select class='select-price' name='variant-id' onchange='Shopify.allWidgets.<?php echo esc_attr( $widget_id ); ?>.updateVisablePrice(); return false;'>
				</select>
			</div>

			<div class='widget-buttons'><?php
			if ( $settings['destination'] == "cart" ) {
				echo "<input type='hidden' class='selected-variant' name='id' "
					. "value=''/> <input type='submit' class='widget-buy-button' value='"
					. esc_attr( $settings["button_text"] ) . "' target='#'/>";
			} else {
				echo "<input type='hidden' name='return-to' value='/checkout'/>"
					. "<input type='submit' class='widget-buy-button' value='"
					. esc_attr( $settings["button_text"] ) . "' target='#' "
					. "onclick='Shopify.allWidgets." . esc_attr( $widget_id ) . ".buyNow();return false;'/>";
			};
			?>
			</div>

		</div>

	</form>
</div>

<script type='text/javascript' charset='utf-8'>
		jQuery(document).ready(function () {
				Shopify.allWidgets.<?php echo esc_js( $widget_id ); ?> = new Shopify.Widget({
					handle: '<?php echo esc_js( $widget_id ); ?>',
					money_format: '<?php echo esc_js( $settings["money_format"] ); ?>',
					myshopify_domain: '<?php echo esc_js( $settings["myshopify_domain"] ); ?>',
					product_handle: '<?php echo esc_js( $product_handle ); ?>',
					referer: '<?php echo esc_url( site_url() ); ?>',
					size: '<?php echo esc_js( $settings["image_size"] ); ?>',
				});
				Shopify.allWidgets.<?php echo esc_js( $widget_id ); ?>.fetchProduct();
		});
</script>
<style type='text/css'>
	#<?php echo esc_html( $widget_id ); ?>-container.shopify-widget {
		padding: <?php echo esc_html( $settings["border_padding"] ); ?>;
		border: <?php echo esc_html( $settings["border_color"] ); ?> 1px solid;
		background: <?php echo esc_html( $settings["background_color"] ); ?>;
	}
	#<?php echo esc_html( $widget_id ); ?>-container.shopify-widget .widget-buttons input[type='submit'] {
		background: <?php echo esc_html( $settings["button_background"] ); ?>;
		color: <?php echo esc_html( $settings["button_text_color"] ); ?>;
	}
	#<?php echo esc_html( $widget_id ); ?>-container.shopify-widget.centered {
		color: <?php echo esc_html( $settings["text_color"] ); ?>;
	}
	#<?php echo esc_html( $widget_id ); ?>-container.shopify-widget.simple .widget-price {
		color: <?php echo esc_html( $settings["text_color"] ); ?>;
	}
</style>
