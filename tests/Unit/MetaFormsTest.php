<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Meta\FormXml;
use Yepr\Component\Metagen\Administrator\Generator\Meta\LanguageStructure;
use Yepr\Component\Metagen\Administrator\Generator\Meta\ReferenceTable;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Output\FileCollection;

/**
 * The forms of a modelled language, generated from the language.
 *
 * Step 3.2, checked the way 2.3 checked a generated generator: against output
 * that already exists and was written by hand. The fixture is LionCore M3
 * modelled in LionCore M3, and the meta-model it describes is in this
 * repository as fifteen form files somebody wrote years before this generator
 * did - so "does the generator produce the meta-model" is a question with an
 * answer rather than an opinion.
 *
 * **It is not byte-identical, and is not claimed to be.** Four kinds of
 * difference are real and each is recorded in the plan under 3.2: presentation
 * attributes a concept model cannot hold (`size="1"`, a `custom-select` class),
 * the Joomla item chrome on the root form (published, access, ordering), the
 * naming of language strings, and two places where the hand-written files are
 * internally inconsistent and the generated ones are not. Reconciling them is
 * 3.3, which is where the comparison becomes golden files.
 *
 * What is asserted here is everything a form has to get *right*: the set of
 * files, what each one holds, how subtyping is laid out, where references
 * point, and that the result is a form Joomla can load.
 *
 * @since  1.2.0
 */
