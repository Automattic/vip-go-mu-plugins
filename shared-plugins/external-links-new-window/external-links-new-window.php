<?php
/*
Plugin Name: External Links in a New Window
Plugin URI: http://javimoya.com/blog/2006/10/25/plugin-wordpress-enlaces-externos-en-una-nueva-ventana/
Description: Searches the text for links outside of the domain of the blog.	 To these, it adds <strong>target="_blank"</strong>.
Author: Javi Moya
Version: 1.0
Author URI: http://javimoya.com/blog/
*/

/* Descripción:
   El objetivo de este plugin es hacer que todos los enlaces externos (es decir, 
   los enlaces a dominios diferentes al nuestro) en nuestras entradas
   (también en los comentarios si así lo queremos) se abran automáticamente
   en una nueva ventana del navegador. También esta funcionalidad
   se aplica a los enlaces a imágenes dentro de nuestro dominio. */

/* Importante: Este plugin es una simple modificación de 
   identify External Links (http://txfx.net/code/wordpress/identify-external-links/),
   que pretender añadir la funcionalidad de poder
   abrir enlaces externos en una nueva ventana.
   Aquel plugin teóricamente incorpora esa opción,
   pero no funciona. */

/*	Copyright 2006	Javi Moya (email: kkcorreo@yahoo.es)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Pon a false esta variable si deseas que los enlaces a imágenes de tu dominio
// no se les añada el target="_blank" (por defecto se abriran en una nueva ventana)
$txfx_elnw_images_target_blank = true;

function wp_elnw_get_domain_name_from_uri($uri){
	preg_match("/^(http:\/\/)?([^\/]+)/i", $uri, $matches);
	$host = $matches[2];
	preg_match("/[^\.\/]+\.[^\.\/]+$/", $host, $matches);
	return $matches[0];	   
}

function wp_elnw_es_imagen($uri) {
	
	$cadena=strtolower($uri);
	if ((strpos($cadena, '.jpg') > 0) || (strpos($cadena, '.gif') > 0) || (strpos($cadena, '.png')  > 0))
	{
		return true;		 
	} 
	return false; 
} 

function wp_elnw_parse_external_links($matches){
    global $txfx_elnw_images_target_blank;

	$link = '';

	/* para mejorar el rendimiento se puede cambiar 
	wp_elnw_get_domain_name_from_uri($_SERVER["HTTP_HOST"]) 
	 por directamente el nombre del dominio (ejemplo: "javimoya.com") (con las comillas incluidas) */
	if (($txfx_elnw_images_target_blank && wp_elnw_es_imagen($matches[3])) || ( wp_elnw_get_domain_name_from_uri($matches[3]) != wp_elnw_get_domain_name_from_uri($_SERVER["HTTP_HOST"]) )) {
		$link = '<a href="' . $matches[2] . '//' . $matches[3] . '"' . $matches[1] . $matches[4] . ' target="_blank">' . $matches[5] . '</a>';	 
	} else {
		$link = '<a href="' . $matches[2] . '//' . $matches[3] . '"' . $matches[1] . $matches[4] . '>' . $matches[5] . '</a>';
	}
	return apply_filters( 'elnw_external_link', $link, $matches );
}
 
function wp_elnw_external_links($text) {	

	$pattern = '/<a (.*?)href="(.*?)\/\/(.*?)"(.*?)>(.*?)<\/a>/i';
	$text = preg_replace_callback($pattern,'wp_elnw_parse_external_links',$text);
	 
	return $text;
}

// filters have high priority to make sure that any markup plugins like Textile or Markdown have already created the HTML links
add_filter('the_content', 'wp_elnw_external_links', 999);
add_filter('the_excerpt', 'wp_elnw_external_links', 999);
add_filter('comment_text', 'wp_elnw_external_links', 999);
?>
