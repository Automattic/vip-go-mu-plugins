#!/bin/sh

set -eu

script_dir="$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)"
setup_script="${script_dir}/setup-env.sh"
tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT HUP INT TERM

# Keep acquisition local while exercising setup-env.sh's actual jq expressions.
cat > "${tmp_dir}/manifest.json" <<'EOF'
[
  {"ref":"6.7","tag":"6.7.9","prerelease":false},
  {"ref":"6.8","tag":"6.8.4","prerelease":false},
  {"ref":"6.9","tag":"6.9.1","prerelease":false},
  {"ref":"6.10-beta","tag":"9.9.9-beta","prerelease":true}
]
EOF

cat > "${tmp_dir}/wget" <<'EOF'
#!/bin/sh
cat "${MANIFEST_FIXTURE}"
EOF

cat > "${tmp_dir}/vip" <<'EOF'
#!/bin/sh
printf '%s\n' "$*" >> "${VIP_COMMANDS_LOG}"
for argument do
	case "${argument}" in
		--wordpress=*) printf '%s\n' "${argument}" >> "${VIP_VERSIONS_LOG}" ;;
	esac
done
EOF

chmod +x "${tmp_dir}/wget" "${tmp_dir}/vip"

run_setup() {
	requested_version=$1
	shift
	: > "${tmp_dir}/vip-commands"
	: > "${tmp_dir}/vip-versions"
	env PATH="${tmp_dir}:${PATH}" \
		MANIFEST_FIXTURE="${tmp_dir}/manifest.json" \
		VIP_COMMANDS_LOG="${tmp_dir}/vip-commands" \
		VIP_VERSIONS_LOG="${tmp_dir}/vip-versions" \
		WORDPRESS_VERSION="${requested_version}" \
		"${setup_script}" "$@" > "${tmp_dir}/output" 2>&1
}

assert_selected_version() {
	expected_version=$1
	grep -Fq -- "Creating E2E site with WordPress version ${expected_version} " "${tmp_dir}/output"
	grep -Fq -- "dev-env create --title=E2E Testing site" "${tmp_dir}/vip-commands"
	test "$(cat "${tmp_dir}/vip-versions")" = "--wordpress=${expected_version}"
}

command -v jq > /dev/null

run_setup ''
assert_selected_version '6.9.1'

run_setup '6.8'
assert_selected_version '6.8.4'

run_setup '6.8' -v '6.7'
assert_selected_version '6.7.9'

# An unmatched ref intentionally falls back to trunk and triggers core update.
run_setup 'unknown'
assert_selected_version 'trunk'
grep -Fq -- 'wp core update --force --version=unknown' "${tmp_dir}/vip-commands"

printf 'WordPress version selection passed: stable latest, workflow input, explicit -v, and unknown-ref fallback.\n'
