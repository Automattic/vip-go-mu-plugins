=== JSON feed ===
Contributors: chrisnorthwood
Tags: json, feed, feeds
Requires at least: 2.7.1
Tested up to: 2.9
Stable tag: 1.2

== Description ==

Pretty simple, really. Adds a new type of feed you can subscribe to. Simply
add `?feed=json` to anywhere you get a normal feed to get it in JSON form
(but with a cutdown version of events).

Version 1.1 added support for JSONP. To get a JSONP response instead of a
normal JSON structure, simply add `jsonp=callbackName` to your query, where
`callbackName` is the name of the function to be wrapped with.

To use this with jQuery, you'll want to do something like:

`$.getJSON("http://example.com/feed/?feed=json&jsonp=?",
       function(data){
               console.debug(data[0].title);   // print title of first item to firebug console
       });
});`

(thanks to Dan "Tack" Trudell)

== Frequently Asked Questions ==

= There doesn't appear to be much in the feed is there? =

That's right, I made this plugin for a very specific purpose - to provide
feeds for information screens around a University campus. It's very simple,
so should be easy to modify, or contact me and I might do it.

= Why does it require 2.7.1? =

It probably doesn't, but I've not tested it on any other version. Try it
and see, and let me know :)

== Changelog ==

= 1.0 =

Initial version

= 1.1 =

*NEW FEATURE* Added support for JSONP

= 1.2 =

*BUG FIX* Display date alongside time in JSON packet.

= 1.3 =

Improved documentation
*NEW FEATURE* 