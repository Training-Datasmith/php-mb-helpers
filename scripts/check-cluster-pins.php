<?php

/**
 * Verify cluster package SHAs and aliases; guard GAP org packages stay off the index.
 */

$root = dirname(__DIR__);
$installedPhpPath = $root . '/vendor/composer/installed.php';
$installedJsonPath = $root . '/vendor/composer/installed.json';

$org_phpunit_fork_sha = 'a37d66fabf01af0eedabffe271b70dbe71723001';

/** @var array<string, array{alias: string, reference: string}> $expected */
$expected = array();

$demo_ok = run_wrong_sha_demos();
if (!$demo_ok) {
    exit(1);
}

if (!is_readable($installedPhpPath)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

if (!is_readable($installedJsonPath)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
    exit(1);
}

/** @var array{versions: array<string, array{reference?: string, aliases?: list<string>}>} $installedPhp */
$installedPhp = require $installedPhpPath;
$versionsPhp = isset($installedPhp['versions']) ? $installedPhp['versions'] : array();

$jsonRaw = file_get_contents($installedJsonPath);
if ($jsonRaw === false) {
    fwrite(STDERR, "check-cluster-pins: could not read installed.json\n");
    exit(1);
}

/** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $jsonData */
$jsonData = json_decode($jsonRaw, true);
if (!is_array($jsonData)) {
    fwrite(STDERR, "check-cluster-pins: invalid installed.json\n");
    exit(1);
}

$versionsJson = build_json_references($jsonData);

$failed = false;

foreach ($expected as $name => $want) {
    if (!assert_package_php($name, $want, $versionsPhp)) {
        $failed = true;
    }
    if (!assert_package_json($name, $want['reference'], $versionsJson)) {
        $failed = true;
    }
}

if (!assert_no_org_packages($jsonData, $versionsPhp, $org_phpunit_fork_sha)) {
    $failed = true;
}

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: 0 org packages pinned; GAP phpunit/phpunit left on Packagist; wrong-SHA demos failed closed on installed.php and installed.json.\n");
exit(0);

/**
 * @param array<string, array{alias: string, reference: string}> $expected
 * @param array<string, array{reference?: string, aliases?: list<string>}> $versionsPhp
 */
function assert_package_php($name, $want, $versionsPhp)
{
    if (!package_php_matches($name, $want, $versionsPhp)) {
        if (!isset($versionsPhp[$name])) {
            fwrite(STDERR, "check-cluster-pins: {$name} is not installed (installed.php).\n");
            return false;
        }

        $pkg = $versionsPhp[$name];
        $aliases = isset($pkg['aliases']) ? $pkg['aliases'] : array();
        if (!in_array($want['alias'], $aliases, true)) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s alias mismatch (expected %s in aliases %s).\n",
                $name,
                $want['alias'],
                json_encode($aliases)
            ));
            return false;
        }

        $reference = isset($pkg['reference']) ? $pkg['reference'] : '';
        fwrite(STDERR, sprintf(
            "check-cluster-pins: %s reference mismatch on installed.php (expected %s, got %s).\n",
            $name,
            $want['reference'],
            $reference
        ));
        return false;
    }

    return true;
}

/**
 * @param array<string, array{reference?: string, aliases?: list<string>}> $versionsPhp
 * @param array{alias: string, reference: string}                             $want
 */
function package_php_matches($name, $want, $versionsPhp)
{
    if (!isset($versionsPhp[$name])) {
        return false;
    }

    $pkg = $versionsPhp[$name];
    $aliases = isset($pkg['aliases']) ? $pkg['aliases'] : array();
    if (!in_array($want['alias'], $aliases, true)) {
        return false;
    }

    $reference = isset($pkg['reference']) ? $pkg['reference'] : null;
    if (!is_string($reference) || $reference === '') {
        return false;
    }

    return hash_equals(strtolower($want['reference']), strtolower($reference));
}

/**
 * @param array<string, string> $versionsJson
 */
function assert_package_json($name, $wantReference, $versionsJson)
{
    if (!package_json_matches($name, $wantReference, $versionsJson)) {
        if (!isset($versionsJson[$name])) {
            fwrite(STDERR, "check-cluster-pins: {$name} is not installed (installed.json).\n");
            return false;
        }

        fwrite(STDERR, sprintf(
            "check-cluster-pins: %s reference mismatch on installed.json (expected %s, got %s).\n",
            $name,
            $wantReference,
            $versionsJson[$name]
        ));
        return false;
    }

    return true;
}

/**
 * @param array<string, string> $versionsJson
 */
function package_json_matches($name, $wantReference, $versionsJson)
{
    if (!isset($versionsJson[$name])) {
        return false;
    }

    $reference = $versionsJson[$name];

    return hash_equals(strtolower($wantReference), strtolower($reference));
}

/**
 * @param array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $jsonData
 * @param array<string, array{reference?: string, aliases?: list<string>}>                          $versionsPhp
 */
