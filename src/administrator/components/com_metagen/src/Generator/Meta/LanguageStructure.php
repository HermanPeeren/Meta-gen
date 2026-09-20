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
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What a modelled language looks like once it is laid out as Joomla forms.
 *
 * The forms generator and the reference table both need the same three answers
 * and would otherwise each work them out: which classifiers have subtypes,
 * what each subform group and discriminator radio is called, and how a model
 * of this language nests. Working them out twice is how two halves of one
 * mechanism drift, which is the failure 1.9 and 3.1 were both about.
 *
 * **Subtyping becomes nesting.** A classifier that others extend does not hand
 * them its fields; it gets a radio saying which one this row is, and a subform
 * each. That is how the hand-written meta-model is arranged - `languageEntity.xml`
 * holds a name, a key and a `languageEntity_type` radio, and `classifier.xml`
 * hangs off it - and it is the arrangement that lets one repeating group hold
 * five different kinds of thing. So a classifier's form carries its *own*
 * features only: what it inherits is in the form above it, structurally, and
 * somebody filling the form in still sees them all in one place.
 *
 * **Containment becomes a path.** A repeating containment is a repeating
 * subform, which is a group a reader walks into. A single one is stored as the
 * group itself rather than as one keyed row - that is what `SubformField::filter()`
 * does with `multiple` - so it is read as a dotted path and never walked, which
 * is why `classifier.classifier_type` is one condition rather than a step.
 *
 * **Two naming conventions, both read off the hand-written model.** A subtype's
 * subform group is `lcfirst` of its name - `concept`, `conceptInterface`,
 * `annotation` - and a discriminator radio is `lcfirst` of the type it
 * discriminates plus `_type`: `languageEntity_type`, `classifier_type`,
 * `dataType_type`, `feature_type`, `link_type`. All five in the meta-model
 * follow it, which is what makes it a convention rather than a guess.
 *
 * @since  1.2.0
 */
final class LanguageStructure
{
    /**
     * Direct subtypes, keyed by the key of what they extend.
     *
     * @var array<string, Classifier[]>
     *
     * @since  1.2.0
     */
    private array $subtypes = [];

    /**
     * Where each classifier's instances sit, keyed by classifier key.
     *
     * @var array<string, Placement>
     *
     * @since  1.2.0
     */
    private array $placements = [];

    /**
     * @since  1.2.0
     */
    private function __construct(private readonly ConceptModel $model)
    {
        foreach ($this->model->classifiers() as $classifier) {
            if ($classifier->extendsKey !== '') {
                $this->subtypes[$classifier->extendsKey][] = $classifier;
            }
        }

        $root = $this->model->root();

        if ($root !== null) {
            // The root is not placed: it is not a row of anything, it is the
            // record itself. Its containments are where placing starts.
            $this->walk($root, [], [], [], null, '', null, []);
        }
    }

    /**
     * @since  1.2.0
     */
    public static function of(ConceptModel $model): self
    {
        return new self($model);
    }

    /**
     * The model this describes.
     *
     * @since  1.2.0
     */
    public function model(): ConceptModel
    {
        return $this->model;
    }

    /**
     * The classifiers that directly extend this one, as modelled.
     *
     * @return Classifier[]
     *
     * @since  1.2.0
     */
    public function subtypesOf(Classifier $classifier): array
    {
        return $this->subtypes[$classifier->key] ?? [];
    }

    /**
     * Whether this classifier is told apart from its subtypes by a radio.
     *
     * @since  1.2.0
     */
    public function isDiscriminated(Classifier $classifier): bool
    {
        return $this->subtypesOf($classifier) !== [];
    }

    /**
     * The field name of the radio that says which subtype a row is.
     *
     * @since  1.2.0
     */
    public function discriminatorOf(Classifier $classifier): string
    {
        return lcfirst($classifier->name) . '_type';
    }

    /**
     * The field name of the subform group holding a subtype's own fields.
     *
     * @since  1.2.0
     */
    public function groupOf(Classifier $classifier): string
    {
        return lcfirst($classifier->name);
    }

