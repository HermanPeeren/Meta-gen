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

use DOMDocument;
use DOMElement;
use Yepr\Component\Metagen\Administrator\Generator\Model\Classifier;
use Yepr\Component\Metagen\Administrator\Generator\Model\Feature;
use Yepr\Component\Metagen\Administrator\Package\MetalanguagePackage;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One classifier's form, as Joomla form XML.
 *
 * Built through DOM rather than rendered from a template, which is the line
 * step 2.1 drew and this side of the house keeps: `Joomla6\Forms` builds form
 * XML the same way, because a form is a tree whose shape depends on the model
 * rather than a file with holes in it. A template that emitted `<field>` would
 * be a template that had to escape attribute values itself.
 *
 * **What a form holds is the classifier's own features, and nothing inherited.**
 * Inheritance is laid out structurally - see `LanguageStructure` - so a
 * subtype's form is reached through its parent's and the parent's fields are
 * already on screen above it. Putting them in both places would post two inputs
 * to one key, and the second would win without saying so.
 *
 * @since  1.2.0
 */
final class FormXml
{
    /**
     * The prefix the generated forms register for custom field types and rules.
     *
     * Generated forms use `type="Reference"`, which is the shared library's
     * field class - the same one the hand-written meta-model forms use. It is
     * the library's rather than this component's precisely so that a generated
     * language is portable: Exten-gen and Gen-gen resolve the same prefix, so
     * one set of form files works in whichever component imported it.
     *
     * A modelled language does not get to name a field class of its own. That
     * is one of the three model gaps 4.2 names, and it blocks self-hosting
     * rather than this step.
     *
     * @since  1.2.0
     */
    public const FIELD_PREFIX = 'Yepr\\Gen\\Joomla\\Form\\Field';

    /**
     * How a primitive type's name becomes a Joomla input.
     *
     * A primitive is named rather than chosen from a list - `primitiveType.xml`
     * has its closed list commented out and nothing replaced it - so this maps
     * from what somebody called it. A name that is not here becomes a text box,
     * because a modelled language may legitimately name a primitive this
     * component has never heard of and a text box is the honest answer.
     *
     * @var array<string, string>
     *
     * @since  1.2.0
     */
    private const PRIMITIVES = [
        'string'     => 'text',
        'short_text' => 'text',
        'text'       => 'textarea',
        'boolean'    => 'checkbox',
        'integer'    => 'number',
        'number'     => 'number',
        'decimal'    => 'number',
        'float'      => 'number',
        'date'       => 'calendar',
        'time'       => 'calendar',
        'datetime'   => 'calendar',
        'link'       => 'url',
        'url'        => 'url',
        'email'      => 'email',
        'file'       => 'file',
        'image'      => 'media',
    ];

    /**
     * @param  LanguageStructure  $structure     The language, laid out as forms.
     * @param  string             $languageName  What the generated set is filed under.
     * @param  string             $installRoot   Where the package expects to be unpacked, from the site root.
     * @param  LanguageStrings    $strings       Collects the text behind every constant emitted here.
     *
     * @since  1.2.0
     */
    public function __construct(
        private readonly LanguageStructure $structure,
        private readonly string $languageName,
        private readonly string $installRoot,
        private readonly LanguageStrings $strings
    ) {
    }

    /**
     * The path a form for one classifier occupies inside the package.
     *
     * @since  1.2.0
     */
    public function pathFor(Classifier $classifier): string
    {
        return MetalanguagePackage::formPath($classifier->name);
    }

    /**
     * The same form, spelled the way a `formsource` attribute has to spell it.
     *
     * Joomla resolves a `formsource` ending in `.xml` as
     * `JPATH_ROOT . '/' . $formsource` and nothing else, so a subform cannot
     * point at a sibling by a package-relative path. Every one of these is the
     * directory the package declares it will be unpacked into, plus the path
     * inside it - which is why `MetalanguagePackage` has to decide that
     * directory at all, and why the manifest records it.
     *
     * @since  1.3.0
     */
    public function formSourceFor(Classifier $classifier): string
    {
        return $this->installRoot . $this->pathFor($classifier);
    }

