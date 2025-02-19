(function (apiKey, initData) {
    /**
     * Primitive detection of an already-loaded Pendo script by a non-VIP actor.
     * Are you here because you are trying to disable this script from loading?
     * Define the following constant:
     *
     * define( 'VIP_DISABLE_PENDO_TELEMETRY', true );
     */

    // Is the pendo global already defined with an API key? Bail.
    if (window.pendo && window.pendo.apiKey) {
        return;
    }

    (function (p, e, n, d, o) {
        var v, w, x, y, z;
        o = p[d] = p[d] || {};
        o._q = o._q || [];
        v = ["initialize", "identify", "updateOptions", "pageLoad", "track"];
        for (w = 0, x = v.length; w < x; ++w) {
            (function (m) {
                o[m] = o[m] || function () {
                    o._q[m === v[0] ? "unshift" : "push"](
                        [m].concat([].slice.call(arguments, 0)),
                    );
                };
            })(v[w]);
        }
        y = e.createElement(n);
        y.async = !0;
        y.src = "https://cdn.pendo.io/agent/static/" + apiKey + "/pendo.js";
        z = e.getElementsByTagName(n)[0];
        z.parentNode.insertBefore(y, z);
    })(window, document, "script", "pendo");

    pendo.initialize(initData);
})(
    window.VIPPendo.apiKey,
    window.VIPPendo.initData,
);
