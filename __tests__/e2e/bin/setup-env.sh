#!/bin/bash

version=latest
pluginPath="./"
clientCodePath=image

while getopts v:p:c: flag
do
    case "${flag}" in
        v) version=${OPTARG};;
        p) pluginPath=${OPTARG};;
        c) clientCodePath=${OPTARG};;
    esac
done

# Destroy existing test site
vip dev-env destroy --slug=e2e-test-site

# Create and run test site
vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --phpmyadmin --mu-plugins=$pluginPath --wordpress="5.8.1" --multisite=false --client-code=$clientCodePath
vip --slug=e2e-test-site dev-env start

# Install classic editor plugin
docker exec e2etestsite_php_1 wp plugin install --activate --allow-root classic-editor

# Install specified version of WordPress
docker exec e2etestsite_php_1 wp core update --allow-root --version=$version --force