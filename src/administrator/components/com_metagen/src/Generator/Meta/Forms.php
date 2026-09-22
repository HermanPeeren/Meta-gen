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
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ModelInterface;
use Yepr\Gen\Core\Output\FileCollection;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The forms of a modelled language: step 3.2.
 *
 * Give it a concept model - a language, in LionCore M3 - and it produces the
 * Joomla form XML a model *written in* that language is edited with, the
 * reference dropdowns wired the way 1.9 wired them, and the table those
 * dropdowns read.
 *
 * **There was a class here before and it could not have run.** The old
 * `Generator\ProjectForms` was a copy of the ER1 forms generator with a dead
 * first half bolted on: its live code opened `foreach ($metalanguage->datamodel
 * as $entity)`, and a projectForm has no `datamodel` - it has
 * `languageEntities`. So the one screen that called it would have fataled on
 * its first statement, and nothing called that screen either: the link in
 * `projectForms/default.php` said `view=generateform`, the directory was
 * `GenerateProjectForm`, and the class inside it declared a third namespace
 * again. Three names for one view, none of them agreeing, which is why nobody
 * had found out that the generator underneath could not work.
 *
 * **Why this contributes to a `FileCollection` like everything else.** The old
 * one wrote into `administrator/components/com_metagen/forms/` with `mkdir`
 * and `save()` as it went, so generating forms edited the running component
 * from inside itself and a failure half way through left half a language
 * behind. Files are produced in memory and written once, by whoever asked.
 *
 * @since  1.2.0
 */
final class Forms implements GeneratorInterface
{
    /**
     * Where the reference table is written, beside the forms it describes.
     *
     * @since  1.2.0
     */
    public const TABLE_FILE = MetalanguagePackage::REFERENCES;

    /**
     * What this generator produced, in the words shown to the user.
     *
     * @var string[]
     *
     * @since  1.2.0
     */
    private array $log = [];

    /**
     * Whether this generator has anything to contribute for the given model.
     *
     * @since  1.2.0
     */
    public function supports(ModelInterface $model): bool
    {
        return $model instanceof ConceptModel;
    }

    /**
     * Add the language's forms and its reference table to the collection.
     *
     * @since  1.2.0
     */
    public function generate(ModelInterface $model, FileCollection $files): void
    {
        if (!$model instanceof ConceptModel) {
            throw new \InvalidArgumentException(
                'The forms generator needs a ConceptModel, got ' . get_debug_type($model) . '.'
            );
        }

        $structure = LanguageStructure::of($model);
        $table     = new ReferenceTable($structure);
        $built     = $table->build();
        $strings   = new LanguageStrings();
        $forms     = new FormXml(
            $structure,
            MetalanguagePackage::slug($model->name()),
            MetalanguagePackage::installRoot($model->name(), $model->version()),
            $strings
        );

        $this->log = ['<b>=== FORMS FOR ' . $model->name() . ' ===</b>'];

        foreach ($model->classifiers() as $classifier) {
            $files->add($forms->pathFor($classifier), $forms->render($classifier));

            $this->log[] = $forms->pathFor($classifier) . ' generated';
        }

        $files->add(
            self::TABLE_FILE,
            // Pretty-printed and with slashes left alone: this is read by a
            // person as often as by the component, and an escaped path in a
            // `formsource` is unreadable for no gain.
            json_encode($built, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
        );

        $this->log[] = self::TABLE_FILE . ' generated, describing ' . \count($built) . ' reference types';

        // Last, because every constant it holds was collected while the forms
        // above were built. Before 3.3 there was no file here at all, and so
        // every generated label rendered as its own constant name.
        $language = MetalanguagePackage::languagePath($model->name());

        $files->add($language, $strings->render($model->name() . ' ' . $model->version()));

        $this->log[] = $language . ' generated, holding ' . $strings->count() . ' strings';

        $this->reportGaps($structure, $table);
        $this->reportClashes($strings);
    }

    /**
     * What this generator produced, in the words shown to the user.
     *
     * @return string[]
     *
     * @since  1.2.0
     */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * Say out loud what a model does not reach.
     *
     * A form nothing reaches cannot be wrong, because it never runs - which is
     * how `interface.xml` pointed at a file that never existed for as long as
     * it did, and why `FormsTest` now asks the hand-written meta-model the same
     * question. A generator can answer it while generating instead.
     *
     * @since  1.2.0
     */
    private function reportGaps(LanguageStructure $structure, ReferenceTable $table): void
    {
        $placed = $structure->placements();

        foreach ($structure->model()->classifiers() as $key => $classifier) {
            if (!isset($placed[$key]) && !$classifier->partition) {
                $this->log[] = 'note: nothing contains ' . $classifier->name
                    . ', so no model can hold one and its form is unreachable';
            }
        }

        foreach ($table->unplaced() as $name) {
            $this->log[] = 'warning: ' . $name
                . ' is pointed at by a reference and cannot be indexed, so that dropdown will be empty';
        }
    }

    /**
     * Say out loud where one constant was asked for two different labels.
     *
     * Two features of one classifier sharing a name is the only way to get
     * here, and `featuresOf()` already lets a redeclared feature win - so this
     * is the case it cannot resolve: one of the two labels ends up on screen
     * and which one is not decidable from the model.
     *
     * @since  1.3.0
     */
    private function reportClashes(LanguageStrings $strings): void
    {
        foreach ($strings->clashes() as $constant => $texts) {
            $this->log[] = 'warning: ' . $constant . ' was given more than one label ("'
                . implode('", "', $texts) . '"); the first is the one on screen';
        }
    }
}
