#!/usr/bin/env bash

if [ $# -lt 1 ]; then
  echo "usage: $0 <es-version>"
  exit 1
fi

ES_VERSION=$1

setup_es() {
  download_url=$1
  mkdir /tmp/elasticsearch
  wget -O - $download_url | tar xz --directory=/tmp/elasticsearch --strip-components=1
}

start_es() {
  echo "Starting Elasticsearch $ES_VERSION..."
  echo "/tmp/elasticsearch/bin/elasticsearch $1 > /tmp/elasticsearch.log &"
  /tmp/elasticsearch/bin/elasticsearch $1 > /tmp/elasticsearch.log &
}

if [[ "$ES_VERSION" == 1.* ]]; then
  setup_es https://download.elastic.co/elasticsearch/elasticsearch/elasticsearch-${ES_VERSION}.tar.gz
elif [[ "$ES_VERSION" == 2.* ]]; then
  setup_es https://download.elastic.co/elasticsearch/release/org/elasticsearch/distribution/tar/elasticsearch/${ES_VERSION}/elasticsearch-${ES_VERSION}.tar.gz
elif [[ "$ES_VERSION" == [56].* ]]; then
  setup_es https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-${ES_VERSION}.tar.gz
else
  setup_es https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-${ES_VERSION}-linux-x86_64.tar.gz
fi

if [[ "$ES_VERSION" == [12].* ]]; then
  start_es '-Des.path.repo=/tmp'
else
  start_es '-Epath.repo=/tmp -Enetwork.host=_local_'
fi
