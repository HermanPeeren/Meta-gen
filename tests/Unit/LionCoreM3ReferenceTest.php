<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Reference\LionCoreM3;
use Yepr\Gen\Core\Reference\ReferenceIndex;

/**
 * What a metalanguage offers a reference dropdown.
 *
 * The meta-model is LionCore M3 and it puts every object in one repeating
 * group: a language entity is a Classifier or a DataType, and a Classifier is
 * a Concept, a ConceptInterface or an Annotation. So unlike ER1 - where
 * entities, pages and fields live in three different places - every type here
 * is the same rows filtered differently, and filtering is the whole of what
 * can go wrong.
 *
 * The fixture is a small but real language: two datatypes, a concept interface,
 * two concepts (one extending the other), an annotation, and a row somebody has
 * added and not named yet, because real forms are full of those.
 */
final class LionCoreM3ReferenceTest extends TestCase
{
    private function model(): object
    {
        $json = (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/er1.json');

        return json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, list<array{id: string, name: string}>>
     */
    private function index(): array
    {
        return ReferenceIndex::fromTable(LionCoreM3::TABLE)->index($this->model());
    }

    /**
     * @param list<array<string, string>> $entries
     *
     * @return string[]
     */
    private function names(array $entries): array
    {
        return array_map(static fn (array $entry): string => $entry['name'], $entries);
    }

    public function testConceptsAreTheConceptsAndNothingElse(): void
    {
        $this->assertSame(['Entity', 'Field'], $this->names($this->index()['Concept']));
    }

    public function testAConceptInterfaceIsNotAConcept(): void
    {
        $this->assertSame(['INamed'], $this->names($this->index()['ConceptInterface']));
        $this->assertNotContains('INamed', $this->names($this->index()['Concept']));
    }

    /**
     * An annotation is a classifier too, and until 3.1 there was no form to
     * make one with - `classifier.xml` offered the choice and pointed at a
     * file that was never there.
     */
    public function testAnnotationsAreOfferedSeparately(): void
    {
        $this->assertSame(['Deprecated'], $this->names($this->index()['Annotation']));
    }

    /**
     * Classifier is the union of the three, which is what a link points at.
     */
    public function testClassifierIsEveryKindOfClassifier(): void
    {
        $this->assertSame(
            ['INamed', 'Entity', 'Field', 'Deprecated'],
            $this->names($this->index()['Classifier'])
        );
    }

    public function testDataTypesAreNotClassifiers(): void
    {
        $this->assertSame(['String', 'Boolean'], $this->names($this->index()['DataType']));

        foreach (['Classifier', 'Concept', 'ConceptInterface', 'Annotation'] as $type) {
            $this->assertNotContains('String', $this->names($this->index()[$type]));
        }
    }

    /**
     * The id is the key, because that is what a reference stores.
     */
    public function testEntriesAreIdentifiedByTheirKey(): void
    {
        $this->assertSame(
            [['id' => 'c-entity', 'name' => 'Entity'], ['id' => 'c-field', 'name' => 'Field']],
            $this->index()['Concept']
        );
    }

    /**
     * A row with no key yet is left out rather than offered as a blank line.
     *
     * The client assigns one the moment somebody names it; until then there is
     * nothing for a reference to hold.
     */
    public function testARowWithNoKeyIsNotOffered(): void
    {
        $names = $this->names($this->index()['Concept']);

        $this->assertNotContains('', $names);
        $this->assertCount(2, $names);
    }

    public function testAMetalanguageThatWasNeverSavedIndexesToNothing(): void
    {
        $index = ReferenceIndex::fromTable(LionCoreM3::TABLE)->index(null);

        $this->assertSame(
            ['Classifier', 'Concept', 'ConceptInterface', 'Annotation', 'DataType'],
            array_keys($index)
        );

        foreach ($index as $type => $entries) {
            $this->assertSame([], $entries, $type . ' should be empty.');
        }
    }

    /**
     * Every type the client is told about is one the server indexes.
     *
     * The two halves of the table are written side by side, which is exactly
     * the arrangement in which one of them gets edited and the other does not.
     */
    public function testTheClientIsToldAboutTheSameTypes(): void
    {
        $this->assertSame(
            array_keys($this->index()),
            array_keys(ReferenceIndex::fromTable(LionCoreM3::TABLE)->clientTypes())
        );
    }

    /**
     * And every client condition is the element-id spelling of a server one.
     *
     * `classifier.classifier_type` reads a stored model; the browser reads the
     * same value from an input whose id ends `classifier__classifier_type`.
     * Neither can be derived from the other, so both are written down - and
     * this is what keeps them describing the same thing.
     */
    public function testEachClientConditionMirrorsTheServerCondition(): void
    {
        $expected = [
            'Classifier'       => [['languageEntity_type', 'Classifier']],
            'Concept'          => [
                ['languageEntity_type', 'Classifier'],
                ['classifier__classifier_type', 'Concept'],
            ],
            'ConceptInterface' => [
                ['languageEntity_type', 'Classifier'],
                ['classifier__classifier_type', 'ConceptInterface'],
            ],
            'Annotation'       => [
                ['languageEntity_type', 'Classifier'],
                ['classifier__classifier_type', 'Annotation'],
            ],
            'DataType'         => [['languageEntity_type', 'DataType']],
        ];

        foreach (ReferenceIndex::fromTable(LionCoreM3::TABLE)->clientTypes() as $type => $descriptor) {
            $this->assertArrayHasKey('when', $descriptor, $type . ' has no conditions.');

            $actual = array_map(
                static fn (array $c): array => [$c['token'], $c['value']],
                $descriptor['when']
            );

            $this->assertSame($expected[$type], $actual, $type . "'s client conditions");

            // The two spellings differ in one way only: a nesting step is `__`
            // in an element id and `.` in a path. Anything else means the
            // browser and the server are filtering on different things, which
            // shows up as a dropdown that is right until somebody edits it.
            foreach ($descriptor['when'] as $condition) {
                $this->assertMatchesRegularExpression(
                    '/^[a-zA-Z_]+(__[a-zA-Z_]+)*$/',
                    $condition['token'],
                    $type . ' has a token that is not an element-id path'
                );
            }
        }

        // And the filtering those conditions describe is the filtering that
        // happens, which the tests above check against a real model.
        $this->assertSame(array_keys($expected), array_keys($this->index()));
    }
}
