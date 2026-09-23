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
 * A field class a property is edited with, when a text box will not do.
 *
 * The first of the three model gaps 4.2 lists, brought forward because 3.5
 * measured the need rather than guessing at it: ER1 uses three custom field
 * types and two of them do real work. `type="Slot" owner="Entity"` is the
 * custom-code picker, whose choices come from `SlotCatalogue` rather than from
 * a list written into the form, and `type="HtmlTypes"` is the html-type picker.
 * Generated as plain text boxes, both stop being pickers.
 *
 * **This is the one place a language may name a component**, and the exception
 * is deliberate rather than an oversight in the rule 3.3 set. Paths and
 * language keys must name no component because a package is loaded by more than
 * one; a field *class* is different, because it genuinely lives in one and a
 * language that uses it genuinely depends on it. Saying so out loud - in the
 * model, and again in the generated form's `addfieldprefix` - is better than
 * the two ways of not saying it, which are losing the field or pretending the
 * class is somewhere it is not.
 *
 * **The parameters are what makes it more than a name.** `owner="Entity"` tells
 * `SlotField` which slots to offer; without it the picker resolves and is
 * empty, which reads as "there are no slots" rather than as a mistake.
 *
 * @since  1.4.0
 */
final class CustomField
{
    /**
     * @param  string                  $type        The field class's own type name, as a form spells it.
     * @param  string                  $prefix      The namespace it lives in, or '' for a field the library ships.
     * @param  array<string, string>   $parameters  Attributes the field class reads, by name.
     *
     * @since  1.4.0
     */
    private function __construct(
        public readonly string $type,
        public readonly string $prefix,
        public readonly array $parameters
    ) {
    }

    /**
     * Read one out of a stored property, or null when it names no field class.
     *
     * @since  1.4.0
     */
    public static function fromNode(?object $property): ?self
    {
        if ($property === null) {
            return null;
        }

        $type = property_exists($property, 'field_type') && is_scalar($property->field_type)
            ? trim((string) $property->field_type)
            : '';

        if ($type === '') {
            return null;
        }

        $prefix = property_exists($property, 'field_prefix') && is_scalar($property->field_prefix)
            ? trim((string) $property->field_prefix)
            : '';

        return new self($type, $prefix, self::parametersIn($property));
    }

    /**
     * The parameters a stored property carries, by name.
     *
     * @return array<string, string>
     *
     * @since  1.4.0
     */
    private static function parametersIn(object $property): array
    {
        $group = $property->field_parameters ?? null;

        if (\is_object($group)) {
            $group = array_values(get_object_vars($group));
        }

        if (!\is_array($group)) {
            return [];
        }

        $parameters = [];

        foreach ($group as $one) {
            if (!\is_object($one)) {
                continue;
            }

            $name = property_exists($one, 'name') && is_scalar($one->name) ? trim((string) $one->name) : '';

            // A row somebody added and has not named yet. An attribute with no
            // name is not an attribute, and writing one would produce XML that
            // does not parse.
            if ($name === '') {
                continue;
            }

            $parameters[$name] = property_exists($one, 'value') && is_scalar($one->value)
                ? (string) $one->value
                : '';
        }

        return $parameters;
    }
}
