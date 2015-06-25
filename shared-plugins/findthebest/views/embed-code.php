<div style="width:100%;max-width:<?php echo esc_attr( $vars['width'] ); ?>px;margin:0 auto;">
<iframe src="<?php echo esc_url( $vars['url'] ); ?>" width="<?php echo esc_attr( $vars['width'] ); ?>" height="<?php echo esc_attr( $vars['height'] ); ?>" frameborder="0" scrolling="no" style="position:static;vertical-align:top;margin:0;max-width:100%;<?php echo ( $vars['height'] ? "min-height:" . esc_attr( $vars['height'] ) . "px;" : "" ); ?>"></iframe>
<div style="text-align:center;"><a target="_blank" href="<?php echo esc_url( $vars['link'] ); ?>" style="font:14px/16px arial;color:#3d3d3d;"><?php echo esc_html( $vars['link_text'] ); ?></a></div>
</div>
