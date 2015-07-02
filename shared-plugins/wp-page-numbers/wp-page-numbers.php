<?php
/*
Plugin Name: WP Page Numbers
Plugin URI: http://www.jenst.se/2008/03/29/wp-page-numbers
Description: Show pages numbers instead of "Next page" and "Previous Page".
Version: 0.2
Author: Jens T&ouml;rnell
Author URI: http://www.jenst.se
*/

function wp_page_numbers_stylesheet()
{
	$settings = get_option('wp_page_numbers_array');

	$head_stylesheet = isset( $settings["head_stylesheetsheet"] ) ? $settings['head_stylesheetsheet'] : '';
	$head_stylesheet_folder_name = isset( $settings["head_stylesheetsheet_folder_name"] ) ? $settings["head_stylesheetsheet_folder_name"] : '';
	$style_theme = isset( $settings["style_theme"] ) ? $settings["style_theme"] : '';

	if($head_stylesheet == "on" || $head_stylesheet == "" && (is_archive() || is_search() || is_home() ||is_page()))
	{
		echo '<link rel="stylesheet" href="http://s.wordpress.com/wp-content/themes/vip/plugins/wp-page-numbers/';
		if($head_stylesheet_folder_name == "")
		{
			if($style_theme == "default")
				echo 'default';
			elseif($style_theme == "classic")
				echo 'classic';
			elseif($style_theme == "tiny")
				echo 'tiny';
			elseif($style_theme == "panther")
				echo 'panther';
			elseif($style_theme == "stylish")
				echo 'stylish';
			else
				echo 'default';
		}
		else
			echo $head_stylesheet_folder_name;
		echo '/wp-page-numbers.css" type="text/css" media="screen" />';
	}
}
add_action('wp_head', 'wp_page_numbers_stylesheet');

function wp_page_numbers_check_num($num)
{
  return ($num%2) ? true : false;
}

function wp_page_numbers_page_of_page($max_page, $paged, $page_of_page_text, $page_of_of)
{
	$pagingString = "";
	if ( $max_page > 1)
	{
		$pagingString .= '<li class="page_info">';
		if($page_of_page_text == "")
			$pagingString .= 'Page ';
		else
			$pagingString .= $page_of_page_text . ' ';
		
		if ( $paged != "" )
			$pagingString .= $paged;
		else
			$pagingString .= 1;
		
		if($page_of_of == "")
			$pagingString .= ' of ';
		else
			$pagingString .= ' ' . $page_of_of . ' ';
		$pagingString .= floor($max_page).'</li>';
	}
	return $pagingString;
}

function wp_page_numbers_prevpage($paged, $max_page, $prevpage)
{
	$pagingString = '';
	if( $max_page > 1 && $paged > 1 )
		$pagingString = '<li><a href="'. esc_url( get_pagenum_link($paged-1) ). '">'.$prevpage.'</a></li>';
	return $pagingString;
}

function wp_page_numbers_left_side($max_page, $limit_pages, $paged, $pagingString)
{
	$pagingString = "";
	$page_check_max = false;
	$page_check_min = false;
	if($max_page > 1)
	{
		for($i=1; $i<($max_page+1); $i++)
		{
			if( $i <= $limit_pages )
			{
				if ($paged == $i || ($paged == "" && $i == 1))
					$pagingString .= '<li class="active_page"><a href="'.esc_url( get_pagenum_link($i) ). '">'.$i.'</a></li>'."\n";
				else
					$pagingString .= '<li><a href="'.esc_url( get_pagenum_link($i) ). '">'.$i.'</a></li>'."\n";
				if ($i == 1)
					$page_check_min = true;
				if ($max_page == $i)
					$page_check_max = true;
			}
		}
		return array($pagingString, $page_check_max, $page_check_min);
	}
}

