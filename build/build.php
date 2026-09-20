<?php

/**
 * Assembles the installable component package.
 *
 *   php build/build.php                          -> build/com_metagen-<version>.zip
 *   php build/build.php --library=path/to.zip    use a locally built library
 *   php build/build.php --no-library             leave it out, deliberately
 *
 * The repository already mirrors the installed layout, so most of this is
 * copying `src/` and leaving out what is a product rather than a source.
 *
 * The one thing it adds is the shared library. Joomla has no way for a package
 * manifest to declare a dependency on another extension, so the package carries
 * a copy of `lib_yepr_gen` and `script.php` installs it when the site has none
 * or has an older one. The library is fetched from its release rather than
 * rebuilt here, so that what ships is the artefact that was released and
 * verified, not one assembled on the way past.
 */

declare(strict_types=1);

$root     = \dirname(__DIR__);
$manifest = $root . '/src/metagen.xml';

$xml = simplexml_load_file($manifest);

if ($xml === false) {
    fwrite(STDERR, "Cannot read the manifest at {$manifest}.\n");
    exit(1);
}

$version = trim((string) $xml->version);
$element = trim((string) $xml->name);

if ($version === '' || $element === '') {
    fwrite(STDERR, "The manifest needs both a version and a name.\n");
    exit(1);
}

// The version the install script insists on, so the build cannot ship a library
// older than the component will accept.
$required = libraryMinimum($root . '/src/script.php');

$options = getopt('', ['library::', 'no-library']);
$zipPath = $root . '/build/' . $element . '-' . $version . '.zip';

// Products, not sources: the generators this has generated, and the Twig
// compilation cache. Both live inside the component because that is the one
// directory a Joomla install is certain to let it write to, which means both
// would otherwise be packaged and shipped to somebody else.
$skip = [
    'administrator/components/com_metagen/generated/',
    'administrator/components/com_metagen/compilation_cache/',
    'node_modules/',
];

echo "Building {$element} {$version}\n";

$library = null;

if (!isset($options['no-library'])) {
    $library = resolveLibrary($root, \is_string($options['library'] ?? null) ? $options['library'] : null, $required);
}

if (is_file($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
    fwrite(STDERR, "Cannot create {$zipPath}.\n");
    exit(1);
}

$added   = 0;
$skipped = 0;

foreach (walk($root . '/src') as $absolute) {
    // Forward slashes always: a backslash in a zip entry becomes a literal
    // backslash in a filename on Linux, and the extension then fails to load.
    $entry = str_replace('\\', '/', substr($absolute, \strlen($root . '/src') + 1));

    foreach ($skip as $prefix) {
        if (str_starts_with($entry, $prefix) || str_contains($entry, '/' . $prefix)) {
            $skipped++;

            continue 2;
        }
    }

    $zip->addFile($absolute, $entry);
    $added++;
}

if ($library !== null) {
    // script.php looks for it under library/ in the package it is installing from.
    $zip->addFile($library, 'library/' . basename($library));
    $added++;

    echo '  library/                 ' . basename($library) . "\n";
}

$zip->close();

printf(
    "\n%s\n  %d files, %s (%d skipped as build products)\n",
    basename($zipPath),
    $added,
    formatSize((int) filesize($zipPath)),
    $skipped
);

if ($library === null) {
    echo "\n  No library bundled. A site without lib_yepr_gen will install this\n"
        . "  component and then be unable to generate.\n";
}

/**
 * The library version script.php refuses to go below.
 */
function libraryMinimum(string $script): string
{
    $source = (string) file_get_contents($script);

    preg_match("/LIBRARY_MINIMUM\s*=\s*'([^']+)'/", $source, $match);

    if (!isset($match[1])) {
        fwrite(STDERR, "script.php does not say which library version it needs.\n");
        exit(1);
    }

    return $match[1];
}

/**
 * The version in a library package's file name, or null when it has none.
 */
function libraryVersion(string $path): ?string
{
    return preg_match('/lib_yepr_gen-(\d+\.\d+\.\d+)\.zip$/', basename($path), $match) === 1
        ? $match[1]
        : null;
}

/**
 * Find the library package to bundle: the one given, the newest built locally,
 * or the released one.
 */
function resolveLibrary(string $root, ?string $given, string $required): string
{
    if ($given !== null && $given !== '') {
        if (!is_file($given)) {
            fwrite(STDERR, "No library package at {$given}.\n");
            exit(1);
        }

        return $given;
    }

    // A sibling checkout that has been built is the usual case while working on
    // both at once, and it is what should be shipped then - but only if it is
    // new enough. This used to take whichever local build sorted last, so
    // bumping LIBRARY_MINIMUM and rebuilding produced a package carrying a
    // library its own install script refuses: the zip installs, the library
    // does not, and the component lands on a site unable to generate.
    $local = [];

    foreach (glob($root . '/../generator-core/build/lib_yepr_gen-*.zip') ?: [] as $candidate) {
        $version = libraryVersion($candidate);

        if ($version !== null && version_compare($version, $required, '>=')) {
            $local[$version] = $candidate;
        }
    }

    if ($local !== []) {
        uksort($local, static fn (string $x, string $y): int => version_compare($x, $y));

        $newest = (string) end($local);

        echo '  using the locally built library: ' . basename($newest) . "\n";

        return $newest;
    }

    $url = 'https://github.com/HermanPeeren/generator-core/releases/download/v'
        . $required . '/lib_yepr_gen-' . $required . '.zip';
    $into = $root . '/build/tmp/lib_yepr_gen-' . $required . '.zip';

    if (!is_dir(\dirname($into)) && !mkdir(\dirname($into), 0755, true) && !is_dir(\dirname($into))) {
        fwrite(STDERR, 'Cannot create ' . \dirname($into) . "\n");
        exit(1);
    }

    echo '  fetching the released library ' . $required . "\n";

    $contents = @file_get_contents($url);

    if ($contents === false) {
        fwrite(STDERR, "Could not fetch {$url}.\nBuild the library locally, or pass --library=, or --no-library.\n");
        exit(1);
    }

    file_put_contents($into, $contents);

    return $into;
}

/** @return iterable<string> Every file under a directory, in a stable order. */
function walk(string $directory): iterable
{
    if (!is_dir($directory)) {
        return [];
    }

    $files    = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    sort($files, SORT_STRING);

    return $files;
}

function formatSize(int $bytes): string
{
    return $bytes > 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : number_format($bytes / 1024, 1) . ' KB';
}
