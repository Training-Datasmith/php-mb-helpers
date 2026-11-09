<?php

/**
 * Enforce repo-owned snake_case naming and baseline test method presence.
 */

$root = dirname(__DIR__);

$baseline_tests = array(
    'test_mb_ucwords',
    'test_mb_ucfirst',
    'test_mb_strrev',
    'test_mb_str_pad',
    'test_mb_count_chars',
    'test_mb_count_chars_unsupported_mode',
    'test_mb_str_split',
);

$legacy_names = array(
    'firstLetter',
    'HelpersTest',
    'mb_helpersTest.php',
);

$scan_paths = array(
    $root . '/src',
    $root . '/tests',
    $root . '/examples',
    $root . '/README.md',
    $root . '/ARCHITECTURE.md',
);

$allowlist_path = __DIR__ . '/rename-allowlist.txt';
$allowed_sites = load_allowlist($allowlist_path);

if (!run_allowlist_demo($allowed_sites)) {
    exit(1);
}

$failures = array();

foreach ($baseline_tests as $method) {
    if (!test_method_present($root . '/tests', $method)) {
        $failures[] = "Missing baseline test method: {$method}";
    }
}

foreach ($scan_paths as $path) {
    if (is_file($path)) {
        scan_file($path, $root, $allowed_sites, $legacy_names, $failures);
        continue;
    }
    if (!is_dir($path)) {
        continue;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $ext = strtolower($fileInfo->getExtension());
        if ($ext !== 'php' && $ext !== 'md') {
            continue;
        }
        scan_file($fileInfo->getPathname(), $root, $allowed_sites, $legacy_names, $failures);
    }
}

if (count($failures) > 0) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "check-rename: {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-rename: baseline 7 tests present; no legacy or camelCase repo-owned names remain.\n");
exit(0);

/**
 * @return array<string, true>
 */
function load_allowlist($path)
{
    $allowed = array();
    if (!is_readable($path)) {
        fwrite(STDERR, "check-rename: missing allowlist {$path}\n");
        exit(1);
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        fwrite(STDERR, "check-rename: could not read allowlist\n");
        exit(1);
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $allowed[$line] = true;
    }

    return $allowed;
}

/**
 * Demo: allowlisted upstream token must fail when not at an allowlisted site.
 *
 * @param array<string, true> $allowed_sites
 */
function run_allowlist_demo($allowed_sites)
{
    $sample = <<<'PHP'
<?php
class Helpers_Test extends PHPUnit\Framework\TestCase
{
    public function test_demo()
    {
        assertEquals(1, 1);
    }
}
PHP;

    $failures = array();
    scan_source($sample, 'demo.php', $allowed_sites, array(), $failures, true);

    if (count($failures) === 0) {
        fwrite(STDERR, "check-rename: allowlist demo should have rejected bare assertEquals\n");
        return false;
    }

    foreach ($failures as $failure) {
        if (strpos($failure, 'assertEquals') !== false) {
            return true;
        }
    }

    fwrite(STDERR, "check-rename: allowlist demo did not mention assertEquals\n");
    return false;
}

function test_method_present($tests_dir, $method)
{
    if (!is_dir($tests_dir)) {
        return false;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tests_dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'php') {
            continue;
        }
        $contents = file_get_contents($fileInfo->getPathname());
        if ($contents === false) {
            continue;
        }
        if (preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(/', $contents)) {
            return true;
        }
    }

    return false;
}

/**
 * @param array<string, true> $allowed_sites
 * @param list<string>        $legacy_names
 * @param list<string>        $failures
 */
function scan_file($file, $root, $allowed_sites, $legacy_names, &$failures)
{
    $contents = file_get_contents($file);
    if ($contents === false) {
        $failures[] = "Could not read {$file}";
        return;
    }

    $relative = relative_path($file, $root);

    foreach ($legacy_names as $legacy) {
        if (strpos($contents, $legacy) !== false) {
            $failures[] = "Legacy name '{$legacy}' in {$relative}";
        }
    }

    if (substr($relative, -3) === '.md') {
        return;
    }

    scan_source($contents, $relative, $allowed_sites, $legacy_names, $failures, false);
}

