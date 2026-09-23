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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * How a generated form looks: step 3.5's defaults table.
 *
 * A concept model says what a language *is*. It does not say that a reference
 * dropdown is tinted, or that a repeating subform has buttons under it, and it
 * should not: those are decisions about a Joomla form, and a language that
 * carried them could not be generated into anything else. 3.2 listed the
 * problem and 3.5 settled it - the presentation lives here, keyed by what the
 * generator already knows about a feature, and nowhere near the model.
 *
 * **One place rather than scattered through the emitter.** These attributes
 * were spelled out inside `FormXml` beside the fields they applied to, which
 * works and means nobody can answer "what do generated forms look like" without
 * reading the whole emitter. The answer is this file.
 *
 * **What a table cannot give back, and is not pretending to.** Comparing the
 * generated ER1 against the hand-written one at 3.5 found eighteen `size`
 * attributes and fourteen `min`s, and they are not uniform: sizes run 60, 40,
 * 20, 2 and 1, and only seventeen of thirty-five subforms carry a `min` at all.
 * Those are per-field decisions somebody made one at a time. A table that
 * guessed one number would make eighteen fields differently wrong instead of
 * uniformly plain, so it guesses none of them and the widths are lost. That is
 * the cost of not modelling presentation, and it is the cost that was chosen.
 *
 * @since  1.4.0
 */
final class Presentation
{
    /**
     * The tint a dropdown of existing things carries.
     *
     * Uniform in both hand-written languages: every ER1 reference field and
     * every M3 one is `custom-select-color-state`, which is Joomla's own class
     * for a select that shows state. Ten fields, one value - which is what
     * makes it a default rather than a decision per field.
     *
     * @since  1.4.0
     */
    public const SELECT_CLASS = 'custom-select-color-state';

    /**
     * How many rows a closed list shows: one, so it is a dropdown.
     *
     * @since  1.4.0
     */
    public const LIST_SIZE = '1';

    /**
     * What sits under a repeating subform.
     *
     * `move` as well as add and remove, because the order of a repeating group
     * is part of the model - the entities of a project generate in the order
     * they are listed - and a group somebody cannot reorder is a model they
     * cannot express.
     *
     * @since  1.4.0
     */
    public const REPEATING_BUTTONS = 'add,remove,move';

    /**
     * The layouts Joomla renders a subform with.
     *
     * @since  1.4.0
     */
    public const REPEATING_LAYOUT = 'joomla.form.field.subform.repeatable';

    /**
     * @since  1.4.0
     */
    public const SINGLE_LAYOUT = 'joomla.form.field.subform.default';

    /**
     * What a reference dropdown looks like.
     *
     * @return array<string, string>
     *
     * @since  1.4.0
     */
    public static function forReference(): array
    {
        return ['class' => self::SELECT_CLASS];
    }

    /**
     * What a closed list looks like.
     *
     * The same tint as a reference, because they are the same gesture: both
     * are "choose one of these", and one of them happening to be a set of
     * concepts rather than a set of literals is not a difference a person
     * filling in a form cares about.
     *
     * @return array<string, string>
     *
     * @since  1.4.0
     */
    public static function forEnumeration(): array
    {
        return ['class' => self::SELECT_CLASS, 'size' => self::LIST_SIZE];
    }

    /**
     * What a subform looks like, repeating or not.
     *
     * @return array<string, string>
     *
     * @since  1.4.0
     */
    public static function forContainment(bool $multiple, bool $optional = true): array
    {
        if (!$multiple) {
            return ['layout' => self::SINGLE_LAYOUT];
        }

        $attributes = [
            'multiple' => 'true',
            'buttons'  => self::REPEATING_BUTTONS,
            'layout'   => self::REPEATING_LAYOUT,
        ];

        // A repeating containment that is not optional holds at least one, and
        // the form opens with that one already there. This is multiplicity
        // rather than presentation - LionCore has it, `is_optional` is where
        // this language keeps it - and it arrives here because the attribute
        // that expresses it is a Joomla form's `min`.
        //
        // 3.5 filed `min` under presentation and lost it, and a browser spec
        // caught the difference: a project's `pages` is not optional, so the
        // hand-written form opened with a page in it and the generated one
        // opened with none. The spec said so in a comment written long before
        // any of this, which is the second time this week a comment has been
        // the specification.
        if (!$optional) {
            $attributes['min'] = '1';
        }

        return $attributes;
    }

    /**
     * What the radio that asks which kind of thing a row is looks like.
     *
     * Nothing. It is a short list of words and Joomla renders it fine; saying
     * so here is what stops somebody adding a class to `FormXml` later and
     * leaving this file describing a form that no longer exists.
     *
     * @return array<string, string>
     *
     * @since  1.4.0
     */
    public static function forDiscriminator(): array
    {
        return [];
    }
}
