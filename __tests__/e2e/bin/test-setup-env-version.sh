#!/bin/sh

set -eu

script_dir="$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)"
setup_script="${script_dir}/setup-env.sh"
tmp_dir="$(mktemp -d)"
trap 'rm -rf "${tmp_dir}"' EXIT HUP INT TERM

cat > "${tmp_dir}/wget" <<'EOF'
#!/bin/sh
printf '[]\n'
EOF

cat > "${tmp_dir}/jq" <<'EOF'
#!/bin/sh
printf '%s\n' "$*" >> "${JQ_ARGS_LOG}"
requested=
while [ "$#" -gt 0 ]; do
	if [ "$1" = '--arg' ] && [ "${2:-}" = 'ref_value' ]; then
		requested=$3
		break
	fi
	shift
done
if [ -n "${requested}" ]; then
	printf '%s.4\n' "${requested}"
else
	printf '%s\n' "${LATEST_TAG}"
fi
EOF

cat > "${tmp_dir}/vip" <<'EOF'
#!/bin/sh
printf '%s\n' "$*" >> "${VIP_COMMANDS_LOG}"
EOF

chmod +x "${tmp_dir}/wget" "${tmp_dir}/jq" "${tmp_dir}/vip"

run_setup() {
	requested_version=$1
	latest_tag=$2
	shift 2
	: > "${tmp_dir}/jq-args"
	: > "${tmp_dir}/vip-commands"
	env PATH="${tmp_dir}:${PATH}" \
		JQ_ARGS_LOG="${tmp_dir}/jq-args" \
		VIP_COMMANDS_LOG="${tmp_dir}/vip-commands" \
		LATEST_TAG="${latest_tag}" \
		WORDPRESS_VERSION="${requested_version}" \
		"${setup_script}" "$@" > "${tmp_dir}/output" 2>&1
}

assert_selected_version() {
	expected_version=$1
	grep -Fq -- "Creating E2E site with WordPress version ${expected_version}" "${tmp_dir}/output"
	grep -Fq -- "--wordpress=${expected_version}" "${tmp_dir}/vip-commands"
}

run_setup '' '6.9.9'
assert_selected_version '6.9.9'

run_setup '6.8' '6.9.9'
assert_selected_version '6.8.4'
grep -Fq -- '--arg ref_value 6.8' "${tmp_dir}/jq-args"

run_setup '6.8' '6.9.9' -v '6.7'
assert_selected_version '6.7.4'
grep -Fq -- '--arg ref_value 6.7' "${tmp_dir}/jq-args"

printf 'WordPress version selection passed: latest fallback, workflow input, and explicit -v override.\n'
