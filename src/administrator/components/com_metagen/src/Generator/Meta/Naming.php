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
use Yepr\Component\Metagen\Administrator\Generator\Model\Feature;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Which of a classifier's properties is its identity, and which is its name.
 *
 * **This is the one place in the forms generator where a naming habit stands in
 * for something the meta-model cannot say.** LionCore M3 has no way to mark a
 * property as the key or as the label, so they are found by what they are
 * called: `key` or `id` or something ending in `_id`, and `name` or something
 * ending in `_name`.
 *
 * It is a convention rather than a guess because it reads both tables that
 * already exist and were written by hand, years apart, without being tuned to
 * either: M3's `key` and `name`, and ER1's `entity_id` and `entity_name`.
 *
 * It is here rather than in the two classes that ask, because the form and the
 * reference table have to agree about it. The form puts a class on the name
 * input and the table tells the browser to look for that class; if the two
 * disagreed about which property the name is, the dropdown would find no rows
 * and say nothing - the exact silent failure 1.9 existed to end.
 *
 * Marking them in the model is 4.2's business, together with the other things
 * the meta-model cannot express. Until then the convention is written down,
 * with a test that reads it off the tables it claims to reproduce.
 *
 * @since  1.2.0
 */
final class Naming
{
    /**
     * Property names that are an identity outright.
     *
     * @var string[]
     *
     * @since  1.2.0
     */
    private const IDENTITY_NAMES = ['key', 'id'];

    /**
     * Endings that make a property an identity when no name matches outright.
     *
     * @var string[]
     *
     * @since  1.2.0
     */
    private const IDENTITY_SUFFIXES = ['_id', '_key'];

    /**
     * Property names that are a display name outright.
     *
     * @var string[]
     *
     * @since  1.2.0
     */
    private const DISPLAY_NAMES = ['name'];

    /**
     * Endings that make a property a display name.
     *
     * @var string[]
     *
     * @since  1.2.0
     */
    private const DISPLAY_SUFFIXES = ['_name'];

    /**
     * The property holding a row's identity, or an empty string for none.
     *
     * @since  1.2.0
     */
    public static function identityOf(ConceptModel $model, Classifier $classifier): string
    {
        return self::propertyNamed($model, $classifier, self::IDENTITY_NAMES, self::IDENTITY_SUFFIXES);
    }

    /**
     * The property holding what a person reads in a dropdown.
     *
     * @since  1.2.0
     */
    public static function displayNameOf(ConceptModel $model, Classifier $classifier): string
    {
        return self::propertyNamed($model, $classifier, self::DISPLAY_NAMES, self::DISPLAY_SUFFIXES);
    }

    /**
     * The class the browser finds a type's rows by.
     *
     * @since  1.2.0
     */
    public static function selectorOf(Classifier $rowType): string
    {
        return lcfirst($rowType->name) . 'Name';
    }

    /**
     * The first property named exactly one of these, else ending in one of those.
     *
     * Exact names win as a group, before any suffix is tried, so a `key` beside
     * a `classifier_key` is the identity and the other is an ordinary field.
     * Trying them feature by feature would make the answer depend on which was
     * modelled first, which is not something anybody would think to check.
     *
     * @param  string[]  $exact
     * @param  string[]  $suffixes
     *
     * @since  1.2.0
     */
    private static function propertyNamed(
        ConceptModel $model,
        Classifier $classifier,
        array $exact,
        array $suffixes
    ): string {
        $properties = array_filter(
            $model->featuresOf($classifier),
            static fn (Feature $feature): bool => $feature->isProperty()
        );

        foreach ($properties as $feature) {
            if (\in_array($feature->name, $exact, true)) {
                return $feature->name;
            }
        }

        foreach ($properties as $feature) {
            foreach ($suffixes as $suffix) {
                if (str_ends_with($feature->name, $suffix)) {
                    return $feature->name;
                }
            }
        }

        return '';
    }
}
