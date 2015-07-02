# Adbusters for WordPress
A WordPress plugin that loads a set of iframe busters for popular ad networks.
 
* [Download the plugin from WordPress.org](http://wordpress.org/plugins/adbusters).

Have you found a bug, or have a feature request? Github pull requests are warmly received. :)

## Guidelines for iFrame Busters

The following are common XSS vulnerabilities found in iFrame busters.

1. Unescaped URL parameter values
2. Parameters that accept any domain

## Unescaped URL parameter values

Special characters should be removed or converted into their equivalent HTML/hex entity. The characters in the following table can be used to write malicious code on the page.

`example.com/iframebuster.html?parameter="></script><script>alert('XSS')</script>`

	Character => HTML Entity
	    &     =>    &amp;   
	    <     =>    &lt;    
	    >     =>    &gt;    
	    "     =>    &quot;  
	    '     =>    &#x27;  
	    /     =>    &#x2F;  


## Parameters that accept any domain

When passing a domain as a parameter to write a script tag onto the page, it should be restricted to an approved domain(s). 

`example.com/iframebuster.html?server=evildomain.com`

## Examples of Safe iFrame Busters

* [DARTIframe.html](https://github.com/Automattic/Adbusters/blob/master/templates/doubleclick/DARTIframe.html)
* [ifr_b.html](https://github.com/Automattic/Adbusters/blob/master/templates/adcentric/ifr_b.html)
* [Pictela_iframeproxy.html](https://github.com/Automattic/Adbusters/blob/master/templates/pictela/Pictela_iframeproxy.html)

## XSS Attack Prevention Guidelines

Further guidelines can be found at [ha.ckers.org/xss.html](http://ha.ckers.org/xss.html), which covers the above rules as well as many others.
