<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Gen\Core\Lionweb\Chunk;
use Yepr\Gen\Core\Lionweb\LionCoreLanguage;
use Yepr\Gen\Core\Output\FileCollection;

/**
 * A language read in from LionWeb is a metalanguage like any other.
 *
 * The conversion itself is the library's and is pinned there. What matters
 * here is the join: that what comes out of it is something `ConceptModel`
 * reads and `Forms` generates from, with nothing downstream able to tell where
 * the language came from. A converted language that parses but produces no
 * forms would pass every test in the library and be useless.
 *
 * @since  1.4.0
 */
final class LionwebImportTest extends TestCase
{
    /**
     * A language as LionWeb serialises one: a partition holding a thing with a
     * name, a kind and some parts.
     */
    private function chunk(): Chunk
    {
        $m3    = ['language' => 'LionCore-M3', 'version' => '2024.1'];
        $named = [
            'property' => ['language' => 'LionCore-builtins', 'version' => '2024.1',
                'key' => 'LionCore-builtins-INamed-name'],
        ];

        $node = static function (string $id, string $kind, array $props, array $conts = [], array $refs = []) use ($m3, $named): array {
            $node = [
                'id' => $id, 'classifier' => $m3 + ['key' => $kind],
                'properties' => [], 'containments' => [], 'references' => [],
                'annotations' => [], 'parent' => null,
            ];

            foreach ($props as $key => $value) {
                $node['properties'][] = $key === 'name'
                    ? $named + ['value' => $value]
                    : ['property' => $m3 + ['key' => $key], 'value' => $value];
            }

            foreach ($conts as $key => $children) {
                $node['containments'][] = ['containment' => $m3 + ['key' => $key], 'children' => $children];
            }

            foreach ($refs as $key => $targets) {
                $node['references'][] = [
                    'reference' => $m3 + ['key' => $key],
                    'targets'   => array_map(static fn ($t) => ['reference' => $t], $targets),
                ];
            }

            return $node;
        };

        return Chunk::fromArray([
            'serializationFormatVersion' => '2024.1',
            'languages' => [['key' => 'LionCore-M3', 'version' => '2024.1']],
            'nodes'     => [
                $node('lang', 'Language', ['name' => 'Workshop', 'IKeyed-key' => 'workshop',
                    'Language-version' => '2.0'], ['Language-entities' => ['kind', 'thing', 'part']]),

                $node(
                    'kind',
                    'Enumeration',
                    ['name' => 'Kind', 'IKeyed-key' => 'k-kind'],
                    ['Enumeration-literals' => ['lit-a', 'lit-b']]
                ),
                $node('lit-a', 'EnumerationLiteral', ['name' => 'Simple', 'IKeyed-key' => 'k-simple']),
                $node('lit-b', 'EnumerationLiteral', ['name' => 'Complex', 'IKeyed-key' => 'k-complex']),

                $node(
                    'thing',
                    'Concept',
                    ['name' => 'Thing', 'IKeyed-key' => 'k-thing',
                    'Concept-partition' => 'true'],
                    ['Classifier-features' => ['f-name', 'f-kind', 'f-parts']]
                ),

                $node(
                    'f-name',
                    'Property',
                    ['name' => 'title', 'IKeyed-key' => 'k-title'],
                    [],
                    ['Property-type' => ['LionCore-builtins-String-2024-1']]
                ),
                $node(
                    'f-kind',
                    'Property',
                    ['name' => 'kind', 'IKeyed-key' => 'k-f-kind'],
                    [],
                    ['Property-type' => ['kind']]
                ),
                $node('f-parts', 'Containment', ['name' => 'parts', 'IKeyed-key' => 'k-parts',
                    'Link-multiple' => 'true'], [], ['Link-type' => ['part']]),

                $node(
                    'part',
                    'Concept',
                    ['name' => 'Part', 'IKeyed-key' => 'k-part'],
                    ['Classifier-features' => ['f-label']]
                ),
                $node(
                    'f-label',
                    'Property',
                    ['name' => 'label', 'IKeyed-key' => 'k-label'],
                    [],
                    ['Property-type' => ['LionCore-builtins-String-2024-1']]
                ),
            ],
        ]);
    }

    private function stored(): array
    {
        return (new LionCoreLanguage($this->chunk()))->toStoredModel();
    }

    /**
     * The join: what the converter produces is what `ConceptModel` reads.
     */
    public function testAConvertedLanguageIsOneMetagenCanRead(): void
    {
        $model = ConceptModel::fromJson((string) json_encode($this->stored()));

        $this->assertSame('Workshop', $model->name());
        $this->assertSame('Thing', $model->root()?->name, 'the partition is the root form');

        $this->assertSame(
            ['Thing', 'Part'],
            array_map(static fn ($c): string => $c->name, array_values($model->classifiers()))
        );

        // The builtin was materialised, so `title` has a type rather than none.
        $this->assertSame(
            ['String', 'Kind'],
            array_map(static fn ($d): string => $d->name, array_values($model->dataTypes()))
        );
    }

    public function testAnEnumerationArrivesWithItsLiterals(): void
    {
        $model = ConceptModel::fromJson((string) json_encode($this->stored()));
        $kind  = $model->dataTypes()['k-kind'] ?? null;

        $this->assertNotNull($kind);
        $this->assertTrue($kind->isEnumeration());
        $this->assertSame(
            ['Simple', 'Complex'],
            array_column($kind->literals, 'name')
        );
    }

    /**
     * And forms come out of it, which is the only thing that proves the
     * conversion produced a language rather than a shape that merely parsed.
     */
    public function testFormsAreGeneratedFromAConvertedLanguage(): void
    {
        $model = ConceptModel::fromJson((string) json_encode($this->stored()));
        $files = new FileCollection();

        (new Forms())->generate($model, $files);

        $names = array_map('basename', $files->paths());

        sort($names);

        $this->assertContains('thing.xml', $names);
        $this->assertContains('part.xml', $names);
        $this->assertContains('references.json', $names);

        $form = simplexml_load_string($files->all()[
            array_values(array_filter(
                $files->paths(),
                static fn (string $p): bool => str_ends_with($p, 'thing.xml')
            ))[0]
        ]);

        $this->assertNotFalse($form, 'a generated form has to be XML Joomla can load');

        $fields = array_map(
            static fn ($f): string => (string) $f['name'],
            $form->xpath('//field') ?: []
        );

        $this->assertContains('title', $fields);
        $this->assertContains('kind', $fields, 'the enumeration reached the form as a field');
    }
}
