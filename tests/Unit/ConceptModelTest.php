<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
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

    public function testSomethingThatIsNotAnObjectIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConceptModel::fromJson('[]');
    }
}