    /**
     * What LionWeb calls this classifier: its name, qualified by what it extends.
     *
     * A node stores this so that it says what it is without the form it came
     * from. It is derived rather than read off the model, because the model
     * holds one `LIonWeb_key` per row and that one says the row is a
     * LanguageEntity - true of every row, and not what a form needs to know.
     *
     * The hand-written meta-model spells them out by hand and this reproduces
     * all of them: `LanguageEntity.Classifier.Concept`, `Feature.Property`,
     * `Feature.Link.Containment`, `LanguageEntity.DataType.PrimitiveType`.
     *
     * @since  1.2.0
     */
    public function qualifiedNameOf(Classifier $classifier): string
    {
        $names   = [$classifier->name];
        $current = $classifier;
        $seen    = [$classifier->key];

        while ($current->extendsKey !== '' && !\in_array($current->extendsKey, $seen, true)) {
            $seen[]  = $current->extendsKey;
            $parent  = $this->model->classifier($current->extendsKey);

            if ($parent === null) {
                break;
            }

            array_unshift($names, $parent->name);

            $current = $parent;
        }

        return implode('.', $names);
    }

    /**
     * Where one classifier's instances sit, or null when nothing contains it.
     *
     * A language whose root reaches nothing - a vocabulary of concepts with no
     * tree under it, which is what a model looks like halfway through being
     * written - places nothing. That is reported by there being no placement
     * rather than by an exception: it is an ordinary state for a model to be
     * in, and the forms for those classifiers are still worth generating.
     *
     * @since  1.2.0
     */
    public function placementOf(Classifier $classifier): ?Placement
    {
        return $this->placements[$classifier->key] ?? null;
    }

    /**
     * Every placement, in the order the classifiers were modelled.
     *
     * @return array<string, Placement>
     *
     * @since  1.2.0
     */
    public function placements(): array
    {
        $ordered = [];

        foreach ($this->model->classifiers() as $key => $classifier) {
            if (isset($this->placements[$key])) {
                $ordered[$key] = $this->placements[$key];
            }
        }

        return $ordered;
    }

    /**
     * Place one classifier, then everything it discriminates into or contains.
     *
     * @param  string[]                                        $path        Repeating groups walked so far.
     * @param  string[]                                        $prefix      Subform groups the conditions sit under.
     * @param  array<int, array{path: string, value: string}>  $conditions  What the row must say it is.
     * @param  ?Classifier                                     $rowType     The type owning the current row's name input.
     * @param  string                                          $containment The current repeating group's field name.
     * @param  ?Classifier                                     $container   The classifier holding that group.
     * @param  string[]                                        $seen        Keys already on this branch.
     *
     * @since  1.2.0
     */
    private function walk(
        Classifier $classifier,
        array $path,
        array $prefix,
        array $conditions,
        ?Classifier $rowType,
        string $containment,
        ?Classifier $container,
        array $seen
    ): void {
        // A containment cycle - a concept that contains itself, directly or
        // round a loop - is a legitimate language and an infinite form. It
        // stops here, and the deeper occurrence reuses the generated file,
        // which is what a subform pointing at its own source already means.
        if (\in_array($classifier->key, $seen, true)) {
            return;
        }

        $seen[] = $classifier->key;

        if ($rowType !== null && !isset($this->placements[$classifier->key])) {
            $this->placements[$classifier->key] = new Placement(
                $classifier,
                $path,
                $conditions,
                $rowType,
                $containment,
                $container
            );
        }

        foreach ($this->subtypesOf($classifier) as $subtype) {
            // A subtype shares the row it specialises: same path, same name
            // input, one more thing the row has to say about itself. Its own
            // fields sit in a group named after it, so anything deeper is
            // addressed from inside that group.
            $this->walk(
                $subtype,
                $path,
                [...$prefix, $this->groupOf($subtype)],
                [...$conditions, [
                    'path'  => implode('.', [...$prefix, $this->discriminatorOf($classifier)]),
                    'value' => $subtype->name,
                ]],
                $rowType,
                $containment,
                $container,
                $seen
            );
        }

        // Inherited containments included: a subtype holds what its parent
        // holds, and a reference to one of those children says nothing about
        // which subtype it came from.
        foreach ($this->model->featuresOf($classifier) as $feature) {
            if (!$feature->isContainment() || $feature->typeKey === '') {
                continue;
            }

            $target = $this->model->classifier($feature->typeKey);

            if ($target === null) {
                continue;
            }

            $this->walk(
                $target,
                $feature->multiple ? [...$path, $feature->name] : $path,
                $feature->multiple ? [] : [...$prefix, $feature->name],
                $feature->multiple ? [] : $conditions,
                $target,
                $feature->name,
                $rowType ?? $classifier,
                $seen
            );
        }
    }
}