/**
 * @param array<string, true> $allowed_sites
 * @param list<string>        $legacy_names
 * @param list<string>        $failures
 */
function scan_source($contents, $relative, $allowed_sites, $legacy_names, &$failures, $demo_only)
{
    $tokens = token_get_all($contents);
    $class = null;
    $method = null;
    $parent_parts = array();
    $collect_parent = false;
    $count = count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];
        if (is_string($token)) {
            if ($collect_parent && $token === '{') {
                $collect_parent = false;
                $parent_parts = array();
            }
            continue;
        }

        if ($token[0] === T_CLASS) {
            $previous = previous_significant_token($tokens, $i);
            if (is_array($previous) && ($previous[0] === T_NS_SEPARATOR || $previous[0] === T_PAAMAYIM_NEKUDOTAYIM)) {
                continue;
            }
            $class = read_next_string_after($tokens, $i);
            $method = null;
            $collect_parent = false;
            $parent_parts = array();
            continue;
        }

        if ($token[0] === T_EXTENDS) {
            $collect_parent = true;
            $parent_parts = array();
            continue;
        }

        if ($collect_parent) {
            if ($token[0] === T_STRING) {
                $parent_parts[] = $token[1];
                continue;
            }
            if ($token[0] === T_NS_SEPARATOR) {
                continue;
            }
            if (count($parent_parts) > 0) {
                $parent = implode('\\', $parent_parts);
                if ($class !== null) {
                    $site = $class . '::<extends>::' . $parent;
                    if (!isset($allowed_sites[$site])) {
                        $failures[] = "Disallowed parent class reference {$parent} in {$relative}";
                    }
                }
                $collect_parent = false;
                $parent_parts = array();
            }
        }

        if ($token[0] === T_FUNCTION) {
            $name = read_next_string_after($tokens, $i);
            if ($name !== null && $name !== '__construct') {
                $method = $name;
            }
            continue;
        }

        if ($token[0] !== T_STRING) {
            continue;
        }

        $name = $token[1];
        if (!is_repo_owned_identifier($name)) {
            continue;
        }

        $site = build_site($class, $method, $name);
        if (!isset($allowed_sites[$site])) {
            $failures[] = "Disallowed repo-owned identifier {$name} at {$relative}" . ($method ? " in {$method}" : '');
        }
    }

    if ($demo_only && count($failures) === 0) {
        return;
    }
}

function build_site($class, $method, $name)
{
    if ($class === null) {
        return '::' . $name;
    }
    if ($method === null) {
        return $class . '::' . $name;
    }

    return $class . '::' . $method . '::' . $name;
}

function previous_significant_token($tokens, $index)
{
    for ($j = $index - 1; $j >= 0; $j--) {
        $token = $tokens[$j];
        if (is_array($token) && $token[0] === T_WHITESPACE) {
            continue;
        }

        return $token;
    }

    return null;
}

function read_next_string_after($tokens, $index)
{
    $count = count($tokens);
    for ($j = $index + 1; $j < $count; $j++) {
        $token = $tokens[$j];
        if (is_array($token) && $token[0] === T_WHITESPACE) {
            continue;
        }
        if (is_array($token) && $token[0] === T_STRING) {
            return $token[1];
        }
        break;
    }

    return null;
}

function is_repo_owned_identifier($name)
{
    if ($name === '') {
        return false;
    }

    if (preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
        return false;
    }

    if (is_snake_or_studly_snake($name)) {
        return false;
    }

    if (preg_match('/[a-z][A-Z]/', $name)) {
        return true;
    }

    return false;
}

function is_snake_or_studly_snake($name)
{
    $parts = explode('_', $name);
    foreach ($parts as $part) {
        if ($part === '') {
            return false;
        }
        if (preg_match('/^[a-z][a-z0-9]*$/', $part)) {
            continue;
        }
        if (preg_match('/^[A-Z][a-z0-9]*$/', $part)) {
            continue;
        }

        return false;
    }

    return true;
}

function relative_path($file, $root)
{
    $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
    $file = str_replace('\\', '/', $file);
    if (strpos($file, $root) === 0) {
        return substr($file, strlen($root));
    }

    return $file;
}
