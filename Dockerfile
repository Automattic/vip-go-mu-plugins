#
# pre-requisite: checkout submodules before building
#
FROM alpine:latest
RUN apk update && apk add --no-cache rsync
WORKDIR /mu-plugins
COPY . .
ENV MU_PLUGINS_DIR=/host/mu-plugins
CMD rsync --delete -av /mu-plugins/ "${MU_PLUGINS_DIR}/"
