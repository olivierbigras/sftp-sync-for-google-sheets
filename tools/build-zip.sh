#!/usr/bin/env bash
# Build a distributable plugin ZIP that includes vendor/ (phpseclib) and languages/.
# Uses the versioned composer.lock via composer install --no-dev --optimize-autoloader.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_SLUG="sftp-sync-for-google-sheets"
DIST_DIR="${ROOT}/dist"
STAGE="$(mktemp -d)"
PLUGIN_DIR="${STAGE}/${PLUGIN_SLUG}"
DISTIGNORE="${ROOT}/.distignore"

cleanup() {
	rm -rf "${STAGE}"
}
trap cleanup EXIT

if ! command -v composer >/dev/null 2>&1; then
	echo "composer is required" >&2
	exit 1
fi

if ! command -v zip >/dev/null 2>&1; then
	echo "zip is required" >&2
	exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
	echo "rsync is required" >&2
	exit 1
fi

if [[ ! -f "${ROOT}/composer.lock" ]]; then
	echo "composer.lock is required (commit it, then rebuild)" >&2
	exit 1
fi

mkdir -p "${PLUGIN_DIR}" "${DIST_DIR}"

RSYNC_EXCLUDES=()
if [[ -f "${DISTIGNORE}" ]]; then
	while IFS= read -r line || [[ -n "${line}" ]]; do
		line="${line%%#*}"
		line="$(echo "${line}" | sed 's/[[:space:]]*$//')"
		[[ -z "${line}" ]] && continue
		RSYNC_EXCLUDES+=(--exclude="${line}")
	done < "${DISTIGNORE}"
fi

# Never drop translations or the Composer runtime tree from the ZIP.
rsync -a \
	--exclude=".git/" \
	--exclude="dist/" \
	"${RSYNC_EXCLUDES[@]}" \
	"${ROOT}/" "${PLUGIN_DIR}/"

# composer.json/lock are listed in .distignore; copy them only long enough to install.
cp "${ROOT}/composer.json" "${PLUGIN_DIR}/composer.json"
cp "${ROOT}/composer.lock" "${PLUGIN_DIR}/composer.lock"

(
	cd "${PLUGIN_DIR}"
	composer install --no-dev --optimize-autoloader --no-interaction
	rm -f composer.lock
	find vendor -name ".git" -type d -prune -exec rm -rf {} +
	find vendor -name ".github" -type d -prune -exec rm -rf {} +
)

if [[ ! -f "${PLUGIN_DIR}/vendor/autoload.php" ]]; then
	echo "vendor/autoload.php missing after composer install" >&2
	exit 1
fi

if [[ ! -d "${PLUGIN_DIR}/languages" ]]; then
	echo "languages/ missing from staging directory" >&2
	exit 1
fi

ZIP_PATH="${DIST_DIR}/${PLUGIN_SLUG}.zip"
rm -f "${ZIP_PATH}"
(
	cd "${STAGE}"
	zip -r -q "${ZIP_PATH}" "${PLUGIN_SLUG}"
)

echo "Wrote ${ZIP_PATH}"
unzip -l "${ZIP_PATH}"
