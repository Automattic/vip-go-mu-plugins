=== Ecwid Shopping Cart Shortcode ===
Tags: shopping cart, ecommerce, e-commerce, paypal, google checkout, 2checkout, store, shop, product catalog, inventory
Requires at least: 2.8
Tested up to: 3.8.1
Stable tag: 0.3

Ecwid is a free full-featured shopping cart that can easily be added to any blog
and takes less than 5 minutes to set up.

== Description ==
Ecwid is a full-featured shopping cart and an e-commerce solution that can easily be added to any blog or Facebook profile. It offers the performance and flexibility you need, with none of the hassles you don't.  
"Ecwid" stands for "ecommerce widgets".

There are eight key advantages to Ecwid:

- Free plan is always available.
- It has AJAX everywhere and supports drag-and-drop.
- It can be easily integrated to any existing site or Facebook profile in minutes.
- It can be mirrored on many sites at the same time. Add your store to many sites, manage it from one place.
- Integrates with social networks. Run your own store on Facebook, mySpace and many others, or let your customers share the links to your products and their purchases.
- Simple to use and maintain. For both store owner and customer.
- Lightning fast. New-gen technologies make Ecwid much faster than usual
  shopping carts regardless the hosting service you use.
- Seamless upgrades. You just wake up one day and enjoy new features.

- You can see the demo there: [www.ecwid.com/demo-frontend.html](http://www.ecwid.com/demo-frontend.html)
- More features:
[www.ecwid.com/key-features.html](http://www.ecwid.com/key-features.html)

This plugin adds [ecwid] shortcode to WordPress. Putting this shortcode without parameters produces the Ecwid Product Browser widget that displays demo
store content. Here are the parameters of this shortcode:
- id
  Ecwid store ID.
  If none specified, the widget displays Ecwid demo store content.
- widgets
  The list of widgets to display for this short code entry. Possible values are: productbrowser, categories, vcategories, minicart, search. Also one can specify several widgets like this: "search categories productbrowser". If none specified, the shortcode displays product browser widget. You can learn more about ecwid widgets here: http://kb.ecwid.com/w/page/15853259/Ecwid%20widgets
- layout (for minicart only)
  The minicart widget layout. Possible values are: attachToCategories, floating, Mini, MiniAttachToProductBrowser. If none specified, the minicart widget is displayed in its default layout. Here is the description of these layouts: http://kb.ecwid.com/w/page/15853298/Minicart
- grid (for productbrowser only)
  The number of columns and rows for the grid view of the product browser widget separated by comma. The default value is "3,3". If none specified, then the grid view will not be available in the product browser view.
- list (for productbrowser only)
  The number of items in the list view of the product browser widget. The default value is 10. If not specified, then the list view will not be available in the product browser view.
- table (for productbrowser only)
  The number of rows in the table viewof the product browser widget. The default value is 20. If not specified, then the table view will not be available in the product browser view.
- category_view (for productbrowser only)
  The default view for products in categories. Possible values are: list, grid, table. The default value is grid.
- search_view (for productbrowser only)
  The default view for search results. Possible values are: list, grid, table. The default value is grid.
- default_category_id (for productbrowser only)
  The default category to be displayed. If none specified, the product browser opens the root category of the store. Please, refer to this page for more information about this parameter: http://kb.ecwid.com/w/page/15853258/Default%20category%20for%20product%20browser
- responsive (for productbrowser only)
  Whether to use the product browser responsive design feature. Possible values are: yes, no. The default value is "yes".

So, an easy start from scratch having all parameters on hand would be to use the following shortcode:
[ecwid id="1003" grid="5,5" list="5" table="10" category_view="table" search_view="list" default_category_id="3002" layout="floating" widgets="search categories productbrowser minicart"]
Note that this shortcode does not have the vertical categories widget because in most cases it is better to be put separately in a side menu.

== Changelog ==

= 0.2 =
- Code refactoring and code style changes
= 0.1 =
- Initial version
