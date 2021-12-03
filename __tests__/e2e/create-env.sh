#!/bin/bash
git submodule init
git submodule update
npm ci
npm install --prefix=$HOME/.local -g @automattic/vip
echo $'\t' | vip --slug=e2e-test-site dev-env create --title="E2E Testing site" --phpmyadmin --mu-plugins="./" --wordpress="5.8.1" --multisite=false