    /**
     * One classifier's form.
     *
     * @since  1.2.0
     */
    public function render(Classifier $classifier): string
    {
        $document = new DOMDocument('1.0', 'utf-8');

        $document->formatOutput = true;

        $form = $document->createElement('form');

        $document->appendChild($form);

        $fieldset = $document->createElement('fieldset');

        // No `addruleprefix`. 3.2 emitted one naming this component's own
        // `Rule` namespace, and there are two things wrong with that: a
        // package is loaded by com_extengen and com_gengen, where that prefix
        // resolves to nothing, and there is no such namespace in *this*
        // component either - the directory has never existed. A modelled
        // language cannot name a validation rule at all; that is one of the
        // three model gaps 4.2 names, and an attribute pointing at an empty
        // namespace is not a head start on closing it.
        $fieldset->setAttribute('addfieldprefix', self::FIELD_PREFIX);

        $form->appendChild($fieldset);

        foreach ($classifier->features as $feature) {
            $field = $this->fieldFor($document, $classifier, $feature);

            if ($field !== null) {
                $fieldset->appendChild($field);
            }
        }

        $this->appendSubtypes($document, $fieldset, $classifier);

        // What LionWeb calls this classifier, carried in the stored model so
        // that a node says what it is without the form it came from.
        $hidden = $document->createElement('field');

        $hidden->setAttribute('name', 'LIonWeb_key');
        $hidden->setAttribute('value', $this->structure->qualifiedNameOf($classifier));
        $hidden->setAttribute('type', 'hidden');

        $fieldset->appendChild($hidden);

        return (string) $document->saveXML();
    }

    /**
     * The discriminator radio and one subform per subtype.
     *
     * @since  1.2.0
     */
    private function appendSubtypes(DOMDocument $document, DOMElement $fieldset, Classifier $classifier): void
    {
        $subtypes = $this->structure->subtypesOf($classifier);

        if ($subtypes === []) {
            return;
        }

        $radio = $document->createElement('field');

        $radio->setAttribute('name', $this->structure->discriminatorOf($classifier));
        $radio->setAttribute('type', 'radio');
        // A concrete classifier is one of its own choices, and the first: a row
        // that is simply one of these, rather than one of the specialised kinds,
        // is a thing somebody can mean. An abstract one is not - that is what
        // abstract says - so its radio offers only the kinds below it, which is
        // why `classifier_type` in the hand-written meta-model offers Concept,
        // ConceptInterface and Annotation and not Classifier.
        $radio->setAttribute('default', $classifier->abstract ? $subtypes[0]->name : $classifier->name);
        $radio->setAttribute('label', $this->discriminatorLabel($classifier));

        if (!$classifier->abstract) {
            $own = $document->createElement('option', $this->typeLabel($classifier, $classifier));

            $own->setAttribute('value', $classifier->name);

            $radio->appendChild($own);
        }

        foreach ($subtypes as $subtype) {
            $option = $document->createElement('option', $this->typeLabel($classifier, $subtype));

            $option->setAttribute('value', $subtype->name);

            $radio->appendChild($option);
        }

        $fieldset->appendChild($radio);

        foreach ($subtypes as $subtype) {
            $group = $this->structure->groupOf($subtype);
            $field = $document->createElement('field');

            $field->setAttribute('name', $group);
            $field->setAttribute('type', 'subform');
            $field->setAttribute('formsource', $this->formSourceFor($subtype));
            $field->setAttribute('label', $this->typeLabel($classifier, $subtype));

            $description = $this->description($classifier, $subtype->name, $subtype->description);

            if ($description !== null) {
                $field->setAttribute('description', $description);
            }

            $field->setAttribute('id', $group);
            $field->setAttribute('layout', 'joomla.form.field.subform.default');
            $field->setAttribute('showon', $this->structure->discriminatorOf($classifier) . ':' . $subtype->name);

            $fieldset->appendChild($field);
        }
    }

