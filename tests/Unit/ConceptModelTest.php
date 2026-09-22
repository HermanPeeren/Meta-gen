<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Model\Classifier;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;

/**
 * Reading a modelled language out of what Joomla's form layer stored.
 *
 * The stored shape is a form's shape, not a model's, and every awkward thing
 * about it is a thing somebody would otherwise get wrong once per generator.
 * A checkbox that was never ticked is *absent* rather than "0". A repeating
 * group is an object keyed `languageEntities0`. A feature that is a property
 * still carries an empty `link` group beside it. A row somebody added and
 * walked away from has no key and no name at all.
 *
 * `Project` exists for the same reason on the ER1 side, and its docblock makes
 * the same point: the cost was never the typing, it was that a question had
 * nowhere to be asked.
 *
 * @since  1.2.0
 */
final class ConceptModelTest extends TestCase
{
    private function er1(): ConceptModel
    {
        return ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/er1.json')
        );
    }

    private function m3(): ConceptModel
    {
        return ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/lioncore-m3.json')
        );
    }

    public function testItReadsTheLanguagesName(): void
    {
        $this->assertSame('ER1', $this->er1()->name());
        $this->assertSame('LIonCore_M3', $this->m3()->name());
    }

    /**
     * Classifiers and datatypes are kept apart, because only one has a form.
     */
    public function testItTellsClassifiersFromDatatypes(): void
    {
        $model = $this->er1();

        $this->assertSame(
            ['INamed', 'Entity', 'Field', 'Deprecated'],
            array_map(static fn ($c): string => $c->name, array_values($model->classifiers()))
        );

        $this->assertSame(
            ['String', 'Boolean'],
            array_map(static fn ($d): string => $d->name, array_values($model->dataTypes()))
        );
    }

    /**
     * And which of the three kinds each classifier is.
     */
    public function testItReadsWhichKindOfClassifierEachIs(): void
    {
        $model = $this->er1();

        $this->assertSame('ConceptInterface', $model->classifier('ci-inamed')?->kind);
        $this->assertSame('Concept', $model->classifier('c-entity')?->kind);
        $this->assertSame('Annotation', $model->classifier('a-deprecated')?->kind);
    }

    /**
     * A row with no key yet is not a classifier anything can name.
     *
     * `er1.json` carries one on purpose - somebody pressed add and stopped -
     * because real stored forms are full of them, and the same rule keeps it
     * out of `ReferenceIndex`.
     */
    public function testARowWithNoKeyIsNotAClassifier(): void
    {
        $this->assertCount(4, $this->er1()->classifiers());
    }

    /**
     * A checkbox that was never ticked is absent, and absent means false.
     */
    public function testAnUntickedCheckboxReadsAsFalse(): void
    {
        $entity = $this->er1()->classifier('c-entity');

        $this->assertNotNull($entity);
        $this->assertFalse($entity->abstract);
        $this->assertFalse($entity->partition);
    }

    /**
     * A property's type comes from `property`, a link's from `link`.
     *
     * Both are called `type`, which is the one thing about the two that is the
     * same, and a feature carries whichever group it is not as well.
     */
    public function testAFeatureTakesItsTypeFromTheGroupThatMatches(): void
    {
        $inamed = $this->er1()->classifier('ci-inamed');
        $field  = $this->er1()->classifier('c-field');

        $this->assertNotNull($inamed);
        $this->assertNotNull($field);

        $name = $inamed->features[0];

        $this->assertTrue($name->isProperty());
        $this->assertSame('dt-string', $name->typeKey);

        $owner = $field->features[0];

        $this->assertTrue($owner->isReference());
        $this->assertSame('c-entity', $owner->typeKey);
    }

    /**
     * The root is the classifier a model of the language hangs off.
     */
    public function testTheRootIsThePartition(): void
    {
        $this->assertSame('Language', $this->m3()->root()?->name);

        // ER1's sketch has no partition, which is an ordinary state for a model
        // halfway through being written rather than an error.
        $this->assertNull($this->er1()->root());
    }

    /**
     * Inheritance arrives from `extends` and from `implements` alike.
     *
     * Somebody filling in a form cannot tell which of the two a field came
     * from, and neither should a generator.
     */
    public function testAClassifierHasWhatItExtendsAndWhatItImplements(): void
    {
        $model = $this->er1();
        $field = $model->classifier('c-field');

        $this->assertNotNull($field);
        $this->assertSame('c-entity', $field->extendsKey);
        $this->assertSame(['ci-inamed'], $field->implements);

        // INamed's `name`, Entity's `isValueObject`, and its own `owner`.
        $this->assertSame(
            ['name', 'isValueObject', 'owner'],
            array_map(static fn ($f): string => $f->name, $model->featuresOf($field))
        );
    }

    /**
     * A concept that extends itself, round however long a loop, still answers.
     *
     * Not a hypothetical: it is one keystroke away in a dropdown that offers
     * every concept in the language, including the one being edited. Without
     * this the form for it never finishes generating.
     */
    public function testACycleInTheExtendsChainTerminates(): void
    {
        $model = ConceptModel::fromJson((string) json_encode([
            'name'             => 'Loop',
            'languageEntities' => [
                'languageEntities0' => [
                    'name'                => 'A',
                    'key'                 => 'a',
                    'languageEntity_type' => 'Classifier',
                    'classifier'          => [
                        'classifier_type' => 'Concept',
                        'concept'         => ['extends' => 'b'],
                    ],
                ],
                'languageEntities1' => [
                    'name'                => 'B',
                    'key'                 => 'b',
                    'languageEntity_type' => 'Classifier',
                    'classifier'          => [
                        'classifier_type' => 'Concept',
                        'concept'         => ['extends' => 'a'],
                    ],
                ],
            ],
        ]));

        $a = $model->classifier('a');

        $this->assertNotNull($a);
        $this->assertSame([], $model->featuresOf($a));
    }

    /**
     * A label is what a person reads; the name is what everything else uses.
     *
     * Until 3.3 the only human text in a metalanguage was an identifier, so a
     * generated form read `isValueObject` where it should read "Is this a value
     * object?". Both are optional and fall back to the name, which is what keeps
     * every model written before then rendering exactly as it did - `er1.json`
     * is one of those, and it carries no label anywhere.
     */
    public function testALabelIsReadWhenThereIsOneAndTheNameWhenThereIsNot(): void
    {
        $model = ConceptModel::fromJson((string) json_encode([
            'name'             => 'Labelled',
            'languageEntities' => [
                'languageEntities0' => [
                    'name'                => 'Entity',
                    'key'                 => 'c-entity',
                    'label'               => 'Thing',
                    'description'         => 'Something the model holds',
                    'languageEntity_type' => 'Classifier',
                    'classifier'          => [
                        'classifier_type' => 'Concept',
                        'concept'         => [],
                        'feature'         => [
                            'feature0' => [
                                'name'         => 'isValueObject',
                                'label'        => 'Is this a value object?',
                                'feature_type' => 'Property',
                                'property'     => ['type' => 'dt-string'],
                            ],
                            'feature1' => [
                                'name'         => 'unlabelled',
                                'feature_type' => 'Property',
                                'property'     => ['type' => 'dt-string'],
                            ],
                        ],
                    ],
                ],
            ],
        ]));

        $entity = $model->classifier('c-entity');

        $this->assertNotNull($entity);
        $this->assertSame('Thing', $entity->displayLabel());
        $this->assertSame('Entity', $entity->name, 'the name is still what a reference resolves by');
        $this->assertSame('Something the model holds', $entity->description);

        $this->assertSame('Is this a value object?', $entity->features[0]->displayLabel());
        $this->assertSame('unlabelled', $entity->features[1]->displayLabel());
    }

    /**
     * And a model written before labels existed reads exactly as it did.
     */
    public function testAModelWithNoLabelsAnywhereFallsBackThroughout(): void
    {
        $model = $this->er1();

        foreach ($model->classifiers() as $classifier) {
            $this->assertSame($classifier->name, $classifier->displayLabel());

            foreach ($classifier->features as $feature) {
                $this->assertSame($feature->name, $feature->displayLabel());
            }
        }
    }

    public function testSomethingThatIsNotAnObjectIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConceptModel::fromJson('[]');
    }

    // -- one name, two features ----------------------------------------------

    /**
     * A language of interfaces and one concept implementing them.
     *
     * Written out rather than added to `er1.json`, because the fixtures are
     * what a real metalanguage looks like and these are the shapes a real one
     * should not have.
     *
     * @param  array<string, array{key: string, name: string}[]>  $interfaces
     * @param  array{key: string, name: string}[]                 $own
     */
    private function implementing(array $interfaces, array $own = []): ConceptModel
    {
        // Keyed `feature0`, `feature1`, because that is how Joomla stores a
        // repeating group and the reader insists on it.
        $group = static function (array $features): array {
            $rows = [];

            foreach ($features as $position => $f) {
                $rows['feature' . $position] = [
                    'name'         => $f['name'],
                    'key'          => $f['key'],
                    'feature_type' => 'Property',
                    'property'     => ['type' => 'dt-string'],
                ];
            }

            return $rows;
        };

        $entities = [];
        $index    = 0;

        foreach ($interfaces as $key => $features) {
            $entities['languageEntities' . $index++] = [
                'name'                => ucfirst(ltrim($key, 'ci-')),
                'key'                 => $key,
                'languageEntity_type' => 'Classifier',
                'classifier'          => [
                    'classifier_type'  => 'ConceptInterface',
                    'conceptInterface' => ['extends' => ''],
                    'feature'          => $group($features),
                ],
            ];
        }

        $implements = [];

        foreach (array_keys($interfaces) as $position => $key) {
            $implements['implements' . $position] = ['conceptInterface' => $key];
        }

        $entities['languageEntities' . $index] = [
            'name'                => 'Thing',
            'key'                 => 'c-thing',
            'languageEntity_type' => 'Classifier',
            'classifier'          => [
                'classifier_type' => 'Concept',
                'concept'         => ['extends' => '', 'implements' => $implements],
                'feature'         => $group($own),
            ],
        ];

        return ConceptModel::fromJson(
            (string) json_encode(['name' => 'Clash', 'languageEntities' => $entities])
        );
    }

    private function thing(ConceptModel $model): Classifier
    {
        $thing = $model->classifier('c-thing');

        $this->assertNotNull($thing);

        return $thing;
    }

    /**
     * A diamond is one feature, because one key is one feature.
     *
     * Two interfaces that both carry the same feature - the same key, because
     * it is the same feature - meet on a concept implementing both. Reaching it
     * twice does not make it two, and a form must not offer it twice.
     */
    public function testAFeatureReachedTwiceIsStillOneFeature(): void
    {
        $shared = [['key' => 'f-name', 'name' => 'name']];
        $model  = $this->implementing(['ci-one' => $shared, 'ci-two' => $shared]);
        $thing  = $this->thing($model);

        $this->assertSame(
            ['name'],
            array_map(static fn ($f): string => $f->name, $model->featuresOf($thing))
        );
        $this->assertSame([], $model->featureNameClashes($thing));
    }

    /**
     * Two genuinely different features under one name are reported.
     *
     * This is what deduplicating by name could not see: the keys differ, so
     * these are two features, and a form has one field for them. Before the
     * clash was recorded the second one simply stopped existing somewhere
     * between the language and the form, and nothing said so.
     */
    public function testTwoDifferentFeaturesUnderOneNameAreReported(): void
    {
        $model = $this->implementing([
            'ci-one' => [['key' => 'f-one-name', 'name' => 'name']],
            'ci-two' => [['key' => 'f-two-name', 'name' => 'name']],
        ]);
        $thing = $this->thing($model);

        $this->assertSame(
            ['name' => ['f-one-name', 'f-two-name']],
            $model->featureNameClashes($thing)
        );

        // Still one field per name: the generators are owed that, and which one
        // wins is the same as it always was.
        $features = $model->featuresOf($thing);

        $this->assertCount(1, $features);
        $this->assertSame('f-two-name', $features[0]->key);
    }

    /**
     * A concept redeclaring an inherited name is a clash like any other.
     *
     * LionCore has no feature overriding - a redeclaration is a second feature
     * with a second key - so the honest thing is to say two arrived. It still
     * wins, and it still keeps the position the inherited one held, because
     * that is what the form has always done and reporting is not refusing.
     */
    public function testARedeclaredFeatureWinsAndIsStillReported(): void
    {
        $model = $this->implementing(
            ['ci-one' => [
                ['key' => 'f-one-name', 'name' => 'name'],
                ['key' => 'f-one-size', 'name' => 'size'],
            ]],
            [['key' => 'f-thing-name', 'name' => 'name']]
        );
        $thing = $this->thing($model);

        $this->assertSame(
            ['name' => ['f-one-name', 'f-thing-name']],
            $model->featureNameClashes($thing)
        );

        $features = $model->featuresOf($thing);

        $this->assertSame(
            ['name', 'size'],
            array_map(static fn ($f): string => $f->name, $features),
            'the redeclared feature holds the place the inherited one had'
        );
        $this->assertSame('f-thing-name', $features[0]->key);
    }

    /**
     * A row with no key yet identifies by name, as every feature once did.
     *
     * Somebody pressed add and started typing. There is nothing to identify it
     * by, so it falls back to the name - and it is not reported, because a
     * half-filled row is not a language with a problem in it.
     */
    public function testAFeatureWithNoKeyIdentifiesByNameAndIsNotReported(): void
    {
        $model = $this->implementing(
            ['ci-one' => [['key' => '', 'name' => 'name']]],
            [['key' => '', 'name' => 'name']]
        );
        $thing = $this->thing($model);

        $this->assertCount(1, $model->featuresOf($thing));
        $this->assertSame([], $model->featureNameClashes($thing));
    }

    /**
     * And the fixtures, which are what a metalanguage should look like, have
     * none - including LionCore M3, where eight classifiers inherit.
     */
    public function testTheFixtureLanguagesHaveNoClashes(): void
    {
        foreach ([$this->er1(), $this->m3()] as $model) {
            foreach ($model->classifiers() as $classifier) {
                $this->assertSame(
                    [],
                    $model->featureNameClashes($classifier),
                    $classifier->name . ' gathers two features under one name'
                );
            }
        }
    }
}