function wp_page_numbers_middle_side($max_page, $paged, $limit_pages_left, $limit_pages_right)
{
	$pagingString = "";
	$page_check_max = false;
	$page_check_min = false;
	for($i=1; $i<($max_page+1); $i++)
	{
		if($paged-$i <= $limit_pages_left && $paged+$limit_pages_right >= $i)
		{
			if ($paged == $i)
				$pagingString .= '<li class="active_page"><a href="'.esc_url( get_pagenum_link($i) ). '">'.$i.'</a></li>'."\n";
			else
				$pagingString .= '<li><a href="'.esc_url( get_pagenum_link($i) ). '">'.$i.'</a></li>'."\n";
				
			if ($i == 1)
				$page_check_min = true;
			if ($max_page == $i)
				$page_check_max = true;
		}
	}
	return array($pagingString, $page_check_max, $page_check_min);
}

function wp_page_numbers_right_side($max_page, $limit_pages, $paged, $pagingString)
{
	$pagingString = "";
	$page_check_max = false;
	$page_check_min = false;
	for($i=1; $i<($max_page+1); $i++)
	{
		if( ($max_page + 1 - $i) <= $limit_pages )
		{
			if ($paged == $i)
				$pagingString .= '<li class="active_page"><a href="'.esc_url( get_pagenum_link($i) ). '">'.$i.'</a></li>'."\n";
			else
				$pagingString .= '<li><a href="'.esc_url( get_pagenum_link($i) ). '">'.$i.'</a></li>'."\n";
				
			if ($i == 1)
			$page_check_min = true;
		}
		if ($max_page == $i)
			$page_check_max = true;
		
	}
	return array($pagingString, $page_check_max, $page_check_min);
}

function wp_page_numbers_nextpage($paged, $max_page, $nextpage)
{
	if( $paged != "" && $paged < $max_page)
		$pagingString = '<li><a href="'.esc_url( get_pagenum_link($paged+1) ). '">'.$nextpage.'</a></li>'."\n";
	return $pagingString;
}

function wp_page_numbers()
{
	global $wp_query;
	global $max_page;
	global $paged;
	if ( !$max_page ) { $max_page = $wp_query->max_num_pages; }
	if ( !$paged ) { $paged = 1; }
	
	$settings = get_option('wp_page_numbers_array');
	$page_of_page = $settings["page_of_page"];
	$page_of_page_text = $settings["page_of_page_text"];
	$page_of_of = $settings["page_of_of"];
	
	$next_prev_text = $settings["next_prev_text"];
	$show_start_end_numbers = $settings["show_start_end_numbers"];
	$show_page_numbers = $settings["show_page_numbers"];
	
	$limit_pages = $settings["limit_pages"];
	$nextpage = $settings["nextpage"];
	$prevpage = $settings["prevpage"];
	$startspace = $settings["startspace"];
	$endspace = $settings["endspace"];
	$pagingMiddleString = '';
	
	if( $nextpage == "" ) { $nextpage = "&gt;"; }
	if( $prevpage == "" ) { $prevpage = "&lt;"; }
	if( $startspace == "" ) { $startspace = "..."; }
	if( $endspace == "" ) { $endspace = "..."; }
	
	if($limit_pages == "") { $limit_pages = "10"; }
	elseif ( $limit_pages == "0" ) { $limit_pages = $max_page; }
	
	if(wp_page_numbers_check_num($limit_pages) == true)
	{
		$limit_pages_left = ($limit_pages-1)/2;
		$limit_pages_right = ($limit_pages-1)/2;
	}
	else
	{
		$limit_pages_left = $limit_pages/2;
		$limit_pages_right = ($limit_pages/2)-1;
	}
	
	if( $max_page <= $limit_pages ) { $limit_pages = $max_page; }
	
	$pagingString = "<div id='wp_page_numbers'>\n";
	$pagingString .= '<ul>';
	
	if($page_of_page != "no")
		$pagingString .= wp_page_numbers_page_of_page($max_page, $paged, $page_of_page_text, $page_of_of);
	
	if( ($paged) <= $limit_pages_left )
	{
		list ($value1, $value2, $page_check_min) = wp_page_numbers_left_side($max_page, $limit_pages, $paged, $pagingString);
		$pagingMiddleString .= $value1;
	}
	elseif( ($max_page+1 - $paged) <= $limit_pages_right )
	{
		list ($value1, $value2, $page_check_min) = wp_page_numbers_right_side($max_page, $limit_pages, $paged, $pagingString);
		$pagingMiddleString .= $value1;
	}
	else
	{
		list ($value1, $value2, $page_check_min) = wp_page_numbers_middle_side($max_page, $paged, $limit_pages_left, $limit_pages_right);
		$pagingMiddleString .= $value1;
	}
	if($next_prev_text != "no")
		$pagingString .= wp_page_numbers_prevpage($paged, $max_page, $prevpage);

		if ($page_check_min == false && $show_start_end_numbers != "no")
		{
			$pagingString .= "<li class=\"first_last_page\">";
			$pagingString .= "<a href=\"" . esc_url(get_pagenum_link(1) ) . "\">1</a>";
			$pagingString .= "</li>\n<li  class=\"space\">".$startspace."</li>\n";
		}
	
	if($show_page_numbers != "no")
		$pagingString .= $pagingMiddleString;
	
		if ($value2 == false && $show_start_end_numbers != "no")
		{
			$pagingString .= "<li class=\"space\">".$endspace."</li>\n";
			$pagingString .= "<li class=\"first_last_page\">";
			$pagingString .= "<a href=\"" . esc_url( get_pagenum_link($max_page) ) . "\">" . $max_page . "</a>";
			$pagingString .= "</li>\n";
		}
	
	if($next_prev_text != "no")
		$pagingString .= wp_page_numbers_nextpage($paged, $max_page, $nextpage);
	
	$pagingString .= "</ul>\n";
	
	$pagingString .= "<div style='float: none; clear: both;'></div>\n";
	$pagingString .= "</div>\n";
	
	if($max_page != 1)
		echo $pagingString;
}