    /**
     * One feature's field, or null for a feature that cannot become one.
     *
     * @since  1.2.0
     */
    private function fieldFor(DOMDocument $document, Classifier $owner, Feature $feature): ?DOMElement
    {
        if ($feature->name === '') {
            // A feature somebody added and has not named yet. A field with no
            // name posts nothing and hides the next one's label.
            return null;
        }

        $field = $document->createElement('field');

        $field->setAttribute('name', $feature->name);

        if ($feature->isProperty()) {
            $this->describeProperty($document, $field, $owner, $feature);
        } elseif ($feature->isContainment()) {
            if (!$this->describeContainment($field, $feature)) {
                return null;
            }
        } elseif ($feature->isReference()) {
            if (!$this->describeReference($field, $feature)) {
                return null;
            }
        } else {
            // A link that says neither Containment nor Reference is a row
            // half filled in. There is no field it could become.
            return null;
        }

        $field->setAttribute('label', $this->featureLabel($owner, $feature));

        $description = $this->description($owner, $feature->name, $feature->description);

        if ($description !== null) {
            $field->setAttribute('description', $description);
        }

        if (!$feature->optional && $feature->isProperty()) {
            $field->setAttribute('required', 'true');
        }

        return $field;
    }

    /**
     * A property: whichever input its datatype calls for.
     *
     * @since  1.2.0
     */
    private function describeProperty(
        DOMDocument $document,
        DOMElement $field,
        Classifier $owner,
        Feature $feature
    ): void {
        $datatype = $feature->typeKey === '' ? null : $this->structure->model()->dataType($feature->typeKey);

        if ($datatype !== null && $datatype->isEnumeration()) {
            $field->setAttribute('type', 'list');

            foreach ($datatype->literals as $literal) {
                $option = $document->createElement('option', $literal['name']);

                $option->setAttribute('value', $literal['key']);

                $field->appendChild($option);
            }

            return;
        }

        $field->setAttribute(
            'type',
            $datatype === null ? 'text' : (self::PRIMITIVES[strtolower($datatype->name)] ?? 'text')
        );

        // The class the browser finds this row by. It goes on the name input
        // of whichever classifier declares it, which is the one whose form
        // this is - a subtype's form must not carry it, or one row would be
        // found twice and the second read would overwrite the first.
        if ($this->isDisplayNameOf($owner, $feature)) {
            $field->setAttribute('class', Naming::selectorOf($owner));
        }
    }

    /**
     * A containment: a subform holding the target's own form.
     *
     * @since  1.2.0
     */
    private function describeContainment(DOMElement $field, Feature $feature): bool
    {
        $target = $feature->typeKey === '' ? null : $this->structure->model()->classifier($feature->typeKey);

        if ($target === null) {
            return false;
        }

        $field->setAttribute('type', 'subform');
        $field->setAttribute('formsource', $this->formSourceFor($target));
        $field->setAttribute('id', $feature->name);

        if ($feature->multiple) {
            $field->setAttribute('multiple', 'true');
            $field->setAttribute('buttons', 'add,remove,move');
            $field->setAttribute('layout', 'joomla.form.field.subform.repeatable');

            return true;
        }

        $field->setAttribute('layout', 'joomla.form.field.subform.default');

        return true;
    }

    /**
     * A reference: the dropdown 1.9 built, told what it may offer.
     *
     * @since  1.2.0
     */
    private function describeReference(DOMElement $field, Feature $feature): bool
    {
        $target = $feature->typeKey === '' ? null : $this->structure->model()->classifier($feature->typeKey);

        if ($target === null) {
            return false;
        }

        $field->setAttribute('type', 'Reference');
        $field->setAttribute('objecttype', $target->name);
        $field->setAttribute('id', $feature->name);

        // No `scope` yet, and deliberately not guessed at. A scoped dropdown -
        // the fields of one entity - narrows itself by an element beside it,
        // and the hand-written ER1 form that does this points at
        // `entity_reference_id`: the hidden backup of the *sibling dropdown*
        // where somebody picked the entity, not a property of the row. Which
        // of those a modelled language means is a question the real ER1 model
        // answers, at 3.3. Emitting a scope on a hunch would produce dropdowns
        // that narrow to the wrong thing, and an over-narrow list reads as an
        // empty one rather than as a mistake.
        return true;
    }

