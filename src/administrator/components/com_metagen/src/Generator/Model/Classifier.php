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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One classifier in a modelled language: a concept, a concept interface or an
 * annotation.
 *
 * These are the three things a form can be generated for, because they are the
 * three things a model can hold an instance of. A datatype is not one of them -
 * it is what a property holds, not something with a form of its own - and
 * `ConceptModel` keeps them apart for that reason.
 *
 * **What the three share is in `classifier`, not in each kind.** A classifier of
 * any kind may carry features and, in the stored shape, they hang off the
 * `classifier` group rather than off `concept`, `conceptInterface` or
 * `annotation`. What distinguishes the kinds is what they may point at:
 * a concept extends a concept, an interface extends an interface, an
 * annotation extends an annotation and additionally annotates any classifier -
 * which this does not yet carry, because nothing generated from it reads one.
 * An annotation's own fields reach a form as features like any other; making
 * the classifier it attaches to change *that* classifier's form is a language
 * feature nothing in this repository can check yet.
 *
 * @since  1.2.0
 */
final class Classifier
{
    /**
     * @param  string     $name        The classifier's name, as somebody typed it.
     * @param  string     $key         Its key, which is what a reference to it stores.
     * @param  string     $label       What a person reads instead of the name, if anybody set one.
     * @param  string     $description How it is explained on screen, if anybody set one.
     * @param  string     $kind        `Concept`, `ConceptInterface` or `Annotation`.
     * @param  string     $extendsKey  The key of the classifier it extends, if any.
     * @param  string[]   $implements  Keys of the concept interfaces it implements.
     * @param  bool       $abstract    Whether it may be instantiated.
     * @param  bool       $partition   Whether it may be a root, which makes it the top form.
     * @param  Feature[]  $features    Its own features, not counting inherited ones.
     *
     * @since  1.2.0
     */
    private function __construct(
        public readonly string $name,
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly string $kind,
        public readonly string $extendsKey,
        public readonly array $implements,
        public readonly bool $abstract,
        public readonly bool $partition,
        public readonly array $features
    ) {
    }

    /**
     * Read one classifier out of a stored language entity.
     *
     * @param  object  $entity  A `languageEntities` row whose type is Classifier.
     *
     * @since  1.2.0
     */
    public static function fromNode(object $entity): self
    {
        $classifier = \is_object($entity->classifier ?? null) ? $entity->classifier : new \stdClass();
        $kind       = self::text($classifier, 'classifier_type');

        // Whichever kind this is, its own group is named after it in lowerCamel
        // - `concept`, `conceptInterface`, `annotation` - which is the same
        // relation `classifier.xml` writes into its `showon` attributes.
        $own = \is_object($classifier->{lcfirst($kind)} ?? null)
            ? $classifier->{lcfirst($kind)}
            : new \stdClass();

        return new self(
            self::text($entity, 'name'),
            self::text($entity, 'key'),
            self::text($entity, 'label'),
            self::text($entity, 'description'),
            $kind,
            self::text($own, 'extends'),
            self::implementedKeys($own),
            self::flag($own, 'abstract'),
            self::flag($own, 'partition'),
            self::features($classifier)
        );
    }

    /**
     * What a person should read for this classifier.
     *
     * @since  1.2.0
     */
    public function displayLabel(): string
    {
        return $this->label !== '' ? $this->label : $this->name;
    }

    /**
     * The keys this classifier inherits features from, nearest first.
     *
     * A concept's own `extends` chain and the interfaces it implements are
     * both inheritance as far as a form is concerned: the fields arrive either
     * way and somebody filling the form in cannot tell which.
     *
     * @return string[]
     *
     * @since  1.2.0
     */
    public function parentKeys(): array
    {
        return array_values(array_filter(
            array_merge([$this->extendsKey], $this->implements),
            static fn (string $key): bool => $key !== ''
        ));
    }

    /**
     * @return Feature[]
     *
     * @since  1.2.0
     */
    private static function features(object $classifier): array
    {
        $group = $classifier->feature ?? null;

        if (!\is_object($group)) {
            return [];
        }

        $features = [];

        foreach (get_object_vars($group) as $node) {
            if (\is_object($node)) {
                $features[] = Feature::fromNode($node);
            }
        }

        return $features;
    }

    /**
     * The concept interfaces a classifier implements.
     *
     * A repeating group of one-field rows, each holding a key. An empty row -
     * somebody pressed add and stopped - is dropped rather than carried
     * forward as a parent nothing can resolve.
     *
     * @return string[]
     *
     * @since  1.2.0
     */
    private static function implementedKeys(object $own): array
    {
        $group = $own->implements ?? null;

        if (!\is_object($group)) {
            return [];
        }

        $keys = [];

        foreach (get_object_vars($group) as $row) {
            if (\is_object($row) && self::text($row, 'conceptInterface') !== '') {
                $keys[] = self::text($row, 'conceptInterface');
            }
        }

        return $keys;
    }

    /**
     * @since  1.2.0
     */
    private static function text(object $node, string $key): string
    {
        return property_exists($node, $key) && is_scalar($node->{$key}) ? (string) $node->{$key} : '';
    }

    /**
     * @since  1.2.0
     */
    private static function flag(object $node, string $key): bool
    {
        return property_exists($node, $key) && (bool) $node->{$key};
    }
}
