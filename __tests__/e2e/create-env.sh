#!/bin/bash
echo $'\t' | vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --phpmyadmin --mu-plugins="./" --wordpress="5.8.1" --multisite=false
vip --slug=e2e-test-site dev-env start