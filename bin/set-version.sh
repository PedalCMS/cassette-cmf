#!/usr/bin/env bash

# Writes a single version number into every version-bearing published file in
# the library: the README badge, package.json, and composer.json. Cassette-CMF
# is a Composer library rather than a standalone plugin, so there is no plugin
# header or runtime version constant to update — but Abstract_Handler::get_version()
# already reads composer.json's "version" key at runtime for asset
# cache-busting, falling back to a file-mtime timestamp when it's unset. This
# keeps that field populated with the real release version. Per-file
# @version/@since docblocks are intentionally left untouched.
#
# Usage: bin/set-version.sh <version>
#   <version> may be given as "0.4.0" or "v0.4.0"; a single leading "v" is
#   stripped and the normalized "0.4.0" form is written everywhere.

set -euo pipefail

if [[ $# -ne 1 ]]; then
	echo "Usage: $0 <version>" >&2
	exit 2
fi

# Normalize: strip a single leading "v" (v0.4.0 -> 0.4.0).
VERSION="${1#v}"

if [[ ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([-.][0-9A-Za-z.]+)?$ ]]; then
	echo "Invalid version '${1}'. Expected X.Y.Z (optionally with a suffix), e.g. 0.4.0 or v0.4.0." >&2
	exit 1
fi

# Resolve the repository root from this script's location so it works from anywhere.
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "${REPO_ROOT}"

TAB=$'\t'

# apply <file> <sed-expression> <verify-grep-pattern>
# Runs the substitution, then fails loudly if the expected value is not present
# afterwards (guards against a pattern that silently matched nothing).
apply() {
	local file="$1" expr="$2" verify="$3"
	[[ -f "${file}" ]] || { echo "Expected file '${file}' not found." >&2; exit 1; }
	sed -i -E "${expr}" "${file}"
	if ! grep -qE "${verify}" "${file}"; then
		echo "Failed to set version in ${file} (pattern did not match)." >&2
		exit 1
	fi
	echo "  ${file} -> ${VERSION}"
}

echo "Setting version to ${VERSION}"

# README badge:  badge/version-0.0.2-blue.svg
# (".*" is safe here, not "[^-]+": a pre-release suffix like "1.0.0-beta.1"
# contains a hyphen of its own, and there is only one "-blue.svg" per line.)
apply "README.md" \
	"s#(badge/version-).*(-blue\\.svg)#\\1${VERSION}\\2#" \
	"badge/version-${VERSION}-blue\\.svg"

# npm manifest:  "version": "0.4.0"  (first, top-level occurrence only)
apply "package.json" \
	"0,/\"version\":/ s/(\"version\":[[:space:]]*\")[^\"]*(\")/\\1${VERSION}\\2/" \
	"\"version\":[[:space:]]*\"${VERSION}\""

# package-lock.json (lockfile v2/v3): the top-level "version" and the root
# package entry packages[""].version. Skipped when there is no lock file.
if [[ -f package-lock.json ]]; then
	apply "package-lock.json" \
		"0,/\"version\":/ s/(\"version\":[[:space:]]*\")[^\"]*(\")/\\1${VERSION}\\2/" \
		"\"version\":[[:space:]]*\"${VERSION}\""
	sed -i -E "/^[[:space:]]*\"\": \{/,/\"version\":/ s/(\"version\":[[:space:]]*\")[^\"]*(\")/\\1${VERSION}\\2/" package-lock.json
	if [[ "$(grep -cE "^[[:space:]]+\"version\":[[:space:]]*\"${VERSION//./\\.}\",?\$" package-lock.json)" -lt 2 ]]; then
		echo "Failed to set packages[\"\"].version in package-lock.json." >&2
		exit 1
	fi
fi

# composer.json:  "version": "0.4.0"
# No "version" key exists by default for a Composer library (Composer infers
# it from VCS tags), but get_version() reads it explicitly, so add it if
# missing rather than only updating an existing value.
if grep -qE "^${TAB}\"version\":" composer.json; then
	apply "composer.json" \
		"s/(\"version\":[[:space:]]*\")[^\"]*(\")/\\1${VERSION}\\2/" \
		"\"version\":[[:space:]]*\"${VERSION}\""
else
	sed -i "/^${TAB}\"description\":/a\\${TAB}\"version\": \"${VERSION}\"," composer.json
	if ! grep -qE "\"version\":[[:space:]]*\"${VERSION}\"" composer.json; then
		echo "Failed to add version to composer.json (description line not found)." >&2
		exit 1
	fi
	echo "  composer.json -> ${VERSION} (added)"
fi

echo "Done."
