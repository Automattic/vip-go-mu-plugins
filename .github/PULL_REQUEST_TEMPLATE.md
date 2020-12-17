<!--
## For Automatticians!

:wave: Just a quick reminder that this is a public repo. Please don't include any internal links or sensitive data (like PII, private code, client names, site URLs, etc. If you're not sure if something is safe to share, please just ask!

If you're not an Automattician, welcome! We look forward to your contribution! :heart:

-->
## Description
<!--
A few sentences describing the overall goals of the Pull Request.

Should include any special considerations, decisions, and links to relevant GitHub issues.

Please don't include internal or private links :)
-->

## Changelog Description
<!--
A description of the context of the change for a changelog. It should have a title, link to the PR, examples(if applicable), and why the change was made.

Example for a plugin upgrade:

### Jetpack 9.2.1

We upgraded Jetpack 9.2 to Jetpack 9.2.1.

Not a lot of significant changes in this patch release, just bugfixes and compatibility improvements.

#### Improved compatibility

- Site Health Tools: improve PHP 8 compatibility.
- Twenty Twenty One: add support for Jetpack’s Content Options.

#### Bug fixes

- Instant Search: fix layout issues with filtering checkboxes with some themes.
- WordPress.com Toolbar: avoid Fatal errors when the feature is not active.
- WordPress.com Toolbar: avoid 404 errors when loading the toolbar.

https://github.com/Automattic/vip-go-mu-plugins/pull/1905

Example for a feature change:

### New Filters: Adjust Brute Force Thresholds

We’ve added two new filters to our login limiting functionality, which gives you the ability to tweak the thresholds for our application-level brute force protections. For example, you may want to lower them during situations with high security sensitivity.

- `wpcom_vip_ip_username_login_threshold` : how many failed attempts to allow for an IP address and username combination
- `wpcom_vip_ip_login_threshold` : how many failed attempts to allow for an IP address

For example, if you wanted to only allow one attempt for a group of usernames per IP:

```
add_filter( 'wpcom_vip_ip_username_login_threshold', function( $threshold, $ip, $username ) {
    if ( 'adminuser' === $username || 'otheradminuser' === $username ) {
        $threshold = 1;
    }
 
    return $threshold;
}, 10, 3 );
```

https://github.com/Automattic/vip-go-mu-plugins/pull/1782
-->

## Checklist

Please make sure the items below have been covered before requesting a review:

- [ ] This change works and has been tested locally (or has an appropriate fallback).
- [ ] This change works and has been tested on a Go sandbox.
- [ ] This change has relevant unit tests (if applicable).
- [ ] This change has relevant documentation additions / updates (if applicable).
- [ ] (For Automatticians) I've created a changelog draft. 

## Steps to Test
<!--
Outline the steps to test and verify the PR here.

Example:

1. Check out PR.
1. Go to `wp-admin` > `Tools` > `Bakery`
1. Click on "Bake Cookies" button.
1. Verify cookies are delicious.
-->
