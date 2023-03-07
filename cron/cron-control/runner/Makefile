# MakeFile for cron-control-runner

GO_IMAGE   ?= docker.io/library/golang:1.15.7
EXECUTABLE ?= cron-control-runner
GOOS       ?= linux
GOARCH     ?= amd64

all:
	docker run --rm -v "$(CURDIR)/..:/workspace" -w "/workspace/runner" -it \
		-e "GOOS=$(GOOS)" -e "GOARCH=$(GOARCH)" -e "CGO_ENABLED=0" \
		"$(GO_IMAGE)" \
		go build -o "../bin/$(EXECUTABLE)" ./...

clean:
	@ rm -f  ${DIR}/../bin/${EXECUTABLE}