function assert_no_org_packages($jsonData, $versionsPhp, $org_phpunit_fork_sha)
{
    $ok = true;
    $packages = array();
    if (isset($jsonData['packages']) && is_array($jsonData['packages'])) {
        $packages = array_merge($packages, $jsonData['packages']);
    }
    if (isset($jsonData['packages-dev']) && is_array($jsonData['packages-dev'])) {
        $packages = array_merge($packages, $jsonData['packages-dev']);
    }

    foreach ($packages as $package) {
        if (!isset($package['name'])) {
            continue;
        }
        $name = (string) $package['name'];
        $source = isset($package['source']) && is_array($package['source']) ? $package['source'] : array();
        $dist = isset($package['dist']) && is_array($package['dist']) ? $package['dist'] : array();
        $sourceUrl = isset($source['url']) ? (string) $source['url'] : '';
        $distUrl = isset($dist['url']) ? (string) $dist['url'] : '';

        if (strpos($sourceUrl, 'github.com/Training-Datasmith/') !== false) {
            fwrite(STDERR, "check-cluster-pins: unexpected Training-Datasmith source for {$name}.\n");
            $ok = false;
        }
        if (strpos($distUrl, 'github.com/Training-Datasmith/') !== false) {
            fwrite(STDERR, "check-cluster-pins: unexpected Training-Datasmith dist for {$name}.\n");
            $ok = false;
        }
    }

    if (isset($versionsPhp['phpunit/phpunit'])) {
        $ref = isset($versionsPhp['phpunit/phpunit']['reference'])
            ? strtolower((string) $versionsPhp['phpunit/phpunit']['reference'])
            : '';
        if ($ref === strtolower($org_phpunit_fork_sha)) {
            fwrite(STDERR, "check-cluster-pins: phpunit/phpunit must not be the org fork SHA.\n");
            $ok = false;
        }
    }

    return $ok;
}

/**
 * @param array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>} $jsonData
 * @return array<string, string>
 */
function build_json_references($jsonData)
{
    $packages = array();
    if (isset($jsonData['packages']) && is_array($jsonData['packages'])) {
        $packages = array_merge($packages, $jsonData['packages']);
    }
    if (isset($jsonData['packages-dev']) && is_array($jsonData['packages-dev'])) {
        $packages = array_merge($packages, $jsonData['packages-dev']);
    }

    $refs = array();
    foreach ($packages as $package) {
        if (!isset($package['name'])) {
            continue;
        }
        $source = isset($package['source']) ? $package['source'] : null;
        if (!is_array($source) || !isset($source['reference'])) {
            continue;
        }
        $refs[$package['name']] = strtolower((string) $source['reference']);
    }

    return $refs;
}

function run_wrong_sha_demos()
{
    $expectedRef = str_repeat('a', 40);
    $wrongRef = str_repeat('b', 40);

    $installedPhp = array(
        'versions' => array(
            'demo/pkg' => array(
                'reference' => $expectedRef,
                'aliases' => array('1.2.3'),
            ),
        ),
    );

    $installedJson = array(
        'packages-dev' => array(
            array(
                'name' => 'demo/pkg',
                'source' => array(
                    'type' => 'git',
                    'reference' => $expectedRef,
                ),
            ),
        ),
    );

    $versionsJson = build_json_references($installedJson);
    $want = array('alias' => '1.2.3', 'reference' => $expectedRef);

    if (!package_php_matches('demo/pkg', $want, $installedPhp['versions'])) {
        fwrite(STDERR, "check-cluster-pins: demo baseline installed.php failed\n");
        return false;
    }
    if (!package_json_matches('demo/pkg', $expectedRef, $versionsJson)) {
        fwrite(STDERR, "check-cluster-pins: demo baseline installed.json failed\n");
        return false;
    }

    $phpWrong = $installedPhp;
    $phpWrong['versions']['demo/pkg']['reference'] = $wrongRef;

    $jsonWrongData = $installedJson;
    $jsonWrongData['packages-dev'][0]['source']['reference'] = $wrongRef;
    $versionsJsonWrong = build_json_references($jsonWrongData);

    $phpFailed = !package_php_matches('demo/pkg', $want, $phpWrong['versions']);
    $jsonStillOk = package_json_matches('demo/pkg', $expectedRef, $versionsJson);

    if (!$phpFailed || !$jsonStillOk) {
        fwrite(STDERR, "check-cluster-pins: installed.php wrong-SHA demo did not fail only on PHP side\n");
        return false;
    }

    $phpOk = package_php_matches('demo/pkg', $want, $installedPhp['versions']);
    $jsonFailed = !package_json_matches('demo/pkg', $expectedRef, $versionsJsonWrong);

    if (!$phpOk || !$jsonFailed) {
        fwrite(STDERR, "check-cluster-pins: installed.json wrong-SHA demo did not fail only on JSON side\n");
        return false;
    }

    return true;
}
