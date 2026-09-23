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
     * A surrogate identity is hidden, and a typed one is not.
     *
     * ER1 gives every entity an `entity_id` that nobody enters, and the
     * hand-written form hides it. A generated form that rendered it as a text
     * box would put an internal number on screen for somebody to type over -
     * the one difference in the 3.5 inventory that made a generated form
     * *wrong* rather than merely different.
     *
     * **"Identity" is not what decides it.** LionCore M3's identity is `key`
     * and a person types it, in a plain visible text field. So the language
     * says, per property, whether the value is entered or assigned - which is
     * a fact about the language and not about how it looks.
     */
    public function testASurrogateIdentityIsHidden(): void
    {
        $files = new FileCollection();

        (new Forms())->generate($this->er1(), $files);

        $entity = simplexml_load_string($files->get(MetalanguagePackage::formPath('Entity')));

        $this->assertNotFalse($entity);

        $id = $entity->xpath('//field[@name="entity_id"]');

        $this->assertNotEmpty($id, 'the identity left the form entirely');
        $this->assertSame('hidden', (string) $id[0]['type']);

        // And the name beside it is still a text box somebody fills in.
        $name = $entity->xpath('//field[@name="entity_name"]');

        $this->assertNotEmpty($name);
        $this->assertSame('text', (string) $name[0]['type']);
    }

    /**
     * A closed list is an enumeration, and comes back as the same dropdown.
     *
     * `page_type` offers five kinds of page. Read as a String it would generate
     * a text box where the hand-written form has a dropdown, and the five
     * answers would be gone - a field somebody could type anything into, which
     * is what the vocabulary work at 2.2 called the difference between a
     * modelling tool and a JSON editor.
     *
     * The literals keep the option's own value and text, so what comes out is
     * the dropdown that went in rather than one meaning roughly the same.
     */
    public function testAClosedListComesBackAsTheSameDropdown(): void
    {
        $model    = $this->er1();
        $pageType = null;

        foreach ($model->dataTypes() as $dataType) {
            if ($dataType->name === 'PageType') {
                $pageType = $dataType;
            }
        }

        $this->assertNotNull($pageType, 'page_type was not read as an enumeration');
        $this->assertTrue($pageType->isEnumeration());

        $files = new FileCollection();

        (new Forms())->generate($model, $files);

        $page = simplexml_load_string($files->get(MetalanguagePackage::formPath('Page')));

        $this->assertNotFalse($page);

        $field = $page->xpath('//field[@name="page_type"]');

        $this->assertNotEmpty($field);
        $this->assertSame('list', (string) $field[0]['type']);

        $offered = [];

        foreach ($field[0]->xpath('option') ?: [] as $option) {
            $offered[(string) $option['value']] = trim((string) $option);
        }

        $this->assertSame(
            [
                'detailspage'  => 'Detail page',
                'indexpage'    => 'List page',
                'subform'      => 'Sub-form',
                'dashboard'    => 'todo: Dashboard',
                'detailsindex' => 'todo: Detail page with lists embedded',
            ],
            $offered,
            'the generated dropdown is not the one ER1 ships'
        );
    }

    /**
     * A new row opens holding what the language says it holds.
     *
     * "A new page is a detail page unless you say otherwise" is a fact about
     * the language, not about how the form looks - which is why this went into
     * the model at 3.5 while `size` and `min` went to the generator's defaults
     * table. Another target would want this and would not want those.
     *
     * Without it a generated form opens with nothing chosen where the
     * hand-written one opens with something, and a page saved without touching
     * the dropdown would have no type at all.
     */
    public function testANewRowOpensHoldingWhatTheLanguageSays(): void
    {
        $files = new FileCollection();

        (new Forms())->generate($this->er1(), $files);

        $page = simplexml_load_string($files->get(MetalanguagePackage::formPath('Page')));

        $this->assertNotFalse($page);

        $type = $page->xpath('//field[@name="page_type"]');

        $this->assertNotEmpty($type);
        $this->assertSame('detailspage', (string) $type[0]['default']);

        // And a property nobody gave one to carries no empty default, which
        // Joomla would read as a real value of "".
        $name = $page->xpath('//field[@name="page_name"]');

        $this->assertNotEmpty($name);
        $this->assertNull($name[0]['default']);
    }

    /**
     * A default on a closed list is one of that list's own answers.
     *
     * A default naming something the list does not offer renders as nothing
     * selected - which looks exactly like having no default, so the mistake is
     * invisible on screen and survives until somebody saves a row without
     * touching the field.
     */
    public function testEveryDefaultOnAClosedListIsOneOfItsAnswers(): void
    {
        $model  = $this->er1();
        $wrong  = [];
        $looked = 0;

        foreach ($model->classifiers() as $classifier) {
            foreach ($classifier->features as $feature) {
                if (!$feature->isProperty() || $feature->default === '') {
                    continue;
                }

                $datatype = $model->dataType($feature->typeKey);

                if ($datatype === null || !$datatype->isEnumeration()) {
                    continue;
                }

                $looked++;
                $keys = array_column($datatype->literals, 'key');

                if (!\in_array($feature->default, $keys, true)) {
                    $wrong[] = $classifier->name . '.' . $feature->name . ' defaults to "'
                        . $feature->default . '", and ' . $datatype->name . ' offers '
                        . implode(', ', $keys);
                }
            }
        }

        $this->assertSame([], $wrong, implode("
  ", $wrong));
        $this->assertGreaterThan(0, $looked, 'no list with a default was checked, so this proves nothing');
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
