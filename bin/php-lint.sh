#!/bin/sh

npx phplint '**/*.php' '!vendor/**' '!node_modules/**' '!jetpack*/**' '!wp-parsely*/**' > /dev/null
