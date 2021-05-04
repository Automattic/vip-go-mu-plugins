# Search Dev Tools

Search Dev Tools is aiming to be a one-stop shop for developers integrating Search into their applications.

## Key Features

- See general debug information like which post types and post statuses are indexable, what meta keys are allowed, and is the site being rate-limited right now.
- See the list of the queries that were executed during this request:
	* Tweak a query in an editor with syntax higlighting and see the updated result.
	* See the stack trace for a query.
	* See WP_Query arguments for a query
	* (In the future) an API Playground.

## Architecture

The backend consists of a single REST endpoint whose sole purpose is to translate the incoming request into a Search API request and pass back the results to the frontend.

The frontend is a Preact app which mounts onto an Admin Bar node and renders into a portal in DOM. The UI is displayed as an overlay. See [src/](src/).

We use a combination of Prism.js and React Simple Code Editor to highlight the syntax and implement basic editor functionality for textarea. We might switch to something more robust and better suited for editing huge blobs of JSON.

## Local Dev

For the best results we recommend using [mu-plugins dev environment](https://github.com/Automattic/vip-go-mu-dev) as it comes with everything that's needed for the working local Search instance, but it's possible to work with frontend code only.

Frontend:
1. Install dependencies `npm i` or `yarn`.
1. `npm run dev` will build the app and start a standalone server with hot reload and start hacking in `src/`.
1. To build the WordPress assets, use `npm run build`.

Mock data is defined in [src/template.html](src/template.html) and reflects what WordPress is generating for any given Search-enabled page.

## Contributing

Please check for open issues first, if there's not an issue, feel free to create one.

Please be mindful about the bundle size, as in before using a dependency see how much it does add to a bundle.

## Build

For now commit the results of `npm run build` as a separate commit. This will change in the near future once we figure out the best way to build files as a part of our CI pipeline.