#
# pre-requisite: checkout submodules before building
#
FROM debian:buster-slim
WORKDIR /mu-plugins
COPY . .
CMD /bin/sleep infinity
