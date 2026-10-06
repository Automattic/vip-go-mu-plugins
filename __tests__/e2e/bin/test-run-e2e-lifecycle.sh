#!/bin/sh
set -eu

script_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT HUP INT TERM
mkdir -p "$tmp/bin"
cat > "$tmp/bin/vip" <<'STUB'
#!/bin/sh
printf 'vip %s\n' "$*" >> "$E2E_TEST_LOG"
case "$*" in
    *'dev-env create'*)
        if [ "${E2E_TEST_FAIL_CREATE:-false}" = 'true' ]; then
            exit 1
        fi
        ;;
esac
STUB
cat > "$tmp/bin/wget" <<'STUB'
#!/bin/sh
printf '{}'
STUB
cat > "$tmp/bin/jq" <<'STUB'
#!/bin/sh
printf '6.8.3\n'
STUB
cat > "$tmp/bin/npx" <<'STUB'
#!/bin/sh
printf 'npx %s\n' "$*" >> "$E2E_TEST_LOG"
exit "${E2E_TEST_NPX_STATUS:-0}"
STUB
chmod +x "$tmp/bin/"*
export PATH="$tmp/bin:$PATH"
export E2E_TEST_LOG="$tmp/log"

# External targets skip all VIP provisioning and teardown.
E2E_BASE_URL=https://external.example "$script_dir/run-e2e.sh" --grep smoke
! rg -q '^vip ' "$E2E_TEST_LOG"
rg -q '^npx playwright test -c playwright.config.ts --grep smoke$' "$E2E_TEST_LOG"

# Local runs use a unique slug for all lifecycle operations and clean up on failures.
: > "$E2E_TEST_LOG"
if E2E_TEST_NPX_STATUS=7 "$script_dir/run-e2e.sh"; then
    echo 'expected the stubbed Playwright run to fail' >&2
    exit 1
fi
slug=$(sed -n 's/^vip --slug=\([^ ]*\) dev-env create.*/\1/p' "$E2E_TEST_LOG")
[ -n "$slug" ]
rg -q "^vip dev-env destroy --slug=$slug$" "$E2E_TEST_LOG"
! rg -q 'e2e-test-site' "$E2E_TEST_LOG"

# Operators can retain a failed local site for diagnosis.
: > "$E2E_TEST_LOG"
if E2E_TEST_NPX_STATUS=7 E2E_PRESERVE_ON_FAILURE=true "$script_dir/run-e2e.sh"; then
    echo 'expected the stubbed Playwright run to fail' >&2
    exit 1
fi
! rg -q '^vip dev-env destroy ' "$E2E_TEST_LOG"

# A failed create for a caller-selected existing slug is never cleaned up.
: > "$E2E_TEST_LOG"
if E2E_TEST_FAIL_CREATE=true E2E_SITE_SLUG=e2e-existing "$script_dir/run-e2e.sh"; then
    echo 'expected the stubbed VIP create to fail' >&2
    exit 1
fi
! rg -q '^vip dev-env destroy --slug=e2e-existing$' "$E2E_TEST_LOG"
echo 'E2E lifecycle wrapper checks passed'
