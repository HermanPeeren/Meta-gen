<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Meta\LanguageStrings;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Component\Metagen\Administrator\Package\MetalanguagePackage;
use Yepr\Gen\Core\Output\FileCollection;

/**
 * The text on a generated language's forms: step 3.3.
 *
 * Until now every generated label was a constant nothing defined, so a
 * generated form showed a person its own constant names. 3.2 wrote that down
 * as one of the four differences between the generated meta-model and the
 * hand-written one, and left which names to use open. It is settled: they name
 * the *language*, because a package is loaded by com_extengen and by
 * com_gengen and a key naming either of them is wrong whichever it names.
 *
 * **The rule that matters here is the first one**, and it is the same rule
 * `FormsTest` applies to subform sources: every constant a form names is one
 * the package defines. Joomla renders an undefined constant as itself, in
 * capitals, with no error anywhere - so a language file with a hole in it is a
 * screen that looks broken to a person and fine to every gate.
 *
 * @since  1.3.0
 */
final class LanguageFileTest extends TestCase
{
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
     * LionCore M3, modelled in LionCore M3.
     */
    private function metaModel(): ConceptModel
    {
        return ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/lioncore-m3.json')
        );
    }

    /**
     * A small language that has a label and a description on it.
     *
     * Neither fixture on disk carries any: `er1.json` and `lioncore-m3.json`
     * were both modelled before there was anywhere to put one, and the point
     * of them is that they still generate. So the language that exercises the
     * text is written here.
     */
    private function labelled(): ConceptModel
    {
        return ConceptModel::fromJson((string) json_encode([
            'name'             => 'Labelled',
            'version'          => '1.0',
            'languageEntities' => [
                'languageEntities0' => [
                    'name'                => 'Entity',
                    'key'                 => 'c-entity',
                    'label'               => 'Thing',
                    'languageEntity_type' => 'Classifier',
                    'classifier'          => [
                        'classifier_type' => 'Concept',
                        'concept'         => ['partition' => '1'],
                        'feature'         => [
                            'feature0' => [
                                'name'         => 'isValueObject',
                                'label'        => 'Is this a "value object"?',
                                'description'  => 'A value object has no identity of its own.',
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
    }

    /**
     * The strings of a generated language, as Joomla will read them.
     *
     * Parsed rather than matched, because what a site shows is what the parser
     * makes of the file and not what the file looks like.
     *
     * @return array<string, string>
     */
    private function strings(string $name, ?ConceptModel $model = null): array
    {
        $files = $this->generate($model);
        $path  = MetalanguagePackage::languagePath($name);

        $this->assertArrayHasKey($path, $files, 'no language file was generated at all');

        $parsed = parse_ini_string($files[$path], false, \INI_SCANNER_RAW);

        $this->assertIsArray($parsed, $path . ' is not a file Joomla can parse.');

        $strings = [];

        foreach ($parsed as $key => $value) {
            $strings[(string) $key] = str_replace('\\"', '"', (string) $value);
        }

        return $strings;
    }

    /**
     * Every constant a generated form names, and the form that names it.
     *
     * A constant is recognised by shape - capitals with an underscore in - and
     * an underscore is required on purpose: an enumeration literal's option
     * text is the literal's own name, so `Concept` must not be mistaken for a
     * string somebody forgot to define.
     *
     * @param  array<string, string>  $files
     *
     * @return array<string, string>  constant => the form that names it
     */
    private function constantsIn(array $files): array
    {
        $found = [];

        foreach ($files as $path => $contents) {
            if (!str_ends_with($path, '.xml')) {
                continue;
            }

            $xml = simplexml_load_string($contents);

            $this->assertNotFalse($xml, $path . ' is not XML.');

            foreach ($xml->xpath('//@label | //@description | //option') ?: [] as $node) {
                $text = trim((string) $node);

                if (preg_match('/^[A-Z][A-Z0-9]*(_[A-Z0-9]+)+$/', $text) === 1) {
                    $found[$text] = basename($path);
                }
            }
        }

        return $found;
    }

    /**
     * Every constant a generated form names is one the package defines.
     */
    public function testEveryConstantAFormNamesIsDefined(): void
    {
        $files     = $this->generate();
        $strings   = $this->strings('LIonCore_M3');
        $constants = $this->constantsIn($files);
        $undefined = [];

        foreach ($constants as $constant => $form) {
            if (!isset($strings[$constant])) {
                $undefined[] = $constant . ' (' . $form . ')';
            }
        }

        sort($undefined);

        $this->assertSame(
            [],
            $undefined,
            "Joomla renders these back at the user as their own names:\n  " . implode("\n  ", $undefined)
        );

        // And the scan reached something, so a rule that found nothing cannot
        // pass by finding nothing.
        $this->assertGreaterThan(40, \count($constants), 'no constants were scanned at all');
    }

    /**
     * And nothing is defined that no form asks for.
     *
     * The other direction, and it is not tidiness: a string collected for a
     * label that is no longer emitted means the collector and the emitter have
     * come apart, which is the failure the whole `FormXml`-asks-and-hands-over
     * arrangement exists to make impossible.
     */
    public function testNothingIsDefinedThatNoFormAsksFor(): void
    {
        $spare = array_diff_key(
            $this->strings('LIonCore_M3'),
            $this->constantsIn($this->generate())
        );

        $this->assertSame([], array_keys($spare));
    }

    /**
     * A constant names the language and no component.
     */
    public function testAConstantNamesTheLanguageAndNoComponent(): void
    {
        foreach (array_keys($this->strings('LIonCore_M3')) as $constant) {
            $this->assertStringStartsWith('YEPR_LIONCORE_M3_', $constant);
            $this->assertStringNotContainsString('COM_', $constant);
        }
    }

    /**
     * A label is used when there is one, and the name when there is not.
     */
    public function testALabelIsUsedWhenThereIsOneAndTheNameWhenThereIsNot(): void
    {
        $strings = $this->strings('Labelled', $this->labelled());

        $this->assertSame(
            'Is this a "value object"?',
            $strings['YEPR_LABELLED_ENTITY_FIELD_ISVALUEOBJECT_LABEL'] ?? null,
            'the label did not survive the file, quotes and all'
        );

        $this->assertSame(
            'unlabelled',
            $strings['YEPR_LABELLED_ENTITY_FIELD_UNLABELLED_LABEL'] ?? null
        );
    }

    /**
     * A description reaches the form only when the model holds one.
     *
     * An empty `description` attribute is not nothing: it is a field with a
     * tooltip that opens onto blank space.
     */
    public function testADescriptionIsEmittedOnlyWhenThereIsOne(): void
    {
        $files = $this->generate($this->labelled());
        $form  = simplexml_load_string($files[MetalanguagePackage::formPath('Entity')]);

        $this->assertNotFalse($form);

        $described = $form->xpath('//field[@name="isValueObject"]')[0];
        $plain     = $form->xpath('//field[@name="unlabelled"]')[0];

        $this->assertSame(
            'YEPR_LABELLED_ENTITY_FIELD_ISVALUEOBJECT_DESC',
            (string) $described['description']
        );
        $this->assertNull($plain['description'], 'a feature with no description got an empty one');

        $this->assertSame(
            'A value object has no identity of its own.',
            $this->strings('Labelled', $this->labelled())['YEPR_LABELLED_ENTITY_FIELD_ISVALUEOBJECT_DESC'] ?? null
        );
    }

    /**
     * The radio that says which kind of thing a row is reads like one.
     *
     * `Classifier type`, the way the hand-written meta-model spells it, which
     * is what keeps 3.5's golden-file comparison down to differences that are
     * about the model rather than about wording.
     */
    public function testTheKindRadioIsLabelledAfterTheClassifierItSplits(): void
    {
        $strings = $this->strings('LIonCore_M3');

        $this->assertSame(
            'Classifier type',
            $strings['YEPR_LIONCORE_M3_CLASSIFIER_FIELD_CLASSIFIER_TYPE_LABEL'] ?? null
        );
        $this->assertSame(
            'Concept',
            $strings['YEPR_LIONCORE_M3_CLASSIFIER_FIELD_CONCEPT_LABEL'] ?? null
        );
    }

    /**
     * A value that would break the file is made safe, not silently dropped.
     *
     * A newline inside a label ends the entry, and everything after it in the
     * file is then read as whatever the next line happens to look like - so
     * one pasted line break would take out the rest of the language. It
     * becomes a space.
     */
    public function testAValueThatWouldBreakTheFileIsMadeSafe(): void
    {
        $strings = new LanguageStrings();

        $strings->add('A_ONE', "two\nlines");
        $strings->add('A_TWO', 'he said "no"');

        $parsed = parse_ini_string($strings->render('test'), false, \INI_SCANNER_RAW);

        $this->assertIsArray($parsed);
        $this->assertSame('two lines', $parsed['A_ONE']);
        $this->assertSame('he said "no"', str_replace('\\"', '"', (string) $parsed['A_TWO']));
    }

    /**
     * One constant asked for two different labels is reported, not resolved.
     *
     * The first wins, because the alternative is a winner that depends on the
     * order classifiers happen to be walked in - and the run says so, which is
     * the only thing that can be done about a model that cannot decide.
     */
    public function testOneConstantWithTwoLabelsIsReported(): void
    {
        $strings = new LanguageStrings();

        $strings->add('A_ONE', 'first');
        $strings->add('A_ONE', 'second');

        $this->assertSame(['first'], array_values($strings->all()));
        $this->assertSame(['A_ONE' => ['first', 'second']], $strings->clashes());
    }
}
