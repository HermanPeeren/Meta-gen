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
 * Where instances of one classifier sit in a model written in its language.
 *
 * A reference dropdown has to find them, and finding them is two questions that
 * look like one. *Which repeating groups do you walk through* - that is the
 * path, and it is containment. *And which of the rows you land on are this
 * kind* - that is the conditions, and it is subtyping, because a subtype shares
 * its parent's repeating group and says what it is in a radio beside the name.
 *
 * M3 shows both at once: every language entity is a `languageEntities` row, so
 * the path is one step for all five indexed types, and a Concept is told from a
 * DataType only by what two radios hold. ER1 shows neither: entities, pages and
 * fields are three different groups and nothing is discriminated, so the paths
 * differ and the conditions are empty.
 *
 * @since  1.2.0
 */
final class Placement
{
    /**
     * @param  Classifier                           $classifier   What is placed.
     * @param  string[]                             $path         Repeating groups to walk, from the root.
     * @param  array<int, array{path: string, value: string}>  $conditions   What a row must say it is.
     * @param  Classifier                           $rowType      The type whose form owns the row's name input.
     * @param  string                               $containment  The repeating group's field name.
     * @param  ?Classifier                          $parent       The classifier that contains it, if any.
     *
     * @since  1.2.0
     */
    public function __construct(
        public readonly Classifier $classifier,
        public readonly array $path,
        public readonly array $conditions,
        public readonly Classifier $rowType,
        public readonly string $containment,
        public readonly ?Classifier $parent
    ) {
    }

    /**
     * The same conditions, spelled as the browser reads them.
     *
     * A nesting step is `.` in a path through stored JSON and `__` in an
     * element id, and that is the only difference - which is exactly why
     * `ReferenceIndex` writes both down instead of deriving one at runtime:
     * knowing it is knowing how Joomla builds element ids, and that knowledge
     * belongs in one place rather than in every reader.
     *
     * @return array<int, array{token: string, value: string}>
     *
     * @since  1.2.0
     */
    public function clientConditions(): array
    {
        return array_map(
            static fn (array $condition): array => [
                'token' => str_replace('.', '__', $condition['path']),
                'value' => $condition['value'],
            ],
            $this->conditions
        );
    }
}
