<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Meta\LanguageStructure;
use Yepr\Component\Metagen\Administrator\Generator\Meta\ReferenceTable;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Component\Metagen\Administrator\Reference\LionCoreM3;
use Yepr\Gen\Core\Reference\ReferenceIndex;

/**
 * The generated reference table is the hand-written one.
 *
 * This is step 3.2's acceptance criterion for the half the plan calls "the
 * JavaScript the 1.9 mechanism needs", and it is not a judgement call, in the
 * same way 2.3's was not: `ReferenceIndex::PROJECT_FORM` describes LionCore M3
 * and was written by hand, months before this generator existed and without any
 * knowledge of it. `tests/Fixtures/languages/lioncore-m3.json` is LionCore M3
 * modelled in LionCore M3 - the meta-model describing itself - so generating a
 * table from it must produce that table back.
 *
 * It is checked through `ReferenceIndex` rather than against the constant,
 * because two arrays matching proves nothing if both are nonsense. What is
 * asserted is that the generated table *behaves* the same: it tells the browser
 * the same things, and it indexes a real stored model into the same answers.
 * The real stored model is `er1.json`, the same fixture
 * `MetalanguageReferenceTest` uses, so the two tests describe one thing.
 *
 * @since  1.2.0
 */
final class MetaReferenceTableTest extends TestCase
{
    /**
     * LionCore M3, modelled in LionCore M3.
     */
    private function metaModel(): ConceptModel
    {
        return ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/lioncore-m3.json')
        );
    }

    /**
     * A metalanguage written in that language, as the component stores one.
     */
    private function storedModel(): object
    {
        $json = (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/er1.json');

        return json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function generated(): array
    {
        return (new ReferenceTable(LanguageStructure::of($this->metaModel())))->build();
    }

    /**
     * The same object types, in the same order.
     *
     * Order matters more than it looks: the table is written to a file and
     * regenerating an unchanged language has to produce an unchanged file, or
     * every run shows up as a change in a diff nobody can read.
     */
    public function testItOffersTheSameObjectTypes(): void
    {
        $this->assertSame(
            ['Classifier', 'Concept', 'ConceptInterface', 'Annotation', 'DataType'],
            array_keys($this->generated())
        );
    }

    /**
     * Five entries for fourteen classifiers, because a table lists what can be
     * *chosen* rather than what a model can hold.
     */
    public function testItDescribesOnlyWhatSomethingPointsAt(): void
    {
        $classifiers = array_keys($this->metaModel()->classifiers());

        $this->assertGreaterThan(\count($this->generated()), \count($classifiers));
        $this->assertArrayNotHasKey('Feature', $this->generated());
        $this->assertArrayNotHasKey('EnumerationLiteral', $this->generated());
    }

    /**
     * What the browser is told is what it has always been told.
     */
    public function testTheClientHalfIsTheHandWrittenClientHalf(): void
    {
        $this->assertSame(
            ReferenceIndex::fromTable(LionCoreM3::TABLE)->clientTypes(),
            ReferenceIndex::fromTable($this->generated())->clientTypes()
        );
    }

    /**
     * And the server half indexes a real model into the same answers.
     *
     * The whole table, run over a stored metalanguage: every type, every
     * condition, every id and name. If the generated conditions filtered even
     * slightly differently - a Concept counted as a ConceptInterface, a
     * DataType counted as a Classifier - this is where it would show.
     */
    public function testItIndexesAStoredModelTheSameWay(): void
    {
        $stored = $this->storedModel();

        $this->assertSame(
            ReferenceIndex::fromTable(LionCoreM3::TABLE)->index($stored),
            ReferenceIndex::fromTable($this->generated())->index($stored)
        );
    }

    /**
     * Spelled out, so that a change to either side has to be looked at.
     *
     * The test above would keep passing if both halves broke the same way -
     * they read one table now, which is the point of generating it. This says
     * what the answer actually is.
     */
    public function testWhatItIndexesInTheFixture(): void
    {
        $index = ReferenceIndex::fromTable($this->generated())->index($this->storedModel());

        $names = static fn (string $type): array => array_map(
            static fn (array $entry): string => $entry['name'],
            $index[$type]
        );

        $this->assertSame(['Entity', 'Field'], $names('Concept'));
        $this->assertSame(['INamed'], $names('ConceptInterface'));
        $this->assertSame(['Deprecated'], $names('Annotation'));
        $this->assertSame(['INamed', 'Entity', 'Field', 'Deprecated'], $names('Classifier'));
        $this->assertSame(['String', 'Boolean'], $names('DataType'));
    }

    /**
     * A condition is written twice and the two spellings are one nesting apart.
     *
     * The generator derives both from one walk, which is the reason to generate
     * them at all: the hand-written pair could drift and the only symptom would
     * be a dropdown that is right until somebody changes a radio.
     */
    public function testEachClientConditionIsTheElementIdSpellingOfTheServerOne(): void
    {
        $checked = 0;

        foreach ($this->generated() as $type => $entry) {
            if (!isset($entry['when'])) {
                continue;
            }

            foreach ($entry['when'] as $index => $condition) {
                $client = $entry['client']['when'][$index];

                $this->assertSame(
                    str_replace('.', '__', $condition['path']),
                    $client['token'],
                    $type . "'s condition " . $index
                );
                $this->assertSame($condition['value'], $client['value']);

                $checked++;
            }
        }

        $this->assertGreaterThan(0, $checked, 'No conditions at all, which cannot be right for M3.');
    }

    /**
     * The other hand-written table, from a language shaped like ER1.
     *
     * M3 exercises one half of the mechanism and not the other: every type is a
     * `languageEntities` row, so every path is one step and every entry is told
     * apart by conditions. `ReferenceIndex::PROJECT` is the opposite shape -
     * three different groups, no conditions at all, and a child type nested
     * inside its parent's group with a `parentKey` and a `parentCut`.
     *
     * Modelling ER1 properly is 3.3. This is the part of it the containment
     * walk has to get right, written out as the smallest language that has the
     * shape: a root holding entities and pages, an entity holding fields, and a
     * field carrying which entity it belongs to.
     */
    public function testALanguageShapedLikeEr1ReproducesTheOtherHandWrittenTable(): void
    {
        $model = ConceptModel::fromJson((string) json_encode([
            'name'             => 'ER1',
            'languageEntities' => [
                'languageEntities0' => $this->datatype('String', 'dt-string'),
                'languageEntities1' => $this->concept('Project', 'c-project', [
                    $this->property('name', 'dt-string'),
                    $this->link('datamodel', 'c-entity', 'Containment', true),
                    $this->link('pages', 'c-page', 'Containment', true),
                ], ['partition' => '1']),
                'languageEntities2' => $this->concept('Entity', 'c-entity', [
                    $this->property('entity_id', 'dt-string'),
                    $this->property('entity_name', 'dt-string'),
                    $this->link('field', 'c-field', 'Containment', true),
                ]),
                'languageEntities3' => $this->concept('Page', 'c-page', [
                    $this->property('page_id', 'dt-string'),
                    $this->property('page_name', 'dt-string'),
                    // Something has to point at each of the three, or they are
                    // not in a table of what can be chosen.
                    $this->link('entity_reference', 'c-entity', 'Reference'),
                    $this->link('page_reference', 'c-page', 'Reference'),
                    $this->link('field_reference', 'c-field', 'Reference'),
                ]),
                'languageEntities4' => $this->concept('Field', 'c-field', [
                    $this->property('field_id', 'dt-string'),
                    $this->property('field_name', 'dt-string'),
                    $this->property('entity_id', 'dt-string'),
                ]),
            ],
        ]));

        $generated = (new ReferenceTable(LanguageStructure::of($model)))->build();

        $this->assertSame(['Entity', 'Page', 'Field'], array_keys($generated));

        // A child's path is its parent's group plus its own, and the element-id
        // cut that reaches its parent's row is that group's name twice - which
        // is how a field added a moment ago, whose entity_id is still blank,
        // finds out which entity it belongs to.
        $this->assertSame([
            'path'      => ['datamodel', 'field'],
            'idKey'     => 'field_id',
            'nameKey'   => 'field_name',
            'parentKey' => 'entity_id',
            'client'    => [
                'selector'    => 'fieldName',
                'nameToken'   => 'field_name',
                'idToken'     => 'field_id',
                'parentToken' => 'entity_id',
                'parentCut'   => '_field__field',
            ],
        ], $generated['Field']);

        // And a type at the top of the tree is scoped to nothing, because the
        // root is the record rather than a row anything could point at.
        $this->assertSame([
            'path'    => ['datamodel'],
            'idKey'   => 'entity_id',
            'nameKey' => 'entity_name',
            'client'  => [
                'selector'  => 'entityName',
                'nameToken' => 'entity_name',
                'idToken'   => 'entity_id',
            ],
        ], $generated['Entity']);

        $this->assertSame(['pages'], $generated['Page']['path']);
        $this->assertArrayNotHasKey('parentKey', $generated['Page']);

        // The hand-written table this reproduces is Exten-gen's
        // `Reference\Er1`, which is in Exten-gen; `Er1FormsTest` there checks
        // the generated forms against it, from the other side of the export.
    }

    /**
     * A stored `languageEntities` row holding a concept.
     *
     * @param  array<int, array<string, mixed>>  $features
     * @param  array<string, string>             $own
     *
     * @return array<string, mixed>
     */
    private function concept(string $name, string $key, array $features, array $own = []): array
    {
        return [
            'name'                => $name,
            'key'                 => $key,
            'languageEntity_type' => 'Classifier',
            'classifier'          => [
                'classifier_type' => 'Concept',
                'concept'         => $own,
                'feature'         => array_combine(
                    array_map(static fn (int $i): string => 'feature' . $i, array_keys($features)),
                    $features
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datatype(string $name, string $key): array
    {
        return [
            'name'                => $name,
            'key'                 => $key,
            'languageEntity_type' => 'DataType',
            'datatype'            => ['dataType_type' => 'PrimitiveType'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function property(string $name, string $type): array
    {
        return ['name' => $name, 'feature_type' => 'Property', 'property' => ['type' => $type]];
    }

    /**
     * @return array<string, mixed>
     */
    private function link(string $name, string $type, string $kind, bool $multiple = false): array
    {
        return [
            'name'         => $name,
            'feature_type' => 'Link',
            'link'         => [
                'type'        => $type,
                'link_type'   => $kind,
                'is_multiple' => $multiple ? '1' : '0',
            ],
        ];
    }

    /**
     * A language whose root reaches nothing indexes nothing, and says so.
     *
     * `er1.json` is that language: a sketch of ER1's vocabulary with no
     * partition and no containments, which is what a model looks like while
     * somebody is still writing it. Generating from it must not throw, and must
     * not quietly emit entries that would index nothing.
     */
    public function testALanguageWithNoTreeYetIsReportedRatherThanIndexed(): void
    {
        $sketch = ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/er1.json')
        );

        $table = new ReferenceTable(LanguageStructure::of($sketch));

        $this->assertSame([], $table->build());
        $this->assertNotSame([], $table->unplaced(), 'Nothing was reported as unreachable.');
        $this->assertContains('Entity', $table->unplaced());
    }
}
