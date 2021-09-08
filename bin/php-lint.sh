#!/bin/sh

npx phplint '**/*.php' '!vendor/**' '!node_modules/**' > /dev/null
