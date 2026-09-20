<?php

/**
 * Install the package this repository just built into a local Joomla.
 *
 *   php tools/install-local.php                 # into ./joomla
 *   php tools/install-local.php ../some/site
 *
 * Through Joomla's own CLI, which wants no login and no browser, so the
 * build-install-look loop is one command rather than a trip through the
 * administrator's upload form.
 *
 * The default is this repository's own site, because there is one
 * of those and it is where Exten-gen is installed - which matters here more
 * than usual: Meta-gen with no target installed shows an empty list and says so,
 * which is correct and not very interesting to look at.
 *
 * It finds the package by reading the version out of the manifest rather than
 * being told, because a hard-coded file name keeps working until the version
 * changes and then installs the wrong build without saying so.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1] ?? $root . '/joomla';

$xml = simplexml_load_file($root . '/src/metagen.xml');

if ($xml === false) {
    fwrite(STDERR, "Cannot read the manifest.\n");
    exit(1);
}

$package = \sprintf('%s/build/%s-%s.zip', $root, trim((string) $xml->name), trim((string) $xml->version));

if (!is_file($package)) {
    fwrite(STDERR, "No package at {$package}. Run `composer build` first.\n");
    exit(1);
}

$cli = $site . '/cli/joomla.php';

if (!is_file($cli)) {
    fwrite(STDERR, "No Joomla CLI at {$cli}.\n");
    exit(1);
}

passthru(
    \sprintf('php %s extension:install --path=%s', escapeshellarg($cli), escapeshellarg($package)),
    $status
);

exit($status);