final class MetaFormsTest extends TestCase
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
     * Everything one generation run produces, keyed by path.
     *
     * @return array<string, string>
     */
    private function generate(?ConceptModel $model = null): array
    {
        $files = new FileCollection();

        (new Forms())->generate($model ?? $this->metaModel(), $files);

        return $files->all();
    }

    /**
     * One generated form, parsed.
     */
    private function form(string $name): \SimpleXMLElement
    {
        $path  = MetalanguagePackage::FORMS . $name . '.xml';
        $files = $this->generate();

        $this->assertArrayHasKey($path, $files, $name . '.xml was not generated at all.');

        $xml = simplexml_load_string($files[$path]);

        $this->assertNotFalse($xml, $name . '.xml is not valid XML.');

        return $xml;
    }

    /**
     * One field of a generated form, by name.
     */
    private function field(string $form, string $name): \SimpleXMLElement
    {
        $found = $this->form($form)->xpath('//field[@name="' . $name . '"]');

        $this->assertNotEmpty($found, $form . '.xml has no field called ' . $name . '.');

        return $found[0];
    }

    /**
     * The values a radio offers, in order.
     *
     * By xpath rather than by iterating the element: SimpleXML keys repeated
     * children by tag name, so iterating gives one entry called `option`
     * holding the last of them.
     *
     * @return string[]
     */
    private function optionValues(\SimpleXMLElement $radio): array
    {
        return array_map(
            static fn (\SimpleXMLElement $o): string => (string) $o['value'],
            $radio->xpath('option') ?: []
        );
    }

    /**
     * A form for every classifier, named after it, plus the table beside them.
     *
     * The names are the hand-written meta-model's own file names. That is not a
     * coincidence to be pleased about - it is the naming convention read off
     * those files and applied back - but it is the thing 3.3 needs in order to
     * compare the two sets at all.
     */
    public function testItGeneratesAFormForEveryClassifier(): void
    {
        $paths = array_map(
            static fn (string $path): string => basename($path),
            array_keys($this->generate())
        );

        sort($paths);

        $this->assertSame([
            'annotation.xml',
            'classifier.xml',
            'concept.xml',
            'conceptInterface.xml',
            'conceptInterfaceReference.xml',
            'containment.xml',
            'dataType.xml',
            'enumeration.xml',
            'enumerationLiteral.xml',
            'feature.xml',
            'language.xml',
            'languageEntity.xml',
            'link.xml',
            'lioncore_m3.ini',
            'primitiveType.xml',
            'property.xml',
            'reference.xml',
            'references.json',
        ], $paths);
    }

    /**
     * Every generated form parses, and every one is a Joomla form.
     */
    public function testEveryGeneratedFormIsAFormJoomlaCanLoad(): void
    {
        $checked = 0;

        foreach ($this->generate() as $path => $contents) {
            if (!str_ends_with($path, '.xml')) {
                continue;
            }

            $xml = simplexml_load_string($contents);

            $this->assertNotFalse($xml, $path . ' is not valid XML.');
            $this->assertSame('form', $xml->getName(), $path . ' is not a <form>.');
            $this->assertNotEmpty(
                $xml->xpath('/form/fieldset[@addfieldprefix]'),
                $path . ' has no fieldset declaring where its field types live.'
            );

            $checked++;
        }

        $this->assertSame(16, $checked);
    }

    /**
     * Subtyping is laid out as a radio and a subform each, not as copied fields.
     *
     * `languageEntity.xml` is the case that matters: one repeating group holds
     * five kinds of thing, and what tells them apart is what the row says it
     * is. Copying a parent's fields into each subtype instead would post two
     * inputs to one key and the later would silently win.
     */
    public function testAClassifierWithSubtypesGetsARadioAndASubformEach(): void
    {
        $radio = $this->field('languageEntity', 'languageEntity_type');

        $this->assertSame('radio', (string) $radio['type']);
        $this->assertSame(['Classifier', 'DataType'], $this->optionValues($radio));

        foreach (['classifier' => 'Classifier', 'dataType' => 'DataType'] as $group => $value) {
            $field = $this->field('languageEntity', $group);

            $this->assertSame('subform', (string) $field['type']);
            $this->assertSame('languageEntity_type:' . $value, (string) $field['showon']);
            $this->assertStringEndsWith('/' . $group . '.xml', (string) $field['formsource']);
        }
    }

    /**
     * An abstract classifier is not one of its own choices; a concrete one is.
     *
     * `classifier_type` in the hand-written model offers Concept,
     * ConceptInterface and Annotation and not Classifier, because a row cannot
     * be a Classifier and nothing more specific. That is what abstract means,
     * and it is the only thing in the model that decides it.
     */
    public function testAnAbstractClassifierIsNotOfferedAsOneOfItsOwnKinds(): void
    {
        $radio = $this->field('classifier', 'classifier_type');

        $this->assertSame(['Concept', 'ConceptInterface', 'Annotation'], $this->optionValues($radio));
        $this->assertSame('Concept', (string) $radio['default']);
    }

    /**
     * A subtype's form carries its own fields and none of its parent's.
     */
    public function testASubtypeFormHoldsOnlyItsOwnFields(): void
    {
        $names = array_map(
            static fn ($f): string => (string) $f['name'],
            $this->form('concept')->xpath('//field') ?: []
        );

        $this->assertSame(['abstract', 'partition', 'extends', 'implements', 'LIonWeb_key'], $names);

        // `name` and `key` are LanguageEntity's, two forms up, and a Concept
        // row shows them because it is a LanguageEntity row.
        $this->assertNotContains('name', $names);
        $this->assertNotContains('key', $names);
    }

    /**
     * A reference feature becomes the dropdown 1.9 built, told what it offers.
     */
    public function testAReferenceBecomesAReferenceField(): void
    {
        $extends = $this->field('concept', 'extends');

        $this->assertSame('Reference', (string) $extends['type']);
        $this->assertSame('Concept', (string) $extends['objecttype']);

        $this->assertSame('Classifier', (string) $this->field('link', 'type')['objecttype']);
        $this->assertSame('DataType', (string) $this->field('property', 'type')['objecttype']);
        $this->assertSame('Classifier', (string) $this->field('annotation', 'annotates')['objecttype']);
    }

    /**
     * Every objecttype a generated form names is one the generated table carries.
     *
     * `ReferenceContractTest` asks this of the hand-written forms and says why:
     * nothing at runtime checks it, and an unknown type renders an empty
     * dropdown with no error anywhere. Generated together, the two cannot
     * disagree - and this is what says so rather than assuming it.
     */
    public function testEveryObjectTypeAFormNamesIsInTheGeneratedTable(): void
    {
        $model = $this->metaModel();
        $table = (new ReferenceTable(LanguageStructure::of($model)))->build();
        $found = 0;

        foreach ($this->generate($model) as $path => $contents) {
            if (!str_ends_with($path, '.xml')) {
                continue;
            }

            $xml = simplexml_load_string($contents);

            foreach (($xml === false ? [] : $xml->xpath('//field[@objecttype]')) ?: [] as $field) {
                $found++;

                $this->assertArrayHasKey(
                    (string) $field['objecttype'],
                    $table,
                    basename($path) . ' points at ' . $field['objecttype'] . ', which the table does not carry.'
                );
            }
        }

        $this->assertGreaterThan(0, $found, 'No reference fields at all, which cannot be right.');
    }

    /**
     * And every class the table tells the browser to look for is on a name input.
     *
     * The join with no other check, in `ReferenceContractTest`'s words: a
     * string in the table and a class attribute in XML. If they part company
     * the dropdown stops seeing rows that are on screen but not yet saved,
     * which is the exact failure 1.9 existed to fix, returning silently.
     */
    public function testEverySelectorTheTableNamesIsOnAGeneratedNameInput(): void
    {
        $model   = $this->metaModel();
        $table   = (new ReferenceTable(LanguageStructure::of($model)))->build();
        $classes = [];

        foreach ($this->generate($model) as $path => $contents) {
            if (!str_ends_with($path, '.xml')) {
                continue;
            }

            $xml = simplexml_load_string($contents);

            foreach (($xml === false ? [] : $xml->xpath('//field[@class]')) ?: [] as $field) {
                foreach (preg_split('/\s+/', (string) $field['class']) ?: [] as $class) {
                    $classes[$class] = true;
                }
            }
        }

        foreach ($table as $type => $entry) {
            $this->assertArrayHasKey(
                $entry['client']['selector'],
                $classes,
                $type . ' is found by "' . $entry['client']['selector'] . '", which no generated form puts anywhere.'
            );
        }
    }

    /**
     * The class goes on the row's own name input and on no subtype's.
     *
     * The browser finds one element per row. A subtype repeating it would mean
     * finding the same row twice under two names, and `liveEntries` would read
     * it twice - the second read overwriting the first with whatever that
     * form's inputs happened to hold.
     */
    public function testOnlyTheRowTypeCarriesTheSelectorClass(): void
    {
        $this->assertSame('languageEntityName', (string) $this->field('languageEntity', 'name')['class']);

        foreach (['enumerationLiteral', 'feature'] as $form) {
            foreach ($this->form($form)->xpath('//field[@class]') ?: [] as $field) {
                $this->assertStringNotContainsString(
                    'languageEntityName',
                    (string) $field['class'],
                    $form . '.xml claims to hold a LanguageEntity row.'
                );
            }
        }
    }

    /**
     * A repeating containment becomes a repeating subform pointing at its target.
     */
    public function testAContainmentBecomesASubform(): void
    {
        $entities = $this->field('language', 'languageEntities');

        $this->assertSame('subform', (string) $entities['type']);
        $this->assertSame('true', (string) $entities['multiple']);
        $this->assertStringEndsWith('/languageEntity.xml', (string) $entities['formsource']);
    }

    /**
     * Every subform points at a form the same run produced.
     *
     * The rule `FormsTest` applies to the hand-written set, applied to a
     * generated one - and it is the defect that hid the longest there:
     * `classifier.xml` pointed at an `annotation.xml` that was never written,
     * and Joomla renders a `formsource` it cannot resolve as an empty box.
     */
    public function testEverySubformSourceIsAFileTheRunProduced(): void
    {
        $files   = $this->generate();
        $missing = [];
        $root    = MetalanguagePackage::installRoot('LIonCore_M3', '2023.1');

        foreach ($files as $path => $contents) {
            if (!str_ends_with($path, '.xml')) {
                continue;
            }

            $xml = simplexml_load_string($contents);

            foreach (($xml === false ? [] : $xml->xpath('//field[@formsource]')) ?: [] as $field) {
                $source = (string) $field['formsource'];

                // Joomla resolves a `formsource` against JPATH_ROOT and
                // nothing else, so every one of these is the directory the
                // package declares it will be unpacked into, plus a path
                // inside it. A source that does not start with that root is a
                // subform pointing outside its own package, which is the worse
                // half of what this rule is for.
                if (!str_starts_with($source, $root)) {
                    $missing[] = basename($path) . ' -> ' . $source . ' (outside the package)';

                    continue;
                }

                if (!isset($files[substr($source, \strlen($root))])) {
                    $missing[] = basename($path) . ' -> ' . $source;
                }
            }
        }

        $this->assertSame([], $missing, "Subforms pointing at nothing:\n  " . implode("\n  ", $missing));
    }

    /**
     * A property's datatype decides its input, and an unknown one is a text box.
     */
    public function testAPropertyTakesItsInputFromItsDatatype(): void
    {
        $this->assertSame('text', (string) $this->field('languageEntity', 'name')['type']);
        $this->assertSame('checkbox', (string) $this->field('concept', 'abstract')['type']);
        $this->assertSame('checkbox', (string) $this->field('link', 'is_multiple')['type']);
    }

    /**
     * A node says what it is, with the name its extends chain spells out.
     *
     * Every one of these is in the hand-written meta-model, written by hand in
     * fifteen separate files. None of them is in the concept model: the stored
     * `LIonWeb_key` on a row says the row is a LanguageEntity, which is true of
     * every row and not what a form needs. They are derived.
     */
    public function testEachFormCarriesTheQualifiedNameOfItsClassifier(): void
    {
        $expected = [
            'language'                  => 'Language',
            'languageEntity'            => 'LanguageEntity',
            'classifier'                => 'LanguageEntity.Classifier',
            'concept'                   => 'LanguageEntity.Classifier.Concept',
            'conceptInterface'          => 'LanguageEntity.Classifier.ConceptInterface',
            'annotation'                => 'LanguageEntity.Classifier.Annotation',
            'dataType'                  => 'LanguageEntity.DataType',
            'primitiveType'             => 'LanguageEntity.DataType.PrimitiveType',
            'enumeration'               => 'LanguageEntity.DataType.Enumeration',
            'enumerationLiteral'        => 'EnumerationLiteral',
            'feature'                   => 'Feature',
            'property'                  => 'Feature.Property',
            'link'                      => 'Feature.Link',
            'containment'               => 'Feature.Link.Containment',
            'reference'                 => 'Feature.Link.Reference',
            'conceptInterfaceReference' => 'ConceptInterfaceReference',
        ];

        foreach ($expected as $form => $key) {
            $this->assertSame($key, (string) $this->field($form, 'LIonWeb_key')['value'], $form . '.xml');
        }
    }

    /**
     * The table is written beside the forms, as JSON somebody can read.
     */
    public function testTheReferenceTableIsWrittenBesideTheForms(): void
    {
        $path  = Forms::TABLE_FILE;
        $files = $this->generate();

        $this->assertArrayHasKey($path, $files);

        $decoded = json_decode($files[$path], true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(
            (new ReferenceTable(LanguageStructure::of($this->metaModel())))->build(),
            $decoded
        );
    }

    /**
     * A model halfway through being written generates rather than throwing.
     *
     * `er1.json` has an unnamed row in it on purpose, because real forms are
     * full of those, and it has no partition at all. Neither is a reason to
     * refuse somebody the forms they are trying to finish.
     */
    public function testAHalfWrittenLanguageStillGeneratesItsForms(): void
    {
        $sketch = ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/er1.json')
        );

        $generator = new Forms();
        $files     = new FileCollection();

        $generator->generate($sketch, $files);

        $names = array_map('basename', $files->paths());

        sort($names);

        $this->assertSame(
            ['deprecated.xml', 'entity.xml', 'er1.ini', 'field.xml', 'iNamed.xml', 'references.json'],
            $names
        );

        // And it says what it could not do, rather than producing it silently.
        $this->assertNotEmpty(
            array_filter($generator->log(), static fn (string $line): bool => str_starts_with($line, 'note:')),
            'Nothing was reported about the classifiers no model can hold.'
        );
    }

    /**
     * Inherited features reach a subtype's form when nothing nests them.
     *
     * In the meta-model every classifier that is extended is also a containment
     * target, so inheritance is laid out as nesting and a subtype's form is
     * bare. ER1's sketch has neither, and `Field` extends `Entity` and
     * implements `INamed` - so the fields have to arrive some other way, and
     * that way is the form itself.
     */
    public function testInheritanceReachesAFormWhenItIsNotLaidOutAsNesting(): void
    {
        $sketch = ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/er1.json')
        );

        $field = $sketch->classifier('c-field');

        $this->assertNotNull($field);

        $names = array_map(
            static fn ($feature): string => $feature->name,
            $sketch->featuresOf($field)
        );

        $this->assertSame(['name', 'isValueObject', 'owner'], $names);
    }
}