function wp_page_numbers_settings()
{
    if(isset($_POST['submitted']))
	{
		if($_POST["head_stylesheetsheet"] == "")
			$_POST["head_stylesheetsheet"] = "no";
		if($_POST["page_of_page"] == "")
			$_POST["page_of_page"] = "no";
		if($_POST["next_prev_text"] == "")
			$_POST["next_prev_text"] = "no";
		if($_POST["show_start_end_numbers"] == "")
			$_POST["show_start_end_numbers"] = "no";
		if($_POST["show_page_numbers"] == "")
			$_POST["show_page_numbers"] = "no";
		if($_POST["style_theme"] == "")
			$_POST["style_theme"] = "default";
	
		$settings = array (
			"head_stylesheetsheet"				=> $_POST["head_stylesheetsheet"],
			"head_stylesheetsheet_folder_name"	=> $_POST["head_stylesheetsheet_folder_name"],
			"page_of_page"						=> $_POST["page_of_page"],
			"page_of_page_text"					=> $_POST["page_of_page_text"],
			"page_of_of"						=> $_POST["page_of_of"],
			"next_prev_text"					=> $_POST["next_prev_text"],
			"show_start_end_numbers"			=> $_POST["show_start_end_numbers"],
			"show_page_numbers"					=> $_POST["show_page_numbers"],
			"limit_pages"						=> $_POST["limit_pages"],
			"nextpage"							=> $_POST["nextpage"],
			"prevpage"							=> $_POST["prevpage"],
			"startspace"						=> $_POST["startspace"],
			"endspace"							=> $_POST["endspace"],
			"style_theme"						=> $_POST["style_theme"],
		);
		update_option('wp_page_numbers_array', $settings);
		
		echo "<div id=\"message\" class=\"updated fade\"><p><strong>WP Page Numbers plugin options updated.</strong></p></div>";
    }

	$settings = get_option('wp_page_numbers_array');
	
	$style_theme = $settings["style_theme"];
	
	$head_stylesheet = $settings["head_stylesheetsheet"];
	$head_stylesheet_folder_name = $settings["head_stylesheetsheet_folder_name"];
	$page_of_page = $settings["page_of_page"];
	$page_of_page_text = $settings["page_of_page_text"];
	$page_of_of = $settings["page_of_of"];
	
	$next_prev_text = $settings["next_prev_text"];
	$show_start_end_numbers = $settings["show_start_end_numbers"];
	$show_page_numbers = $settings["show_page_numbers"];
	
	$limit_pages = $settings["limit_pages"];
	
	$nextpage = $settings["nextpage"];
	$prevpage = $settings["prevpage"];
	$startspace = $settings["startspace"];
	$endspace = $settings["endspace"];

    ?>
<form method="post" name="options" target="_self">

<div class="wrap">
<h2>Page Number Themes</h2>
<table style="width: 100%;" border="0">
	<tr>
		<td><strong>Use themes?</strong></td>
		<td>
			<input type="checkbox" name="head_stylesheetsheet" <?php
			if($head_stylesheet == "on" || $head_stylesheet == "")
			{
				echo 'checked="checked"';
			}
			?>/> Include theme stylesheet for page numbers
		</td>
	</tr>
	<tr>
		<td style="width: 400px;"><strong>Modern</strong></td>
		<td style="padding-top: 5px; padding-bottom: 5px;">
			<input type="radio" name="style_theme" value="default" <?php
			if( ( $style_theme == "default" || $style_theme == "" ) && $head_stylesheet_folder_name == "" )
			{
				echo 'checked="checked"';
			}
			?>/>
			<img src="http://s.wordpress.com/wp-content/themes/vip/plugins/wp-page-numbers/default/preview.gif" alt="" />
		</td>
	</tr>
	<tr>
		<td><strong>Classic</strong></td>
		<td style="padding-top: 5px; padding-bottom: 5px;">
			<input type="radio" name="style_theme" value="classic" <?php
			if($style_theme == "classic" && $head_stylesheet_folder_name == "")
			{
				echo 'checked="checked"';
			}
			?>/>
			<img src="http://s.wordpress.com/wp-content/themes/vip/plugins/wp-page-numbers/classic/preview.gif" alt="" />
		</td>
	</tr>
	<tr>
		<td><strong>Tiny</strong></td>
		<td style="padding-top: 5px; padding-bottom: 5px;">
			<input type="radio" name="style_theme" value="tiny" <?php
			if($style_theme == "tiny" && $head_stylesheet_folder_name == "")
			{
				echo 'checked="checked"';
			}
			?>/>
			<img src="http://s.wordpress.com/wp-content/themes/vip/plugins/wp-page-numbers/tiny/preview.gif" alt="" />
		</td>
	</tr>
	<tr>
		<td><strong>Panther</strong></td>
		<td style="padding-top: 5px; padding-bottom: 5px;">
			<input type="radio" name="style_theme" value="panther" <?php
			if($style_theme == "panther" && $head_stylesheet_folder_name == "")
			{
				echo 'checked="checked"';
			}
			?>/>
			<img src="http://s.wordpress.com/wp-content/themes/vip/plugins/wp-page-numbers/panther/preview.gif" alt="" />
		</td>
	</tr>
	<tr>
		<td><strong>Stylish</strong></td>
		<td style="padding-top: 5px; padding-bottom: 5px;">
			<input type="radio" name="style_theme" value="stylish" <?php
			if($style_theme == "stylish" && $head_stylesheet_folder_name == "")
			{
				echo 'checked="checked"';
			}
			?>/>
			<img src="http://s.wordpress.com/wp-content/themes/vip/plugins/wp-page-numbers/stylish/preview.gif" alt="" />
		</td>
	</tr>
	<tr>
		<td><strong>Theme folder name: </strong><span style="color: red;">OVERRIDE</span> settings above</td>
		<td colspan="3">
			<input name="head_stylesheetsheet_folder_name" type="text" style="width:100%;" value="<?php echo $head_stylesheet_folder_name; ?>" />
		</td>
	</tr>
	
	<tr>
	<td></td>
		<td colspan="2">
			- Have you create a cool WP Page Numbers theme?<br />
			- Want to share it to the rest of the world? <a href="http://www.jenst.se/2000/01/01/kontakt">Contact me</a>.
		</td>
	</tr>
</table>
</div>

<div class="wrap">
<h2>Settings - Text</h2>
<table style="width: 100%;" border="0">

	<tr>
		<td style="width: 400px;"><strong>Default text: </strong>Page</td>
		<td colspan="3">
			<input name="page_of_page_text" type="text" style="width:100%;" value="<?php echo $page_of_page_text; ?>" />
		</td>
	</tr>
	
	<tr>
		<td><strong>Default text: </strong>of</td>
		<td colspan="3">
			<input name="page_of_of" type="text" style="width:100%;" value="<?php echo $page_of_of; ?>" />
		</td>
	</tr>
	
	<tr>
		<td><strong>Default text: </strong>&lt;</td>
		<td colspan="3">
			<input name="prevpage" type="text" style="width:100%;" value="<?php echo $prevpage; ?>" />
		</td>
	</tr>

	<tr>
		<td><strong>Default text: </strong>...</td>
		<td colspan="3">
			<input name="startspace" type="text" style="width:100%;" value="<?php echo $startspace; ?>" />
		</td>
	</tr>
	
	<tr>
		<td><strong>Default text: </strong>...</td>
		<td colspan="3">
			<input name="endspace" type="text" style="width:100%;" value="<?php echo $endspace; ?>" />
		</td>
	</tr>
	
	<tr>
		<td><strong>Default text: </strong>&gt;</td>
		<td colspan="3">
			<input name="nextpage" type="text" style="width:100%;" value="<?php echo $nextpage; ?>" />
		</td>
	</tr>
</table>
</div>
		
<div class="wrap">
<h2>Settings - show / hide</h2>
<table style="width: 100%;" border="0">
	
	<tr>
		<td style="width: 400px;"><strong>Show page info</strong></td>
		<td>
			<input type="checkbox" name="page_of_page" <?php
			if($page_of_page == "on" || $page_of_page == "")
			{
				echo 'checked="checked"';
			}
			?>/> Page 3 of 5
		</td>
	</tr>
	
	<tr>
		<td><strong>Show next / previous page text</td>
		<td>
			<input type="checkbox" name="next_prev_text" <?php
			if($next_prev_text == "on" || $next_prev_text == "")
			{
				echo 'checked="checked"';
			}
			?>/> &lt; &gt;
		</td>
	</tr>
	
	<tr>
		<td><strong>Show start and end numbers</td>
		<td>
			<input type="checkbox" name="show_start_end_numbers" <?php
			if($show_start_end_numbers == "on" || $show_start_end_numbers == "")
			{
				echo 'checked="checked"';
			}
			?>/> 1... ...5
		</td>
	</tr>
	
	<tr>
		<td><strong>Show page numbers</td>
		<td>
			<input type="checkbox" name="show_page_numbers" <?php
			if($show_page_numbers == "on" || $show_page_numbers == "")
			{
				echo 'checked="checked"';
			}
			?>/> 34567
		</td>
	</tr>
</table>
</div>

<div class="wrap">
<h2>Settings - Misc</h2>
<table style="width: 100%;" border="0">
	<tr>
		<td style="width: 400px;"><strong>Number of pages to show: </strong>10 (0 = unlimited)</td>
		<td colspan="3">
			<input name="limit_pages" type="text" style="width:100%;" value="<?php echo $limit_pages; ?>" />
		</td>
	</tr>
</table>
</div>

<div class="wrap">
<h2>Instructions</h2>
	<p>Most of the settings are already set to a default value if blank.</p>
	<table>
		<tr>
			<td><strong>Text options</strong></td>
		</tr>
		<tr>
			<td>You can set all the texts to what ever you like, except the numbers. They will still be numbers.<br /><br /></td>
		</tr>
		
		<tr>
			<td><strong>Number of pages to show</strong></td>
		</tr>
		<tr>
			<td>This will limit your paging menu. Set a of maximum amount of pages to be displayed at the same time. If the textfield is blank, 10 is set by default. If 0 is set, it will not limit the paging.<br /><br /></td>
		</tr>
	</table>

	<p class="submit">
		<input name="submitted" type="hidden" value="yes" />
		<input type="submit" name="Submit" value="Update Settings &raquo;" />
	</p>
</form>
</div><?php 
}

function wp_page_numbers_add_to_menu() {
    add_submenu_page('options-general.php', 'WP Page Numbers Options', 'Page Numbers', 'manage_options', __FILE__, 'wp_page_numbers_settings');
}
add_action('admin_menu', 'wp_page_numbers_add_to_menu');
?>
