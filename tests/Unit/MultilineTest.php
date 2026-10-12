<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Forms;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Naming;
use Yepr\Component\Metagen\Administrator\Generator\Meta\Presentation;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Component\Metagen\Administrator\Generator\Model\Feature;
use Yepr\Component\Metagen\Administrator\Generator\Target\MetaFormsTarget;
use Yepr\Component\Metagen\Administrator\Lionweb\EditorAnnotation;
use Yepr\Gen\Core\Lionweb\Chunk;
use Yepr\Gen\Core\Lionweb\LionCoreLanguage;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Package\MetalanguagePackage;
use Yepr\Gen\Core\Package\PackageReader;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Target\Target;

/**
 * A property that holds several lines, or code: step 6.4.
 *
 * Every string property generated as a text box, and a text box cannot hold a
 * line break: one browser save of JCB's demo blueprint lost them in 860
 * values, PHP among them. A language now says which of its properties run
 * over lines - on the Metalanguage form, and in LionWeb as an annotation on
 * the Property node - and those generate as textareas.
 *
 * **What must not change is a language that says nothing**, which is every
 * language before this one and every chunk JcbInOut has written so far.
 *
 * @since  0.7.0
 */
final class MultilineTest extends TestCase
{
    /**
     * A language as LionWeb serialises one: a Library of Snippets, each with a
     * name, a body of code, some notes and a count, and the annotations that
     * say how each is edited.
     *
     * @param  array<string, string>  $editors  Property node id => annotation value.
     */
    private function chunk(array $editors = [], ?string $onNode = null): Chunk
    {
        $m3    = ['language' => 'LionCore-M3', 'version' => '2024.1'];
        $named = ['language' => 'LionCore-builtins', 'version' => '2024.1', 'key' => 'LionCore-builtins-INamed-name'];

        $node = static function (
            string $id,
            string $kind,
            array $props,
            array $conts = [],
            array $refs = [],
            ?string $parent = null
        ) use (
            $m3,
            $named
        ): array {
            $node = [
                'id' => $id, 'classifier' => $m3 + ['key' => $kind],
                'properties' => [], 'containments' => [], 'references' => [],
                'annotations' => [], 'parent' => $parent,
            ];

            foreach ($props as $key => $value) {
                $node['properties'][] = $key === 'name'
                    ? ['property' => $named, 'value' => $value]
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

        // Every property of a Snippet, by name: its type, and whether it may be
        // left empty. Only the name is required.
        $property = static fn (string $name, string $type) => $node(
            'f-' . $name,
            'Property',
            ['name' => $name, 'IKeyed-key' => 'k-' . $name] + ($name === 'name' ? [] : ['Feature-optional' => 'true']),
            [],
            ['Property-type' => ['LionCore-builtins-' . $type . '-2024-1']],
            'snippet'
        );

        $nodes = [
            'lang'       => $node(
                'lang',
                'Language',
                ['name' => 'Snippets', 'IKeyed-key' => 'snippets', 'Language-version' => '1.0'],
                ['Language-entities' => ['library', 'snippet']]
            ),
            'library'    => $node(
                'library',
                'Concept',
                ['name' => 'Library', 'IKeyed-key' => 'k-library', 'Concept-partition' => 'true'],
                ['Classifier-features' => ['f-snippets']],
                [],
                'lang'
            ),
            'f-snippets' => $node(
                'f-snippets',
                'Containment',
                ['name' => 'snippets', 'IKeyed-key' => 'k-snippets', 'Feature-optional' => 'true',
                    'Link-multiple' => 'true'],
                [],
                ['Link-type' => ['snippet']],
                'library'
            ),
            'snippet'    => $node(
                'snippet',
                'Concept',
                ['name' => 'Snippet', 'IKeyed-key' => 'k-snippet'],
                ['Classifier-features' => ['f-name', 'f-body', 'f-notes', 'f-count']],
                [],
                'lang'
            ),
            'f-name'     => $property('name', 'String'),
            'f-body'     => $property('body', 'String'),
            'f-notes'    => $property('notes', 'String'),
            'f-count'    => $property('count', 'Integer'),
        ];

        $languages = [['key' => 'LionCore-M3', 'version' => '2024.1']];

        foreach ($editors as $property => $editor) {
            $id = 'editor-' . $property;

            $nodes[$id] = EditorAnnotation::node($id, $onNode ?? $property, $editor);

            $nodes[$onNode ?? $property]['annotations'][] = $id;
        }

        if ($editors !== []) {
            $languages[] = ['key' => EditorAnnotation::LANGUAGE, 'version' => EditorAnnotation::VERSION];
        }

        return Chunk::fromArray([
            'serializationFormatVersion' => '2024.1',
            'languages'                  => $languages,
            'nodes'                      => array_values($nodes),
        ]);
    }

    /**
     * The language in that chunk, converted the way the Import button does.
     *
     * @return array<string, mixed>
     */
    private function stored(Chunk $chunk): array
    {
        return (new EditorAnnotation($chunk))->applyTo((new LionCoreLanguage($chunk))->toStoredModel());
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function model(array $stored): ConceptModel
    {
        return ConceptModel::fromJson((string) json_encode($stored));
    }

    /**
     * @param  array<string, mixed>  $stored
     */
    private function snippetForm(array $stored): \SimpleXMLElement
    {
        $files = new FileCollection();

        (new Forms())->generate($this->model($stored), $files);

        $form = simplexml_load_string($files->all()[MetalanguagePackage::FORMS . 'snippet.xml'] ?? '');

        $this->assertNotFalse($form, 'snippet.xml was generated and parses');

        return $form;
    }

    private function field(\SimpleXMLElement $form, string $name): \SimpleXMLElement
    {
        $found = $form->xpath('//field[@name="' . $name . '"]') ?: [];

        $this->assertCount(1, $found, 'one field called ' . $name);

        return $found[0];
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function editorWords(): array
    {
        return [
            'absent, as before 6.4' => [null, Feature::EDITOR_LINE],
            'empty'                 => ['', Feature::EDITOR_LINE],
            'one line'              => ['line', Feature::EDITOR_LINE],
            'several lines'         => ['text', Feature::EDITOR_TEXT],
            'code'                  => ['code', Feature::EDITOR_CODE],
            'spelt loosely'         => [' Code ', Feature::EDITOR_CODE],
            'a word nobody knows'   => ['markdown', Feature::EDITOR_LINE],
        ];
    }

    #[DataProvider('editorWords')]
    public function testAPropertySaysHowItIsEdited(?string $stored, string $expected): void
    {
        $property = (object) ['type' => 'k-string'];

        if ($stored !== null) {
            $property->editor = $stored;
        }

        $feature = Feature::fromNode((object) ['name' => 'body', 'feature_type' => 'Property', 'property' => $property]);

        $this->assertSame($expected, $feature->editor);
        $this->assertSame($expected !== Feature::EDITOR_LINE, $feature->isMultiline());
        $this->assertSame($expected === Feature::EDITOR_CODE, $feature->holdsCode());
    }

    /**
     * The radio on the Metalanguage form offers exactly the words the
     * generator reads, and opens on the one that changes nothing.
     */
    public function testTheMetalanguageFormOffersTheThreeEditors(): void
    {
        $form = simplexml_load_file(
            \dirname(__DIR__, 2) . '/src/administrator/components/com_metagen/forms/LIonCore_M3/property.xml'
        );

        $this->assertNotFalse($form);

        $editor = $this->field($form, 'editor');

        $this->assertSame(Feature::EDITOR_LINE, (string) $editor['default']);
        $this->assertSame(
            [Feature::EDITOR_LINE, Feature::EDITOR_TEXT, Feature::EDITOR_CODE],
            array_map('strval', $editor->xpath('option/@value') ?: [])
        );
    }

    public function testALinkIsNeverMultiline(): void
    {
        $feature = Feature::fromNode((object) [
            'name'         => 'parts',
            'feature_type' => 'Link',
            'link'         => (object) ['type' => 'k-part', 'link_type' => 'Containment'],
            // Left behind by the form when somebody switched the radio.
            'property'     => (object) ['editor' => 'code'],
        ]);

        $this->assertFalse($feature->isMultiline());
        $this->assertFalse($feature->holdsCode());
    }

    /**
     * Nothing said, nothing changed: every string is a text box, as it was.
     */
    public function testALanguageThatSaysNothingKeepsItsTextBoxes(): void
    {
        $form = $this->snippetForm($this->stored($this->chunk()));

        foreach (['name', 'body', 'notes'] as $name) {
            $this->assertSame('text', (string) $this->field($form, $name)['type'], $name);
        }

        $this->assertEmpty($form->xpath('//field[@type="textarea"]') ?: []);
    }

    public function testSeveralLinesIsATextareaAndCodeIsMonospace(): void
    {
        $form = $this->snippetForm($this->stored($this->chunk(['f-notes' => 'text', 'f-body' => 'code'])));

        $notes = $this->field($form, 'notes');

        $this->assertSame('textarea', (string) $notes['type']);
        $this->assertSame(Presentation::TEXT_ROWS, (string) $notes['rows']);
        $this->assertSame('', (string) $notes['class']);
        $this->assertSame('', (string) $notes['spellcheck']);

        $body = $this->field($form, 'body');

        $this->assertSame('textarea', (string) $body['type']);
        $this->assertSame(Presentation::CODE_ROWS, (string) $body['rows']);
        $this->assertSame(Presentation::CODE_CLASS, (string) $body['class']);
        $this->assertSame('false', (string) $body['spellcheck']);

        // Kept as typed either way: the flag decides the input, not the filter.
        $this->assertSame('raw', (string) $notes['filter']);
        $this->assertSame('raw', (string) $body['filter']);

        // And the one nobody marked is untouched.
        $this->assertSame('text', (string) $this->field($form, 'name')['type']);
    }

    /**
     * The name the dropdowns find a row by keeps its class beside the code one.
     */
    public function testANameMarkedAsCodeIsStillFoundByItsClass(): void
    {
        $stored = $this->stored($this->chunk(['f-name' => 'code']));
        $model  = $this->model($stored);
        $name   = $this->field($this->snippetForm($stored), 'name');

        $classes = explode(' ', (string) $name['class']);

        $this->assertSame('textarea', (string) $name['type']);
        $this->assertContains(Presentation::CODE_CLASS, $classes);
        $this->assertContains(Naming::selectorOf($model->classifier('k-snippet')), $classes);
    }

    /**
     * A number marked multi-line is a mistake in the language, and the
     * datatype's own input is the better answer to it.
     */
    public function testANumberKeepsItsInput(): void
    {
        $form = $this->snippetForm($this->stored($this->chunk(['f-count' => 'text'])));

        $this->assertSame('number', (string) $this->field($form, 'count')['type']);
    }

    /**
     * The annotation reaches the stored language where the Metalanguage form
     * puts the radio, so a converted language and a hand-written one are the
     * same shape.
     */
    public function testTheAnnotationLandsWhereTheFormPutsIt(): void
    {
        $stored   = $this->stored($this->chunk(['f-body' => 'code']));
        $features = [];

        foreach ($stored['languageEntities'] as $entity) {
            foreach ($entity['classifier']['feature'] ?? [] as $feature) {
                if ($feature['feature_type'] !== 'Property') {
                    continue;
                }

                $features[$feature['name']] = $feature['property']['editor'] ?? null;
            }
        }

        $this->assertSame(['name' => null, 'body' => 'code', 'notes' => null, 'count' => null], $features);
    }

    /**
     * The annotations are extra nodes and nothing else: the language read out
     * of the chunk is the one read without them, apart from the flag, and the
     * converter has nothing new to say about them.
     */
    public function testAnAnnotatedChunkIsTheSameLanguage(): void
    {
        $plain     = new LionCoreLanguage($this->chunk());
        $annotated = new LionCoreLanguage($this->chunk(['f-body' => 'code', 'f-notes' => 'text']));

        $strip = static function (array $stored): array {
            foreach ($stored['languageEntities'] as $e => $entity) {
                foreach ($entity['classifier']['feature'] ?? [] as $f => $feature) {
                    unset($stored['languageEntities'][$e]['classifier']['feature'][$f]['property']['editor']);
                }
            }

            return $stored;
        };

        $this->assertSame($plain->toStoredModel(), $strip($annotated->toStoredModel()));
        $this->assertSame($plain->diagnostics(), $annotated->diagnostics());
    }

    /**
     * What a writer puts in a chunk is what the reader takes out of it.
     */
    public function testTheWriterAndTheReaderAgree(): void
    {
        $chunk = $this->chunk(['f-body' => 'code', 'f-notes' => 'text']);

        $this->assertSame(['k-body' => 'code', 'k-notes' => 'text'], (new EditorAnnotation($chunk))->editors());

        $this->assertContains(
            ['key' => EditorAnnotation::LANGUAGE, 'version' => EditorAnnotation::VERSION],
            $chunk->languages(),
            'a chunk using the annotation names its language'
        );
    }

    public function testAnAnnotationThatSaysTooLittleIsReported(): void
    {
        $unknown = new EditorAnnotation($this->chunk(['f-body' => 'markdown']));

        $this->assertSame([], $unknown->editors());
        $this->assertSame(['EDITOR_UNKNOWN'], array_column($unknown->diagnostics(), 'code'));

        $misplaced = new EditorAnnotation($this->chunk(['f-body' => 'code'], 'snippet'));

        $this->assertSame([], $misplaced->editors());
        $this->assertSame(['ANNOTATION_NOT_ON_PROPERTY'], array_column($misplaced->diagnostics(), 'code'));

        // `line` is what no annotation means already, and is not a complaint.
        $line = new EditorAnnotation($this->chunk(['f-body' => 'line']));

        $this->assertSame([], $line->editors());
        $this->assertSame([], $line->diagnostics());
    }

    /**
     * Into a package and back: the model a package carries still says it, so
     * a language that reaches Exten-gen regenerates with its textareas.
     */
    public function testTheFlagSurvivesThePackage(): void
    {
        $target = new MetaFormsTarget();
        $files  = (new Pipeline())->run(
            $this->model($this->stored($this->chunk(['f-body' => 'code', 'f-notes' => 'text']))),
            new Target($target->id(), $target->label(), $target->validator(), ...$target->generators())
        );

        $path = sys_get_temp_dir() . '/metagen-multiline-' . bin2hex(random_bytes(6)) . '.zip';

        try {
            (new ZipWriter())->write($files, $path);

            $read = PackageReader::fromZip($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $model   = ConceptModel::fromObject($read->model());
        $editors = [];

        foreach ($model->classifier('k-snippet')->features as $feature) {
            $editors[$feature->name] = $feature->editor;
        }

        $this->assertSame(
            ['name' => 'line', 'body' => 'code', 'notes' => 'text', 'count' => 'line'],
            $editors
        );

        $form = simplexml_load_string($read->files()[MetalanguagePackage::FORMS . 'snippet.xml'] ?? '');

        $this->assertNotFalse($form);
        $this->assertSame('textarea', (string) $this->field($form, 'body')['type']);
    }
}
