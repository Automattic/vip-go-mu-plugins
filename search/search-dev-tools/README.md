# Search Dev Tools

Search Dev Tools is aiming to be a one stop shop for developers integrating Search in their applications.


## Key Features

- See general debug information, things like which post types and post statuses are indexable, what meta keys are allowed, is the site being rate-limited right now.
- See the list of the queries that were executed during this request:
	* Tweak a query in an editor with syntax higlighting and see the updated result.
	* See the stack strace for a query
	* (In the future) an API Playground

## Architecture

The backend consists of a single (ATM) REST endpoint whose sole purpose is to translate the incoming request into a Search API request and pass back the results to frontend.

The frontend is a Preact app, see [src/](src/) which mounts onto Admin Bar node.

## Local Dev

For the best results we recommend using [mu-plugins dev environment](https://github.com/Automattic/vip-go-mu-dev) as it comes with everything that's needed for the working local Search instance, but it's possible to work with frontend code only.

Frontend:
1. Install dependencies `npm i` or `yarn`
1. `npm run dev` will build the app and start a standalone server with Hot Reload and start hacking in src/
1. To build the WordPress assets use `npm run build`

Mock data is defined in [src/template.html](src/template.html) and reflects what WP is generating for any given Search-enabled page.
