<?php

/**
 * @package     Metagen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Generator\Meta;

use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Package\PackageManifest;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ModelInterface;
use Yepr\Gen\Core\Output\FileCollection;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The two files that make the output a package rather than a pile: step 3.3.
 *
 * The concept model it was generated from, and a manifest naming the language,
 * its version and its root classifier - which is what the plan asks a package
 * to carry beyond the forms themselves.
 *
 * **It runs last, and that is load-bearing.** The manifest hashes every other
 * file in the collection, so it has to see them all; `Pipeline::run()` passes
 * one `FileCollection` through the generators in the order the target lists
 * them, which is the whole reason a target's `generators()` is a list rather
 * than a set. Put this first and the manifest describes an empty package, and
 * nothing anywhere would say so - the zip would still open and every hash in
 * it would still be correct, because there would be none.
 *
 * @since  1.3.0
 */
final class PackageFiles implements GeneratorInterface
{
    /**
     * Whether this generator has anything to contribute for the given model.
     *
     * @since  1.3.0
     */
    public function supports(ModelInterface $model): bool
    {
        return $model instanceof ConceptModel;
    }

    /**
     * Add the model and the manifest to what has been generated so far.
     *
     * @throws \JsonException  When the model cannot be re-encoded.
     *
     * @since  1.3.0
     */
    public function generate(ModelInterface $model, FileCollection $files): void
    {
        if (!$model instanceof ConceptModel) {
            throw new \InvalidArgumentException(
                'The package generator needs a ConceptModel, got ' . get_debug_type($model) . '.'
            );
        }

        $files->add(
            MetalanguagePackage::MODEL,
            json_encode(
                $model->stored(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . "\n"
        );

        $files->add(MetalanguagePackage::MANIFEST, $this->manifest($model, $files)->toJson());
    }

    /**
     * Nothing to say: a file either arrived or the run threw.
     *
     * @return string[]
     *
     * @since  1.3.0
     */
    public function log(): array
    {
        return [];
    }

    /**
     * Every classifier the language holds, by key and by name.
     *
     * A consumer that offers a language's concepts - Gen-gen, where a rule
     * says which ones it applies to - would otherwise have to read the concept
     * model and know how a language is stored. That is this component's
     * business and not a thing to teach the other two, so the manifest says it.
     *
     * Both halves, because they answer different questions. The name is what a
     * person picks out of a list; the key is what a rule stores, so renaming a
     * concept here changes what that rule reads as rather than what it points
     * at. The key is also what a LionWeb metapointer is made of.
     *
     * DataTypes are left out: a rule selects things a model can hold instances
     * of, and a datatype is what a property is, not a thing to iterate over.
     *
     * @return array<int, array{key: string, name: string}>
     *
     * @since  1.4.0
     */
    private function concepts(ConceptModel $model): array
    {
        $concepts = [];

        foreach ($model->classifiers() as $key => $classifier) {
            $features = [];

            // Effective, not declared: `featuresOf()` walks the extends chain
            // and the interfaces, so a language that moved a property up to a
            // supertype still says it has it. That matters to whoever reads
            // this - `AncestryCheck` compares a derived language against its
            // parent, and the hierarchy is not in a manifest to work it out
            // from, so the resolving happens here or nowhere.
            foreach ($model->featuresOf($classifier) as $feature) {
                $features[] = ['key' => $feature->key, 'name' => $feature->name];
            }

            $concepts[] = [
                'key'      => (string) $key,
                'name'     => $classifier->name,
                // Written even when empty, because an absent list and an empty
                // one mean different things to the guard: "this package does
                // not say" against "this concept has none".
                'features' => $features,
            ];
        }

        return $concepts;
    }

    /**
     * What this package says about itself.
     *
     * @since  1.3.0
     */
    private function manifest(ConceptModel $model, FileCollection $files): PackageManifest
    {
        $hashes = [];

        foreach ($files as $path => $contents) {
            $hashes[$path] = hash('sha256', $contents);
        }

        ksort($hashes);

        $root = $model->root();

        return new PackageManifest(
            $model->name(),
            MetalanguagePackage::slug($model->name()),
            MetalanguagePackage::versionSlug($model->version()),
            // A language nobody has marked a partition on has no root, and
            // that is an ordinary state for one being written rather than a
            // reason to refuse to package it. An importer reading '' knows it
            // cannot open a model in this language yet, which is exactly true.
            $root === null ? '' : $root->name,
            MetalanguagePackage::installRoot($model->name(), $model->version()),
            MetalanguagePackage::languagePath($model->name()),
            MetalanguagePackage::TAG,
            $this->concepts($model),
            $hashes,
            MetalanguagePackage::FORMAT,
            gmdate('c'),
            $model->dependsOn()
        );
    }
}
