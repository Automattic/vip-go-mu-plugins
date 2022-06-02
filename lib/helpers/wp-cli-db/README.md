# WP-CLI `wp db` Command Helper

Add necessary configuration details prior to handing off control to the [wp-cli/db-command](https://github.com/wp-cli/db-command/).

**IMPORTANT:** Everything in this directory runs **before** WordPress loads, so no wp-specific functions may be used (actions, filters, etc.)
