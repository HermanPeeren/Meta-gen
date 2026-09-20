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

use Yepr\Component\Metagen\Administrator\Generator\Model\Classifier;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The reference table of a modelled language, generated from the language.
 *
 * This is the half of step 3.2 that the plan calls "the JavaScript the 1.9
 * mechanism needs", and it is not JavaScript: it is the description
 * `<metagen-reference>` reads. `ReferenceIndex` carries two of these by hand -
 * one for ER1, one for LionCore M3 - and says in its own docblock why they are
 * a map rather than a method per type: *because Meta-gen generates this from a
 * concept model in stage 3, and a list is a thing that can be generated.*
 *
 * An entry says both halves of one fact. The server half - `path`, `idKey`,
 * `nameKey`, `when` - reads a stored model. The client half reads the form on
 * screen: the class on the name input, how that input's element id relates to
 * the hidden id beside it, and which other input in the row decides whether it
 * counts. Neither can be derived from the other at runtime, because the
 * derivation is "how Joomla builds element ids". Generated together from one
 * source, they cannot disagree, which is the whole point of generating them.
 *
 * **Only what something points at gets an entry.** A table lists what can be
 * *chosen*, so a classifier no reference feature targets is not in it, however
 * many instances a model holds. That is why the hand-written M3 table has five
 * entries for fourteen classifiers.
 *
 * **Which property is the identity is a convention, not a model fact**, and it
 * is written down in `Naming` because the form half of this mechanism has to
 * agree with it.
 *
 * @since  1.2.0
 */
final class ReferenceTable
{
    /**
     * Classifiers reached by no containment, in the order they were modelled.
     *
     * @var string[]
     *
     * @since  1.2.0
     */
    private array $unplaced = [];

    /**
     * @since  1.2.0
     */
    public function __construct(private readonly LanguageStructure $structure)
    {
    }

    /**
     * The table, in `ReferenceIndex`'s own shape.
     *
     * @return array<string, array<string, mixed>>  Keyed by object type name.
     *
     * @since  1.2.0
     */
    public function build(): array
    {
        $this->unplaced = [];

        $table = [];

        foreach ($this->referenced() as $classifier) {
            $placement = $this->structure->placementOf($classifier);

            if ($placement === null) {
                // Something points at it and no model can hold one, so every
                // dropdown offering it would be empty. Reported rather than
                // emitted as an entry that indexes nothing - an empty list is
                // exactly what this mechanism exists to stop being silent.
                $this->unplaced[] = $classifier->name;

                continue;
            }

            $entry = $this->entryFor($placement);

            if ($entry !== null) {
                $table[$classifier->name] = $entry;
            }
        }

        return $table;
    }

    /**
     * Classifiers something points at, but that no model can hold.
     *
     * @return string[]
     *
     * @since  1.2.0
     */
    public function unplaced(): array
    {
        return $this->unplaced;
    }

    /**
     * One entry: where the server looks, and how the browser looks.
     *
     * @return ?array<string, mixed>
     *
     * @since  1.2.0
     */
    private function entryFor(Placement $placement): ?array
    {
        $idKey   = Naming::identityOf($this->structure->model(), $placement->rowType);
        $nameKey = Naming::displayNameOf($this->structure->model(), $placement->rowType);

        if ($idKey === '' || $nameKey === '') {
            // Without both, an entry would offer rows that cannot be named or
            // cannot be stored. The generator reports this as an unplaced type
            // for the same reason: better an absence somebody can see.
            $this->unplaced[] = $placement->classifier->name;

            return null;
        }

        $entry = [
            'path'    => $placement->path,
            'idKey'   => $idKey,
            'nameKey' => $nameKey,
        ];

        $client = [
            'selector'  => Naming::selectorOf($placement->rowType),
            'nameToken' => $nameKey,
            'idToken'   => $idKey,
        ];

        $parentKey = $this->parentKeyOf($placement);

        if ($parentKey !== '') {
            $entry['parentKey']    = $parentKey;
            $client['parentToken'] = $parentKey;

            // A child's element id is its parent's with its own repeating group
            // appended twice - once as the field, once as the row - so cutting
            // there reaches the parent's own id input. That is how a child
            // added a moment ago, whose parent key is still blank, finds out
            // what it belongs to.
            $client['parentCut'] = '_' . $placement->containment . '__' . $placement->containment;
        }

        if ($placement->conditions !== []) {
            $entry['when']  = $placement->conditions;
            $client['when'] = $placement->clientConditions();
        }

        $entry['client'] = $client;

        return $entry;
    }

    /**
     * Every classifier some reference feature in the language points at.
     *
     * In the order they were modelled rather than the order they are pointed
     * at, so that the table reads like the language does and regenerating an
     * unchanged model produces an unchanged file.
     *
     * @return Classifier[]
     *
     * @since  1.2.0
     */
    private function referenced(): array
    {
        $model  = $this->structure->model();
        $wanted = [];

        foreach ($model->classifiers() as $classifier) {
            foreach ($model->featuresOf($classifier) as $feature) {
                if ($feature->isReference() && $feature->typeKey !== '') {
                    $wanted[$feature->typeKey] = true;
                }
            }
        }

        $referenced = [];

        foreach ($model->classifiers() as $key => $classifier) {
            if (isset($wanted[$key])) {
                $referenced[] = $classifier;
            }
        }

        return $referenced;
    }

    /**
     * The property on a child that holds which parent it belongs to.
     *
     * A dropdown scoped to a parent - the fields of one entity - filters by it.
     * The child has to carry it: a containment says where a row is stored, and
     * the browser reads a row rather than the tree around it, so unless the row
     * itself says whose it is there is nothing to filter on before a save.
     *
     * @since  1.2.0
     */
    private function parentKeyOf(Placement $placement): string
    {
        if ($placement->parent === null) {
            return '';
        }

        $parentKey = Naming::identityOf($this->structure->model(), $placement->parent);

        if ($parentKey === '') {
            return '';
        }

        foreach ($this->structure->model()->featuresOf($placement->rowType) as $feature) {
            if ($feature->isProperty() && $feature->name === $parentKey) {
                return $parentKey;
            }
        }

        return '';
    }
}
