#!/usr/bin/env bash

set -euo pipefail

if [ "$#" -ne 1 ]; then
	echo "usage: $0 <jetpack-version>" >&2
	exit 1
fi

new_version="${1#v}"

if ! [[ "${new_version}" =~ ^[0-9]+(\.[0-9]+){1,2}$ ]]; then
	echo "Jetpack version '${new_version}' is not a stable release tag." >&2
	exit 1
fi

if ! git -C jetpack rev-parse -q --verify "refs/tags/${new_version}" >/dev/null; then
	git -C jetpack fetch --depth=1 origin "refs/tags/${new_version}:refs/tags/${new_version}"
fi

git -C jetpack checkout --detach "${new_version}"

requires_at_least="$(awk -F': ' '/^ \* Requires at least: / { print $2; exit }' jetpack/jetpack.php)"
requires_php="$(awk -F': ' '/^ \* Requires PHP: / { print $2; exit }' jetpack/jetpack.php)"
jetpack_sha="$(git -C jetpack rev-parse HEAD)"

if [ -z "${requires_at_least}" ] || [ -z "${requires_php}" ]; then
	echo "Could not read Jetpack header requirements from jetpack/jetpack.php." >&2
	exit 1
fi

export NEW_VERSION="${new_version}"
export REQUIRES_AT_LEAST="${requires_at_least}"
export REQUIRES_PHP="${requires_php}"

perl -0pi -e '
	s/^ \* Version: .*/ * Version: $ENV{NEW_VERSION}/m;
	s/^ \* Requires at least: .*/ * Requires at least: $ENV{REQUIRES_AT_LEAST}/m;
	s/^ \* Requires PHP: .*/ * Requires PHP: $ENV{REQUIRES_PHP}/m;
' jetpack.php

perl <<'PERL'
use strict;
use warnings;

my $new_version       = $ENV{NEW_VERSION};
my $requires_at_least = $ENV{REQUIRES_AT_LEAST};

sub version_minor {
	my ( $version ) = @_;

	die "Could not read a major.minor version from '${version}'.\n"
		unless $version =~ /^([0-9]+\.[0-9]+)/;

	return $1;
}

sub compare_versions {
	my ( $left, $right ) = @_;
	my @left_parts  = split /\./, $left;
	my @right_parts = split /\./, $right;
	my $length      = @left_parts > @right_parts ? @left_parts : @right_parts;

	for my $index ( 0 .. $length - 1 ) {
		my $left_part  = $left_parts[$index]  // 0;
		my $right_part = $right_parts[$index] // 0;

		return -1 if $left_part < $right_part;
		return 1  if $left_part > $right_part;
	}

	return 0;
}

sub next_minor {
	my ( $version ) = @_;
	my ( $major, $minor ) = split /\./, $version;

	return $major . '.' . ( $minor + 1 );
}

sub read_file {
	my ( $path ) = @_;

	open my $handle, '<', $path or die "Could not read ${path}: $!\n";
	local $/;
	my $content = <$handle>;
	close $handle;

	return $content;
}

sub write_file {
	my ( $path, $content ) = @_;

	open my $handle, '>', $path or die "Could not write ${path}: $!\n";
	print {$handle} $content;
	close $handle;
}

my $minimum_wordpress = version_minor( $requires_at_least );
my $jetpack_php       = read_file( 'jetpack.php' );

my ( $latest_wordpress, $previous_latest_version ) = $jetpack_php =~ m{\t\} else \{\n\t\t// WordPress ([0-9]+\.[0-9]+) and newer\.\n\t\treturn '([0-9]+(?:\.[0-9]+){1,2})';\n\t\}}
	or die "Could not read latest WordPress branch from vip_default_jetpack_version().\n";

if ( compare_versions( $minimum_wordpress, $latest_wordpress ) > 0 ) {
	my $previous_comment = next_minor( $latest_wordpress ) eq $minimum_wordpress
		? "WordPress ${latest_wordpress}.x"
		: "WordPress ${latest_wordpress} and newer, before ${minimum_wordpress}.";

	my $replacement = sprintf(
		"\t} elseif ( version_compare( \$wp_version, '%s', '<' ) ) {\n\t\t// %s\n\t\treturn '%s';\n\t} else {\n\t\t// WordPress %s and newer.\n\t\treturn '%s';\n\t}",
		$minimum_wordpress,
		$previous_comment,
		$previous_latest_version,
		$minimum_wordpress,
		$new_version
	);

	$jetpack_php =~ s{\t\} else \{\n\t\t// WordPress \Q$latest_wordpress\E and newer\.\n\t\treturn '\Q$previous_latest_version\E';\n\t\}}{$replacement}
		or die "Could not split latest Jetpack compatibility branch.\n";
} else {
	$jetpack_php =~ s{(// WordPress [^\n]+ and newer\.\n\s*return ')[0-9]+(?:\.[0-9]+){1,2}(';)}{$1$new_version$2}s
		or die "Could not update latest Jetpack compatibility branch.\n";
}

write_file( 'jetpack.php', $jetpack_php );

my $test_php = read_file( 'tests/test-jetpack.php' );
$test_php =~ s/(\$latest = ')[0-9]+(?:\.[0-9]+){1,2}(';)/$1$new_version$2/s
	or die "Could not update latest Jetpack test version.\n";

if ( compare_versions( $minimum_wordpress, $latest_wordpress ) > 0 ) {
	$test_php =~ s{(\n\t\t\t'\Q$latest_wordpress\E' => )\$latest(,\n)}{$1'$previous_latest_version'$2}
		or die "Could not pin previous latest Jetpack test row.\n";

	if ( $test_php =~ m{^\t\t\t'\Q$minimum_wordpress\E' => }m ) {
		$test_php =~ s{(^\t\t\t'\Q$minimum_wordpress\E' => ).*(,\n)}{$1\$latest$2}m
			or die "Could not update new latest Jetpack test row.\n";
	} else {
		$test_php =~ s{(\n\t\t\t'\Q$latest_wordpress\E' => '\Q$previous_latest_version\E',\n)}{$1\t\t\t'$minimum_wordpress' => \$latest,\n}
			or die "Could not add new latest Jetpack test row.\n";
	}
}

write_file( 'tests/test-jetpack.php', $test_php );
PERL

grep -Fq " * Version: ${new_version}" jetpack.php
grep -Fq " * Requires at least: ${requires_at_least}" jetpack.php
grep -Fq " * Requires PHP: ${requires_php}" jetpack.php
grep -Fq "return '${new_version}';" jetpack.php
grep -Fq "\$latest = '${new_version}';" tests/test-jetpack.php

if [ -n "${GITHUB_OUTPUT:-}" ]; then
	{
		echo "jetpack_sha=${jetpack_sha}"
		echo "requires_at_least=${requires_at_least}"
		echo "requires_php=${requires_php}"
	} >> "${GITHUB_OUTPUT}"
else
	echo "Updated Jetpack to ${new_version} (${jetpack_sha})."
	echo "Requires at least: ${requires_at_least}"
	echo "Requires PHP: ${requires_php}"
fi
