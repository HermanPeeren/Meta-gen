<?php

/**
 * Writes a metalanguage package from a stored concept model.
 *
 *   php tools/export-language.php tests/Fixtures/languages/er1-full.json
 *   php tools/export-language.php path/to/model.json --out=../Exten-gen/packages
 *
 * The screen does this, and until now only the screen did: exporting a package
 * meant opening a browser, signing in, and clicking. That is fine for somebody
 * using the component and useless for the one case that keeps recurring -
 * Exten-gen ships ER1 as a package, ER1 is a model in this repository, and a
 * change to the model has to reach that package somehow.
 *
 * 4.2 is the second time that has come up. The first, 3.5, built the package by
 * hand through the screen and recorded the result, which means the plan says
 * what the package contains and nothing says how to make it again.
 *
 * It runs the same target the screen runs, through the same pipeline, so what
 * comes out is what the screen would have produced. The order matters and the
 * target owns it: the manifest hashes what is already in the collection, so
 * running it first would produce a manifest describing nothing.
 */

declare(strict_types=1);

require_once \dirname(__DIR__) . '/tests/bootstrap.php';

use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Component\Metagen\Administrator\Generator\Target\MetaFormsTarget;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Target\Target;

// Parsed by hand rather than with getopt(), which stops at the first argument
// that is not an option - so `export-language.php model.json --out=x` silently
// ignored the --out and wrote to build/ instead, which is the kind of thing you
// only notice by looking for the file where you asked for it.
$arguments = [];
$options   = [];

foreach (\array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--')) {
        [$name, $value]  = array_pad(explode('=', substr($argument, 2), 2), 2, '');
        $options[$name]  = $value;

        continue;
    }

    $arguments[] = $argument;
}

if ($arguments === []) {
    fwrite(STDERR, "Usage: php tools/export-language.php <model.json> [--out=<directory or file>]\n");
    exit(1);
}

$source = $arguments[0];

if (!is_file($source)) {
    fwrite(STDERR, "No model at {$source}.\n");
    exit(1);
}

try {
    $model = ConceptModel::fromJson((string) file_get_contents($source));
} catch (\Throwable $e) {
    fwrite(STDERR, 'Cannot read ' . $source . ': ' . $e->getMessage() . "\n");
    exit(1);
}

$target = new MetaFormsTarget();

try {
    $files = (new Pipeline())->run($model, new Target(
        $target->id(),
        $target->label(),
        $target->validator(),
        ...$target->generators()
    ));
} catch (\Throwable $e) {
    fwrite(STDERR, 'Cannot generate from ' . $source . ': ' . $e->getMessage() . "\n");
    exit(1);
}

// Named after the language and its version, which is how a site tells two
// versions of one language apart in a downloads folder - and how Exten-gen's
// own build finds the single package it ships.
$name = MetalanguagePackage::slug($model->name())
    . '-' . MetalanguagePackage::versionSlug($model->version()) . '.zip';
$out  = \is_string($options['out'] ?? null) && $options['out'] !== '' ? $options['out'] : \dirname(__DIR__) . '/build';

$path = is_dir($out) ? rtrim($out, '/\\') . '/' . $name : $out;

if (!is_dir(\dirname($path)) && !mkdir(\dirname($path), 0755, true) && !is_dir(\dirname($path))) {
    fwrite(STDERR, 'Cannot create ' . \dirname($path) . "\n");
    exit(1);
}

(new ZipWriter())->write($files, $path);

printf(
    "%s %s: %d files -> %s\n",
    $model->name(),
    $model->version(),
    \count($files),
    $path
);
