Objects to Objects [![Build Status](https://travis-ci.org/voceconnect/objects-to-objects.png?branch=master)](https://travis-ci.org/voceconnect/objects-to-objects)
==================

Contributors: prettyboymp, klangley, csloisel, markparolisi, jeffstieler  
Tags: relationships, mapping, connections, many-to-many  
Requires at least: 3.6  
Tested up to: 3.8  
Stable tag: 1.2.4  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A WordPress plugin/module that provides the ability to map relationships between posts and other post types.

## Description
This plugin provides a development layer on top of WordPress' taxonomy system that simplifies the ability to create many-to-many relationships between post types.

## Installation

### As standard plugin:
> See [Installing Plugins](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

### As theme or plugin dependency:
> After dropping the plugin into the containing theme or plugin, add the following:
```php
if( ! class_exists( 'O2O' ) ) {
	require_once( $path_to_o2o . '/objects-to-objects.php' );
}
```

## Usage

### Registering a connection

Relationships are registered directly through the ```O2O::Register_Connection()``` method.

#### Parameters
* ```$name``` (string) - A string used as the lookup key when referencing the connection at later points.
* ```$from_object_types``` (array, string) - An array of object or post types that act as the parent for the connections.  Note that because O2O uses WP's taxonomy system as it's data interface, care should be taken in deciding which object types are the from object vs the to object since options like sorting can't be applied to both directions of the connection.
* ```$to_object_types``` (array, string) - An array of object or post types that the from types can be connected to.
* ```$args``` (array) - An array of options for the connection
	* ```reciprocal``` (boolean) - Default: false.  By default, the ability to make connections is only displayed on the edit screen for the ```$from_object_types```.
	* ```rewrite``` (mixed) - Options: ```false```, ```'from'```, ```'to'```.  Default: false.  When set to ```'to'```, rewrite rules will automatically be added to supply an endpoint that lists the attached to objects of a single post object.  When set to ```'from'`` the rewrite rule will be added that lists the attahed from objects.
	* ```to``` - (array) - An array of options specifically applied to the interface of connecting ```to``` objects.
		* ```sortable``` (boolean) - Default: false.  When true, the user can set an order to the connected objects.
		* ```limit``` (integer) - Default: null.  When set, the interface that allows users to add connected items stops allowing new connections to that object once the limit is reached.
		* ```labels``` (array) - The labels to use with the connection interface.
			* ```name``` (string) - The plural label to use when describing the connected objects.
			* ```singular_name``` (string) - The singular label to use when describing the connected objects.
	* ```from``` - (array) - An array of options specifically applied to the interface of connecting ```from``` objects.
		* ```limit``` (integer) - Default: null.  When set, the interface that allows users to add connected items stops allowing new connections to that object once the limit is reached.
		* ```labels``` (array) - The labels to use with the connection interface.
			* ```name``` (string) - The plural label to use when describing the connected objects.
			* ```singular_name``` (string) - The singular label to use when describing the connected objects.
	* ```metabox``` - (array) - An array of options specifically applied to the metabox user interface
		* ```orderby``` (string) - Default: post_date. Field to order the posts by within the metabox.
		* ```order``` (string) - Default: DESC. Order, ASC or DESC, to display the posts within the metabox.
		* ```context``` (string) - Default: side. Context to display the metabox on the post edit screen. Options are side, normal, and advanced.


#### Example

```php
add_action('init', 'register_o2o_connection');
/**
 * Registers a many to many connection between posts and galleries that allows
 * related galleries to be assigned to a single post for listing.
 */
function register_o2o_connection() {
	O2O::Register_Connection('post_galleries', 'post', 'gallery', array(
		'reciprocal' => true,
		'to' => array(
			'sortable' => true,
			'labels' => array(
				'name' => 'Galleries',
				'singular_name' => 'Gallery'
			)
		),
		'from' => array(
			'labels' => array(
				'name' => 'Posts',
				'singular_name' => 'Post'
			)
		)
	));
}
```

### Querying a connection
O2O provides a custom WP_Query query_var that will filter the query based on the given connection details.

* ```o2o_query``` (array) - An array defining how a connection should be queried agaist.
	* ```connection``` (string) - The key/name of the connection being queried against.
	* ```direction``` (string) - Options 'to', 'from'.  Default: 'to'.  The side of the connection being queried.  When set to 'to', only ```$to_object_types``` will be returned.
	* ```id``` (integer) - The ID of the post object from which the relationships will be queried.
	* ```post_name``` (string) - The post_name of the post object from which the relationships will be queried.  Will only be used if ```id``` is not set.
* ```o2o_orderby``` (string) - Set to the connection name to order the results by that connection's ordering.  Note that the connection must have ordering set to true for the given direction.