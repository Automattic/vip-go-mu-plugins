# Search Dev Tools

Search Dev Tools is aiming to be a one-stop shop for developers integrating Search into their applications.

## Key Features

- A header strip with general debug information: Elasticsearch version, whether the site is rate limited, concurrent requests, and which post types, post statuses and meta keys are indexable. Short lists show inline; longer ones open a dropdown.
- A sidebar listing every query executed during the request, with timing (color coded by speed), hit count, failures and the calling file.
- A detail view for the selected query:
	* Edit the request JSON in a syntax highlighted editor and re-run it with **Run** (shown once the request is edited), **Run query** or ⌘/Ctrl+Enter. **Reset** restores the original request and response.
	* See the WP_Query arguments and the stack trace (with the calling frame highlighted).
	* Browse the response in a collapsible JSON tree, with Elasticsearch errors surfaced above it. **Expand all** makes the whole response searchable with the browser's find.
- Multisite cross-site queries (`'sites' => [ 2, 3 ]` or `'all'`) show how far they reached: the site count after the hits in the sidebar (`4 hits · 2 sites`), the index list in the header (for the network alias, the real indexes it resolved to, e.g. `post-1, post-2, post-3-v2 via post-all`), returned hits per index above the response, and the index on each hit. A "This site" tag marks the per-site settings in the header strip.
- Large payloads stay responsive: above 1 MB or 50,000 lines, **Expand all** shows the response as plain text (with a **Show tree anyway** option); a response that would exceed 50,000 lines even folded (e.g. thousands of aggregation buckets) starts as plain text; and requests above 200 KB are edited without syntax highlighting.
- Light and dark themes. The default follows the system preference; the choice is remembered per browser.

## Architecture

The backend consists of a single REST endpoint whose sole purpose is to translate the incoming request into a Search API request and pass back the results to the frontend. The page data (`window.VIPSearchDevTools`) is printed in the footer; info strip items carry a stable `key` the frontend relies on.

The frontend is a Preact app which mounts onto an Admin Bar node and renders into a portal in DOM. The UI is displayed as a full-screen panel below the Admin Bar. See [src/](src/).

We use Prism.js and React Simple Code Editor for the request editor. The response viewer is a small custom JSON tree ([src/components/json-tree.js](src/components/json-tree.js)).

## Local Dev

For the best results, we recommend using the [VIP local development environment](../../README.md) as it comes with everything that's needed for the working local Search instance, but it's possible to work with frontend code only.

With a VIP dev-env:
1. Create or reuse an environment with Elasticsearch that mounts this checkout as mu-plugins, e.g. `vip dev-env create --elasticsearch --mu-plugins=/path/to/vip-go-mu-plugins`.
1. New environments created with `--elasticsearch` set `VIP_ENABLE_VIP_SEARCH` and `VIP_ENABLE_VIP_SEARCH_QUERY_INTEGRATION` automatically. On an older environment, define both as `true` in the app's `vip-config/vip-config.php`.
1. Index content: `vip dev-env exec -- wp vip-search index --setup --skip-confirm` (add `--url=<site>` on multisite).
1. Run `npx webpack --watch --env production` in this directory. It rebuilds `build/` on save; reload a front-end page such as `/?s=hello` and open **Search** in the Admin Bar.

Frontend only:
1. Install dependencies `npm i` or `yarn`.
1. Change `permission_callback` to `__return_true` in `register_rest_routes()` in order to be able to do unauthenticated requests to the REST endpoint.
1. Make sure your dev environment allows cross-origin requests to the REST endpoint. Look for `Access-Control-Allow-Origin` header on the response from the REST endpoint.
1. `npm run dev` will build the app and start a standalone server with hot reload and start hacking in `src/`.
1. To build the WordPress assets, use `npm run build`.

Mock data is defined in [src/template.html](src/template.html) and reflects what WordPress is generating for any given Search-enabled page.

## Tests

- End-to-end: [`__tests__/e2e/specs/searchdevtools.spec.ts`](../../__tests__/e2e/specs/searchdevtools.spec.ts) with the page object in [`__tests__/e2e/lib/pages/search-page.ts`](../../__tests__/e2e/lib/pages/search-page.ts). Run only this spec with `npm --prefix __tests__/e2e test -- specs/searchdevtools.spec.ts` from the repo root. Update both when the UI changes; they select elements by the `sdt-*` class names and ARIA roles.
- Unit: [`tests/utils.test.mjs`](tests/utils.test.mjs) covers the view-model helpers (labels, cross-site scope, index names, timing, failure detection, edit state). Run `npm test` in this directory; it uses Node's built-in test runner.
- PHP: [`tests/search/test-search-dev-tools.php`](../../tests/search/test-search-dev-tools.php), e.g. `CI=1 ./bin/test.sh --filter Search_Dev_Tools_Test`.
- CI ([`.github/workflows/search-dev-tools.yml`](../../.github/workflows/search-dev-tools.yml)) lints, unit tests and builds this package.
- The e2e site is a single site, so cross-site queries are covered by the unit tests. To try them locally on a multisite dev-env, follow [Enterprise Search on multisite](https://docs.wpvip.com/enterprise-search/multisite/): define `EP_IS_NETWORK`, run `wp vip-search recreate-network-alias`, then query with `'sites' => [ 2, 3 ]` or `'sites' => 'all'`.

## Contributing

Please check for open issues first, if there's not an issue, feel free to create one.

Please be mindful about the bundle size, as in before using a dependency see how much it does add to a bundle.

## Build

For now commit the results of `npm run build` as a separate commit. This will change in the near future once we figure out the best way to build files as a part of our CI pipeline.
