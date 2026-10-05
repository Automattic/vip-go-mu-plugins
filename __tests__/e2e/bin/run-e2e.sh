#!/bin/sh

set -eu

basedir="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$basedir"

# An explicitly supplied target is managed by the caller and must not trigger VIP setup/cleanup.
if [ -n "${E2E_BASE_URL:-}" ]; then
    exec npx playwright test -c playwright.config.ts "$@"
fi

E2E_SITE_SLUG=${E2E_SITE_SLUG:-e2e-$(date +%s)-$$}
export E2E_SITE_SLUG
E2E_SITE_CREATED_MARKER_DIR=$(mktemp -d "${TMPDIR:-/tmp}/vip-e2e-site.XXXXXX")
E2E_SITE_CREATED_MARKER="${E2E_SITE_CREATED_MARKER_DIR}/created"
export E2E_SITE_CREATED_MARKER

cleanup() {
    result=$?
    trap - EXIT HUP INT TERM
    if [ -f "$E2E_SITE_CREATED_MARKER" ]; then
        if [ "$result" -eq 0 ] || [ "${E2E_PRESERVE_ON_FAILURE:-false}" != "true" ]; then
            vip dev-env destroy --slug="$E2E_SITE_SLUG" || true
        else
            printf 'Preserving failed E2E site: %s\n' "$E2E_SITE_SLUG" >&2
        fi
    fi
    rm -f "$E2E_SITE_CREATED_MARKER"
    rmdir "$E2E_SITE_CREATED_MARKER_DIR"
    exit "$result"
}
trap cleanup EXIT HUP INT TERM

./bin/setup-env.sh
npx playwright test -c playwright.config.ts "$@"
