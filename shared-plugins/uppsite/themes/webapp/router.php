<?php
/**
 * Helper file that helps routing the requests to the webapp.
 * Webapp routing is <list_type>/<param_name>/<param_value>, where:
 *  - list_type = 'full' (Post or Page) or 'titles' (List)
 *  - param_name = parameter name for the query (e.g. categoryname / pagename)
 *  - param_value = parameter value.
 */

global $wp_query;
$type = $value = "";

$list_type = is_single() || is_page() ? "full" : "titles";

// Find the parameter name (there should be only one that is occupied, use that.)
foreach ($wp_query->query as $paramName => $paramValue) {
    if (!empty($paramValue)) {
        $type = $paramName;
        $value = $paramValue;
    }
}
// If "Business" part is alive, all except pages should redirect to the "blog" webapp.
$forceBlog = in_array(uppsite_get_type(), array(UPPSITE_TYPE_BUSINESS, UPPSITE_TYPE_BOTH)) && !is_page() ? "&doUppSiteBlog" : "";

wp_safe_redirect(sprintf("%s?is_uppsite=1%s#%s/%s/%s", home_url(), $forceBlog, $list_type, $type, $value));
exit;