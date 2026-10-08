<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Package\MetalanguagePackage;

/**
 * Features grouped into named fieldsets: step 5.2.
 *
 * Extengen showed a project on three tabs - Entities, Pages, Extensions - and
 * lost them when 3.5 made ER1 a generated language, because a generated form
 * had no way to say that some fields belong together. A feature may now name a
 * fieldset; the form generator puts every feature naming the same one into a
 * `<fieldset>` of that name, and Exten-gen renders one tab per fieldset.
 *
 * **What must not change is the case where nobody names one**, which is every
 * language before this. LionCore M3 names none, so its forms are what they
 * were: one unnamed fieldset each.
 *
 * @since  1.6.0
 */
final class FieldsetTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function generate(string $fixture, ?callable $change = null): array
    {
        $json = (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/' . $fixture . '.json');

        if ($change !== null) {
            $json = $change($json);
        }

        $files = new FileCollection();

        (new Forms())->generate(ConceptModel::fromJson($json), $files);

        return $files->all();
    }

    private function projectForm(?callable $change = null): \SimpleXMLElement
    {
        $files = $this->generate('er1-full', $change);
        $xml   = simplexml_load_string($files[MetalanguagePackage::FORMS . 'project.xml'] ?? '');

        $this->assertNotFalse($xml, 'project.xml was generated and parses');

        return $xml;
    }

    /**
     * @return string[]
     */
    private function fieldsetNames(\SimpleXMLElement $form): array
    {
        return array_map(
            static fn (\SimpleXMLElement $set): string => (string) $set['name'],
            $form->xpath('/form/fieldset') ?: []
        );
    }

    public function testEr1PutsItsProjectOnThreeNamedFieldsets(): void
    {
        $form = $this->projectForm();

        // The unnamed one first: it carries the hidden fields and the
        // fieldprefix, and a model naming no group is still all in it.
        $this->assertSame(['', 'entities', 'pages', 'extensions'], $this->fieldsetNames($form));

        foreach (['entities' => 'datamodel', 'pages' => 'pages', 'extensions' => 'extensions'] as $set => $field) {
            $found = $form->xpath('/form/fieldset[@name="' . $set . '"]/field/@name') ?: [];

            $this->assertSame([$field], array_map('strval', $found), $set . ' holds exactly its feature');
        }

        $this->assertNotEmpty(
            $form->xpath('/form/fieldset[not(@name)]/field[@name="LIonWeb_key"]'),
            'the hidden identity stays in the form\'s own fieldset'
        );
    }

    public function testEveryFieldsetHasALabelTheLanguageFileDefines(): void
    {
        $files = $this->generate('er1-full');
        $ini   = '';

        foreach ($files as $path => $contents) {
            if (str_ends_with($path, '.ini')) {
                $ini .= $contents;
            }
        }

        $form = simplexml_load_string($files[MetalanguagePackage::FORMS . 'project.xml']);

        $this->assertNotFalse($form);

        foreach ($form->xpath('/form/fieldset[@name]') ?: [] as $set) {
            $label = (string) $set['label'];

            $this->assertMatchesRegularExpression('/^YEPR_ER1_PROJECT_FIELDSET_[A-Z]+_LABEL$/', $label);
            $this->assertMatchesRegularExpression('/^' . $label . '="[A-Z]/m', $ini, $label . ' is in the language file');
        }

        $this->assertStringContainsString('YEPR_ER1_PROJECT_FIELDSET_ENTITIES_LABEL="Entities"', $ini);
    }

    public function testANameIsNormalisedToWhatJoomlaCanUse(): void
    {
        $form = $this->projectForm(
            static fn (string $json): string => str_replace('"fieldset": "entities"', '"fieldset": " The Entities! "', $json)
        );

        $this->assertContains('the_entities', $this->fieldsetNames($form));
    }

    public function testFeaturesSharingANameShareOneFieldset(): void
    {
        $form = $this->projectForm(
            static fn (string $json): string => str_replace('"fieldset": "extensions"', '"fieldset": "Pages"', $json)
        );

        $this->assertSame(['', 'entities', 'pages'], $this->fieldsetNames($form));
        $this->assertCount(2, $form->xpath('/form/fieldset[@name="pages"]/field') ?: []);
    }

    public function testALanguageNamingNoFieldsetKeepsOneFieldsetPerForm(): void
    {
        foreach ($this->generate('lioncore-m3') as $path => $contents) {
            if (!str_ends_with($path, '.xml')) {
                continue;
            }

            $xml = simplexml_load_string($contents);

            $this->assertNotFalse($xml, $path);
            $this->assertCount(1, $xml->xpath('/form/fieldset') ?: [], $path . ' has one fieldset');
            $this->assertEmpty($xml->xpath('/form/fieldset[@name]') ?: [], $path . ' names none');
        }
    }
}