    /**
     * Whether this property is the one a dropdown reads a row's name from.
     *
     * @since  1.2.0
     */
    private function isDisplayNameOf(Classifier $owner, Feature $feature): bool
    {
        foreach ($this->structure->placements() as $placement) {
            // Only a row type carries the class: the browser finds one element
            // per row, and a subtype's form repeating it would mean finding the
            // same row twice under two names.
            if ($placement->rowType->key !== $owner->key) {
                continue;
            }

            return $feature->name === Naming::displayNameOf($this->structure->model(), $owner);
        }

        return false;
    }

    /**
     * The language string for one feature's label, and the words behind it.
     *
     * The constant is returned and the text is handed to the collector in the
     * same call, because this is the only place that has both. A generator
     * that emitted constants here and gathered text somewhere else would have
     * two lists that agree until somebody renames a feature.
     *
     * @since  1.2.0
     */
    private function featureLabel(Classifier $owner, Feature $feature): string
    {
        return $this->strings->add(
            $this->constantName([$this->languageName, $owner->name, 'FIELD', $feature->name, 'LABEL']),
            $feature->displayLabel()
        );
    }

    /**
     * The language string naming one subtype among its siblings.
     *
     * @since  1.2.0
     */
    private function typeLabel(Classifier $owner, Classifier $subtype): string
    {
        return $this->strings->add(
            $this->constantName([$this->languageName, $owner->name, 'FIELD', $subtype->name, 'LABEL']),
            $subtype->displayLabel()
        );
    }

    /**
     * The language string on the radio that says which kind of thing a row is.
     *
     * `Classifier type`, the way the hand-written meta-model spells it - which
     * matters because 3.5 compares the two sets as golden files, and a
     * difference in wording is one more line of that table to argue about.
     *
     * @since  1.3.0
     */
    private function discriminatorLabel(Classifier $classifier): string
    {
        return $this->strings->add(
            $this->constantName([
                $this->languageName,
                $classifier->name,
                'FIELD',
                $this->structure->discriminatorOf($classifier),
                'LABEL',
            ]),
            $classifier->displayLabel() . ' type'
        );
    }

    /**
     * The language string for a description, or null when there is none.
     *
     * A description is optional in the model and optional on the form: an
     * empty `description` attribute is not nothing, it is a tooltip that opens
     * onto blank space.
     *
     * @since  1.3.0
     */
    private function description(Classifier $owner, string $field, string $text): ?string
    {
        if (trim($text) === '') {
            return null;
        }

        return $this->strings->add(
            $this->constantName([$this->languageName, $owner->name, 'FIELD', $field, 'DESC']),
            $text
        );
    }

    /**
     * A constant naming the language rather than the component it came from.
     *
     * **Not `COM_METAGEN_`, and not `COM_EXTENGEN_` either.** 3.2 left this
     * open and 3.3 settled it: a package is consumed by com_extengen *and* by
     * com_gengen, so a key naming one of them is wrong whichever one it names.
     * The language is what the strings belong to, so the language is what they
     * are scoped by, and `YEPR_` in front keeps a modelled language called
     * `Content` from overwriting somebody else's `CONTENT_*`.
     *
     * @param  string[]  $parts
     *
     * @since  1.2.0
     */
    private function constantName(array $parts): string
    {
        $constant = 'YEPR_' . strtoupper(implode('_', $parts));

        return (string) preg_replace('/[^A-Z0-9_]/', '_', $constant);
    }
}
