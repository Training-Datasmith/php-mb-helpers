#!/usr/bin/env bash
set -euo pipefail

php -r 'echo PHP_VERSION, PHP_EOL;' | tee /tmp/php-floor-version.txt
if ! grep -E '^5\.3\.29$' /tmp/php-floor-version.txt >/dev/null; then
    echo 'php-lint: unexpected PHP floor version' >&2
    exit 1
fi
echo 'php-lint: 5.3.29'

for file in src/mb_helpers.php examples/basic_usage.php; do
    lint_out="$(mktemp)"
    set +e
    php -l "${file}" >"${lint_out}" 2>&1
    lint_status=$?
    set -e
    cat "${lint_out}"
    if [[ ${lint_status} -ne 0 ]]; then
        rm -f "${lint_out}"
        exit "${lint_status}"
    fi
    if ! grep -Fx "No syntax errors detected in ${file}" "${lint_out}" >/dev/null; then
        echo "php-lint: missing success line for ${file}" >&2
        rm -f "${lint_out}"
        exit 1
    fi
    rm -f "${lint_out}"
done

echo 'system-test-floor: all steps passed.'
