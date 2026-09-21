<?php

/**
 * @package     Metagen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Generator\Model;

use Yepr\Gen\Core\Model\ModelInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One modelled language: a metalanguage, in LionCore M3.
 *
 * `Project` is the other model this component holds, and the two are different
 * in kind rather than in shape. A project is *written in* a language - ER1 -
 * and describes an extension. A concept model *is* a language: it says what
 * kinds of object exist, what each holds and what may point at what. Generating
 * from a project produces a component; generating from a concept model produces
 * the forms a project in that language is edited with, which is step 3.2.
 *
 * **The stored shape is a Joomla form's shape**, exactly as `Project`'s is.
 * Every language entity is a row of one repeating group, keyed
 * `languageEntities0`, `languageEntities1`; a row is a Classifier or a DataType
 * and says which; a classifier is a Concept, a ConceptInterface or an
 * Annotation and says which. Reading that is a job with rules in it, so it
 * happens here and the generators get `Classifier` and `DataType` objects.
 *
 * **Inheritance is resolved here too**, because it is a property of the
 * language rather than of the form being written. A concept that extends
 * another has the other's fields, and so does one that implements an interface;
 * somebody filling in the form cannot tell which of the three ways a field
 * arrived, and neither should a generator.
 *
 * @since  1.2.0
 */
final class ConceptModel implements ModelInterface
{
    /**
     * The classifiers, keyed by their own key.
     *
     * @var array<string, Classifier>
     *
     * @since  1.2.0
     */
    private array $classifiers = [];

    /**
     * The datatypes, keyed by their own key.
     *
     * @var array<string, DataType>
     *
     * @since  1.2.0
     */
    private array $dataTypes = [];

    /**
     * @param  object  $data  The metalanguage as stored, decoded.
     *
     * @since  1.2.0
     */
    private function __construct(private readonly object $data)
    {
        foreach (self::listOf($this->data->languageEntities ?? null) as $entity) {
            $key = property_exists($entity, 'key') && is_scalar($entity->key) ? (string) $entity->key : '';

            // A row with no key yet cannot be pointed at and cannot own a form,
            // for the same reason `ReferenceIndex` leaves it out of the index:
            // there is nothing for anything else to name it by. Real forms are
            // full of these - somebody pressed add and went to lunch.
            if ($key === '') {
                continue;
            }

            $type = property_exists($entity, 'languageEntity_type') && is_scalar($entity->languageEntity_type)
                ? (string) $entity->languageEntity_type
                : '';

            if ($type === 'Classifier') {
                $this->classifiers[$key] = Classifier::fromNode($entity);
            }

            if ($type === 'DataType') {
                $this->dataTypes[$key] = DataType::fromNode($entity);
            }
        }
    }

    /**
     * Read a metalanguage from the JSON the component stores in `form_data`.
     *
     * @throws \JsonException  When the stored value is not JSON.
     *
     * @since  1.2.0
     */
    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

        if (!\is_object($decoded)) {
            throw new \InvalidArgumentException(
                'A metalanguage must be a JSON object, got ' . get_debug_type($decoded) . '.'
            );
        }

