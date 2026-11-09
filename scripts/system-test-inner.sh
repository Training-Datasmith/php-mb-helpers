#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1
git config --global --add safe.directory /app >/dev/null 2>&1 || true

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    if grep -E '^Deprecation Notice:' "${stripped}" >/dev/null; then
        echo "${label}: output contains Deprecation Notice lines" >&2
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

php -r 'echo PHP_VERSION, PHP_EOL;' | tee /tmp/php-version.txt
if ! grep -E '^7\.2\.34$' /tmp/php-version.txt >/dev/null; then
    echo 'php-test: unexpected PHP version' >&2
    exit 1
fi
echo 'php-test: 7.2.34'

ext_list='ctype dom filter json libxml mbstring phar simplexml tokenizer xml xmlwriter'
found_ext=''
for ext in ${ext_list}; do
    if php -m | grep -qi "^${ext}$"; then
        found_ext="${found_ext} ${ext}"
    else
        echo "Missing required PHP extension: ${ext}" >&2
        exit 1
    fi
done
found_ext="$(echo "${found_ext}" | sed 's/^ //')"
echo "php-ext: ${found_ext}"

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -

composer_version_out="$(mktemp)"
set +e
php /tmp/composer.phar --version --no-ansi >"${composer_version_out}" 2>&1
composer_version_status=$?
set -e
cat "${composer_version_out}"
fail_deprecation_output "composer version" "${composer_version_out}"
if [[ ${composer_version_status} -ne 0 ]]; then
    rm -f "${composer_version_out}"
    exit "${composer_version_status}"
fi
if ! grep -E '^Composer version 2\.8\.10 2025-07-10 19:08:33$' "${composer_version_out}" >/dev/null; then
    echo 'composer: missing exact version line' >&2
    rm -f "${composer_version_out}"
    exit 1
fi
rm -f "${composer_version_out}"

composer_out="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --prefer-dist --no-progress --no-ansi >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
rm -f "${composer_out}"
echo 'composer update: ok'

php scripts/check-cluster-pins.php

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit --no-interaction --no-ansi --abandoned=report >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    rm -f "${audit_out}"
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
    echo 'composer audit: missing exact success line' >&2
    rm -f "${audit_out}"
    exit 1
fi
rm -f "${audit_out}"

set +e
php scripts/check-rename.php
rename_status=$?
set -e
if [[ ${rename_status} -ne 0 ]]; then
    exit "${rename_status}"
fi

phpunit_out="$(mktemp)"
set +e
vendor/bin/phpunit --colors=never >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
cat "${phpunit_out}"
fail_deprecation_output "phpunit" "${phpunit_out}"
if [[ ${phpunit_status} -ne 0 ]]; then
    rm -f "${phpunit_out}"
    exit "${phpunit_status}"
fi
if ! grep -E '^OK \(7 tests, 37 assertions\)$' "${phpunit_out}" >/dev/null; then
    echo 'phpunit: missing strict OK success line' >&2
    rm -f "${phpunit_out}"
    exit 1
fi
rm -f "${phpunit_out}"

echo 'system-test: all steps passed.'
