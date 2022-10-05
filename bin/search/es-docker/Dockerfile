ARG ES_VERSION=7.17.2
FROM docker.elastic.co/elasticsearch/elasticsearch:${ES_VERSION}
ENV ELECTRON_EXTRA_LAUNCH_ARGS=--disable-gpu
ENV LIBVA_DRIVER_NAME=--disable-software-rasterizer

RUN if [ -d plugins/ingest-attachment ]; then true ; else ./bin/elasticsearch-plugin install ingest-attachment -b; fi