        return self::fromObject($decoded);
    }

    /**
     * Read a metalanguage from an already decoded value.
     *
     * @since  1.2.0
     */
    public static function fromObject(object $data): self
    {
        return new self($data);
    }

    /**
     * The language's name, which is what its generated forms are filed under.
     *
     * @since  1.2.0
     */
    public function name(): string
    {
        return (string) ($this->data->name ?? '');
    }

    /**
     * The language's version, as somebody typed it on the root form.
     *
     * Free text rather than a checked semantic version, because that is what
     * the field is. A language with none is `0.0.0` by the time it reaches a
     * package; deciding that here would hide an empty field from the screen
     * that could ask about it.
     *
     * @since  1.3.0
     */
    public function version(): string
    {
        $version = $this->data->version ?? '';

        return \is_scalar($version) ? trim((string) $version) : '';
    }

    /**
     * The language as it is stored, which is what a package carries.
     *
     * The same object `fromObject()` was given: a package holds the model it
     * generated from so that a language which arrives somewhere can be
     * regenerated there, and a model rebuilt from the parts would be a
     * different thing wearing the same name.
     *
     * @since  1.3.0
     */
    public function stored(): object
    {
        return $this->data;
    }

    /**
     * Every classifier in the language, in the order it was modelled.
     *
     * @return array<string, Classifier>  Keyed by the classifier's key.
     *
     * @since  1.2.0
     */
    public function classifiers(): array
    {
        return $this->classifiers;
    }

    /**
     * Every datatype in the language, in the order it was modelled.
     *
     * @return array<string, DataType>  Keyed by the datatype's key.
     *
     * @since  1.2.0
     */
    public function dataTypes(): array
    {
        return $this->dataTypes;
    }

    /**
     * One classifier by key, or null when nothing carries that key.
     *
     * @since  1.2.0
     */
    public function classifier(string $key): ?Classifier
    {
        return $this->classifiers[$key] ?? null;
    }

    /**
     * One datatype by key, or null when nothing carries that key.
     *
     * @since  1.2.0
     */
    public function dataType(string $key): ?DataType
    {
        return $this->dataTypes[$key] ?? null;
    }

    /**
     * The classifier a model of this language has at its root.
     *
     * LionCore calls it a partition. More than one is possible in principle and
     * this component has never had a use for a second, so the first is the
     * answer and the rest are ordinary concepts - which is what the old
     * generator did too, except that it took the *last* one it happened to
     * walk past and said so in a comment.
     *
     * @since  1.2.0
     */
    public function root(): ?Classifier
    {
        foreach ($this->classifiers as $classifier) {
            if ($classifier->partition) {
                return $classifier;
            }
        }

        return null;
    }

    /**
     * Every feature a classifier has, inherited ones included.
     *
     * Inherited first, then its own, because that is the order they read in:
     * a concept's form opens with what makes it the kind of thing it is and
     * goes on to what makes it that particular one.
     *
     * A feature the classifier redeclares wins over the inherited one of the
     * same name. Two fields with one name in a form is not a narrowed type, it
     * is two inputs posting to the same key, and the later one wins silently.
     *
     * @return Feature[]
     *
     * @since  1.2.0
     */
    public function featuresOf(Classifier $classifier): array
    {
        $features = [];

        foreach ($this->inheritedFeatures($classifier, []) as $feature) {
            $features[$feature->name] = $feature;
        }

        foreach ($classifier->features as $feature) {
            $features[$feature->name] = $feature;
        }

        return array_values($features);
    }

    /**
     * What a classifier inherits, walking up every parent it names.
     *
     * @param  string[]  $seen  Keys already walked, which is what stops a cycle.
     *
     * @return Feature[]
     *
     * @since  1.2.0
     */
    private function inheritedFeatures(Classifier $classifier, array $seen): array
    {
        $features = [];

        foreach ($classifier->parentKeys() as $key) {
            // A concept extending itself, directly or round a loop, is a model
            // somebody is in the middle of editing rather than an impossibility
            // - and without this the form for it never finishes generating.
            if (\in_array($key, $seen, true)) {
                continue;
            }

            $parent = $this->classifier($key);

            if ($parent === null) {
                continue;
            }

            foreach ($this->inheritedFeatures($parent, [...$seen, $key]) as $feature) {
                $features[] = $feature;
            }

            foreach ($parent->features as $feature) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    /**
     * Turn a subform group into a list, whichever way it was stored.
     *
     * @return object[]
     *
     * @since  1.2.0
     */
    private static function listOf(mixed $value): array
    {
        if (\is_array($value)) {
            return array_values(array_filter($value, '\is_object'));
        }

        if (\is_object($value)) {
            return array_values(array_filter(get_object_vars($value), '\is_object'));
        }

        return [];
    }
}
