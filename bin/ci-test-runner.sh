#!/bin/sh

if [ "$DISABLE_JETPACK" = "1" ]; then
    echo "define( 'VIP_JETPACK_SKIP_LOAD', 'true' );" >> 000-vip-init.php
    /usr/local/bin/runner --exclude-group jetpack-required
else
    /usr/local/bin/runner
fi
