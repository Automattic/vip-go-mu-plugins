#!/bin/bash

KEY_PASSWORD=`openssl rand -hex 32`
cat new-key | openssl aes-256-cbc -k "$KEY_PASSWORD" -a  > new-key.enc
travis encrypt --add KEY_PASSWORD=$KEY_PASSWORD
