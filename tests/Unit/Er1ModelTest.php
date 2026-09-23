<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Package\MetalanguagePackage;

/**
 * ER1, modelled in LionCore M3: step 3.5.
 *
 * `er1-full.json` is Exten-gen's own language read back out of the twenty-six
 * form files it ships, by `tools/import-forms.php`. It sits beside `er1.json`
 * rather than replacing it, because that one is a seven-entity sketch with an
 * unnamed row and no partition in it - a *half-written* language, which is its
 * own test case and one the real thing cannot stand in for.
 *
 * **What this file pins is the model, not the reader.** The reader ran once;
 * its output is committed and is what everything downstream uses. So the checks
 * here are the ones a person would make reading the model beside the forms: is
 * the root the thing a project opens at, is the subtyping the subtyping those
 * forms describe, does every reference point at something the language has, and
 * does it generate a form per classifier with nothing reported missing.
 *
 * @since  1.4.0
 */
final class Er1ModelTest extends TestCase
{
    private function er1(): ConceptModel
    {
        return ConceptModel::fromJson(
            (string) file_get_contents(\dirname(__DIR__) . '/Fixtures/languages/er1-full.json')
        );
    }

    /**
     * A project opens at a Project, and there is exactly one root.
     */
    public function testTheRootIsTheThingAProjectOpensAt(): void
    {
        $root = $this->er1()->root();

        $this->assertNotNull($root);
        $this->assertSame('Project', $root->name);

        $partitions = array_filter(
            $this->er1()->classifiers(),
            static fn ($classifier): bool => $classifier->partition
        );

        $this->assertCount(1, $partitions, 'a language with two roots has no answer to "open what?"');
    }

    /**
     * The language holds what ER1's forms describe.
     *
     * Named rather than counted, because a count says nothing about which
     * nineteen: `Entity` and `Field` are what ER1 is *about*, and a model that
     * lost either would still be nineteen of something.
     */
    public function testItHoldsTheConceptsEr1IsAbout(): void
    {
        $names = array_map(
            static fn ($classifier): string => $classifier->name,
            array_values($this->er1()->classifiers())
        );

        foreach (['Project', 'Entity', 'Field', 'Property', 'Page', 'Component'] as $expected) {
            $this->assertContains($expected, $names);
        }

        // And the five forms nothing reaches are not in it - see the plan under
        // 3.5. `pages.xml` is pointed at by no form at all, and it is the root
        // of a subtree of four more.
        foreach (['Pages', 'Indexpage', 'Detailspage'] as $dead) {
            $this->assertNotContains($dead, $names, $dead . ' is a form nothing reaches.');
        }
    }

    /**
     * A Field is a Property or a reference, and nothing else.
     *
     * The one place ER1 really does subtype: `field.xml` offers exactly two
     * kinds and answers each with one subform, which is what makes it subtyping
     * rather than the conditional visibility `page.xml` uses.
     */
    public function testAFieldIsEitherAPropertyOrAReference(): void
    {
        $model = $this->er1();
        $field = null;

        foreach ($model->classifiers() as $classifier) {
            if ($classifier->name === 'Field') {
                $field = $classifier;
            }
        }

        $this->assertNotNull($field);
        $this->assertTrue($field->abstract, 'a row is one of the kinds below Field, never a bare Field');

        $subtypes = [];

        foreach ($model->classifiers() as $classifier) {
            if ($classifier->extendsKey === $field->key) {
                $subtypes[] = $classifier->name;
            }
        }

        sort($subtypes);

        $this->assertSame(['EntityReferenceField', 'Property'], $subtypes);
    }

    /**
     * Every reference points at a classifier the language has.
     *
     * A reference to something absent is a dropdown that renders and is always
     * empty, which reads as "there is nothing to point at" rather than as a
     * mistake - the failure this family keeps meeting.
     */
    public function testEveryReferencePointsAtSomethingTheLanguageHas(): void
    {
        $model   = $this->er1();
        $missing = [];

        foreach ($model->classifiers() as $classifier) {
            foreach ($classifier->features as $feature) {
                if (!$feature->isReference() && !$feature->isContainment()) {
                    continue;
                }

                if ($model->classifier($feature->typeKey) === null) {
                    $missing[] = $classifier->name . '.' . $feature->name . ' -> ' . $feature->typeKey;
                }
            }
        }

        $this->assertSame([], $missing, implode("\n  ", $missing));
    }

    /**
     * It generates a form per classifier, and reports nothing missing.
     */
    public function testItGeneratesAFormPerClassifierWithNothingMissing(): void
    {
        $model     = $this->er1();
        $files     = new FileCollection();
        $generator = new Forms();

        $generator->generate($model, $files);

        $forms = array_filter(
            $files->paths(),
            static fn (string $path): bool => str_starts_with($path, MetalanguagePackage::FORMS)
                && str_ends_with($path, '.xml')
        );

        $this->assertCount(\count($model->classifiers()), $forms);

        $warnings = array_filter(
            $generator->log(),
            static fn (string $line): bool => str_starts_with($line, 'warning:')
        );

        $this->assertSame([], array_values($warnings), implode("\n  ", $warnings));
    }
}
