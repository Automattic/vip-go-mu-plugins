#!/bin/sh

npx phplint '**/*.php' '!vendor/**' '!node_modules/**' '!jetpack*/**' '!wp-parsely*/**' '!drop-ins/wp-memcached/stubs/**' > /dev/null
