#!/usr/bin/env bash
#
# Generate the PHP built-in symbol database.
#
# Runs collect mode inside Docker containers for each PHP version, then
# merges all results into the final data file shipped with Mozart.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${PROJECT_DIR}/src/PhpSymbols/data"
TMP_DIR="$(mktemp -d)"

trap 'rm -rf "${TMP_DIR}"' EXIT

# PHP 7.2 and 7.3 are excluded: their Docker Hub images are no longer available,
# and their built-in symbols are a subset of 7.4 (no symbols were removed).
PHP_VERSIONS=(7.4 8.0 8.1 8.2 8.3 8.4 8.5)

echo "Collecting symbols from PHP versions..."

for version in "${PHP_VERSIONS[@]}"; do
    echo "  PHP ${version}..."
    image="php:${version}-cli-alpine"

    if ! docker run --rm \
        -v "${SCRIPT_DIR}/generate-php-symbols.php:/generate-php-symbols.php:ro" \
        "${image}" \
        php /generate-php-symbols.php --version="${version}" \
        > "${TMP_DIR}/${version}.json" 2>/dev/null; then
        echo "    WARNING: PHP ${version} image not available, skipping."
        rm -f "${TMP_DIR}/${version}.json"
    fi
done

echo ""
echo "Merging symbols..."

mkdir -p "${OUTPUT_DIR}"

docker run --rm \
    -v "${SCRIPT_DIR}/generate-php-symbols.php:/generate-php-symbols.php:ro" \
    -v "${TMP_DIR}:/tmp/symbols:ro" \
    php:8.4-cli-alpine \
    php /generate-php-symbols.php --merge /tmp/symbols > "${OUTPUT_DIR}/php-symbols.php"

echo "Generated ${OUTPUT_DIR}/php-symbols.php"
echo "Done."
