<?php

/**
 * @package     Metagen
 * @subpackage  Reference
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Reference;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What a metalanguage offers a reference dropdown, in LionCore M3.
 *
 * The mechanism that reads this is `Yepr\Gen\Core\Reference\ReferenceIndex` in
 * the shared library, because three components ask the same question of three
 * different models. What differs between them is this table and nothing else,
 * so it lives with the component that owns the language it describes.
 *
 * **Every type here is the same rows filtered differently.** M3 puts every
 * object in one repeating group: a language entity is a Classifier or a
 * DataType, and a Classifier is a Concept, a ConceptInterface or an Annotation.
 * So unlike a language with several groups, the path is one step for all five
 * and the whole of the work is in the conditions.
 *
 * A condition is written twice, once as a path through the stored JSON and once
 * as a token in an element id, because neither spelling can be derived from the
 * other without knowing how Joomla builds element ids. The server reads
 * `classifier.classifier_type`; the browser reads the same value out of an
 * input whose element id ends `classifier__classifier_type`.
 *
 * **This is hand-written and it is the last one that needs to be.** Meta-gen
 * generates exactly this table from a concept model - `Generator\Meta\ReferenceTable`
 * does, and `MetaReferenceTableTest` proves it by reproducing this table from
 * LionCore M3 modelled in LionCore M3. It stays written out because it
 * describes the language Meta-gen itself is written in, which is the one
 * language that cannot arrive as a generated package without a bootstrap.
 *
 * @since  0.1.0
 */
final class LionCoreM3
{
    /**
     * The table, as `ReferenceIndex::fromTable()` reads one.
     *
     * @var array<string, array<string, mixed>>
     *
     * @since  0.1.0
     */
    public const TABLE = [
        'Classifier' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [['path' => 'languageEntity_type', 'value' => 'Classifier']],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [['token' => 'languageEntity_type', 'value' => 'Classifier']],
            ],
        ],
        'Concept' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [
                ['path' => 'languageEntity_type', 'value' => 'Classifier'],
                ['path' => 'classifier.classifier_type', 'value' => 'Concept'],
            ],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [
                    ['token' => 'languageEntity_type', 'value' => 'Classifier'],
                    ['token' => 'classifier__classifier_type', 'value' => 'Concept'],
                ],
            ],
        ],
        'ConceptInterface' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [
                ['path' => 'languageEntity_type', 'value' => 'Classifier'],
                ['path' => 'classifier.classifier_type', 'value' => 'ConceptInterface'],
            ],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [
                    ['token' => 'languageEntity_type', 'value' => 'Classifier'],
                    ['token' => 'classifier__classifier_type', 'value' => 'ConceptInterface'],
                ],
            ],
        ],
        'Annotation' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [
                ['path' => 'languageEntity_type', 'value' => 'Classifier'],
                ['path' => 'classifier.classifier_type', 'value' => 'Annotation'],
            ],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [
                    ['token' => 'languageEntity_type', 'value' => 'Classifier'],
                    ['token' => 'classifier__classifier_type', 'value' => 'Annotation'],
                ],
            ],
        ],
        'DataType' => [
            'path'    => ['languageEntities'],
            'idKey'   => 'key',
            'nameKey' => 'name',
            'when'    => [['path' => 'languageEntity_type', 'value' => 'DataType']],
            'client'  => [
                'selector'  => 'languageEntityName',
                'nameToken' => 'name',
                'idToken'   => 'key',
                'when'      => [['token' => 'languageEntity_type', 'value' => 'DataType']],
            ],
        ],
    ];
}
