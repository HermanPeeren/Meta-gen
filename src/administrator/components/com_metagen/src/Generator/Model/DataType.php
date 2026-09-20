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
 * One datatype in a modelled language: a primitive type, or an enumeration.
 *
 * What a property holds. It has no form of its own - it is never instantiated
 * as a node - so it contributes a *field type* rather than a file: a primitive
 * becomes whichever Joomla input suits it, and an enumeration becomes a `list`
 * whose options are its literals.
 *
 * **A primitive type is named, not chosen from a list.** `primitiveType.xml`
 * has a closed list of Integer, Boolean, Text and the rest commented out inside
 * it, and nothing replaced it: what a primitive is called is its `name`, which
 * is how ER1 gets a `String` and a `Boolean` in the fixture. So the mapping
 * from a datatype to an input is a mapping from that name, and a name nobody
 * recognises becomes a text box rather than an error - a modelled language may
 * legitimately name a primitive this component has never heard of.
 *
 * @since  1.2.0
 */
final class DataType
{
    /**
     * @since  1.2.0
     */
    public const ENUMERATION = 'Enumeration';

    /**
     * @param  string                            $name      The datatype's name, for instance `String`.
     * @param  string                            $key       Its key, which a property's type holds.
     * @param  string                            $kind      `PrimitiveType` or `Enumeration`.
     * @param  list<array{key: string, name: string}>  $literals  An enumeration's literals, in order.
     *
     * @since  1.2.0
     */
    private function __construct(
        public readonly string $name,
        public readonly string $key,
        public readonly string $kind,
        public readonly array $literals
    ) {
    }

    /**
     * Read one datatype out of a stored language entity.
     *
     * @param  object  $entity  A `languageEntities` row whose type is DataType.
     *
     * @since  1.2.0
     */
    public static function fromNode(object $entity): self
    {
        // The group is `datatype`, all lowercase, while every other subform in
        // the meta-model is lowerCamel. That is what `languageEntity.xml` names
        // the field, so it is what the stored model says.
        $datatype = \is_object($entity->datatype ?? null) ? $entity->datatype : new \stdClass();
        $kind     = self::text($datatype, 'dataType_type');

        return new self(
            self::text($entity, 'name'),
            self::text($entity, 'key'),
            $kind,
            $kind === self::ENUMERATION ? self::literals($datatype) : []
        );
    }

    /**
     * @since  1.2.0
     */
    public function isEnumeration(): bool
    {
        return $this->kind === self::ENUMERATION && $this->literals !== [];
    }

    /**
     * An enumeration's literals, in the order they were modelled.
     *
     * @return list<array{key: string, name: string}>
     *
     * @since  1.2.0
     */
    private static function literals(object $datatype): array
    {
        $enumeration = \is_object($datatype->enumeration ?? null) ? $datatype->enumeration : null;
        $group       = $enumeration === null ? null : ($enumeration->literals ?? null);

        if (!\is_object($group)) {
            return [];
        }

        $literals = [];

        foreach (get_object_vars($group) as $row) {
            if (!\is_object($row)) {
                continue;
            }

            $name = self::text($row, 'name');

            // A literal with no name is a row somebody added and left; it
            // would reach the form as a blank option indistinguishable from
            // the empty choice.
            if ($name === '') {
                continue;
            }

            $literals[] = ['key' => self::text($row, 'key'), 'name' => $name];
        }

        return $literals;
    }

    /**
     * @since  1.2.0
     */
    private static function text(object $node, string $key): string
    {
        return property_exists($node, $key) && is_scalar($node->{$key}) ? (string) $node->{$key} : '';
    }
}
