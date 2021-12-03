#!/bin/bash
vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --phpmyadmin --mu-plugins="/code" --wordpress="5.8.1" --multisite=false --client-code="/vip-go-skeleton"
vip --slug=e2e-test-site dev-env start