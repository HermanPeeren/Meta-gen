<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Metagen\Administrator\Generator\Model\ConceptModel;
use Yepr\Component\Metagen\Administrator\Generator\Target\MetaFormsTarget;
use Yepr\Component\Metagen\Administrator\Package\MetalanguagePackage;
use Yepr\Component\Metagen\Administrator\Package\PackageReader;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Output\ZipWriter;
use Yepr\Gen\Core\Pipeline;
use Yepr\Gen\Core\Target\Target;

/**
 * The metalanguage package: step 3.3.
 *
 * The plan says this step is *done when a package round-trips - exported, read
 * back, and the forms in it are the forms the generator produced*, and that is
 * what most of this file is. It is a low bar deliberately chosen: a format
 * nothing reads is a guess about what a format should be, and every mistake in
 * one is found by the first thing that tries to read it.
 *
 * **Read back two ways, from the zip and from the tree beside it.** The
 * generate screen writes both, and until now nothing said they were the same
 * package - which is the kind of thing that stays true until somebody adds a
 * file to one writer and not the other.
 *
 * @since  1.3.0
 */
final class PackageTest extends TestCase
{
    /**
     * Files written during a test, removed afterwards.
     *
     * @var string[]
     */
    private array $rubbish = [];

    protected function tearDown(): void
    {
        foreach ($this->rubbish as $path) {
            if (is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                self::removeTree($path);
            }
        }

        $this->rubbish = [];

        parent::tearDown();
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
     * A whole package, built the way the screen and the export both build one.
     *
     * Through the target rather than by calling the generators, because the
     * order they run in is part of what is being checked: the manifest hashes
     * what is already in the collection, so a target that listed it first
     * would produce a manifest describing nothing and no hash in it would be
     * wrong.
     */
    private function package(?ConceptModel $model = null): FileCollection
    {
        $target = new MetaFormsTarget();

        return (new Pipeline())->run($model ?? $this->metaModel(), new Target(
            $target->id(),
            $target->label(),
            $target->validator(),
            ...$target->generators()
        ));
    }

    /**
     * That package, written to a zip nobody has to clean up by hand.
     */
    private function zip(?FileCollection $files = null): string
    {
        $path = sys_get_temp_dir() . '/metagen-package-' . bin2hex(random_bytes(6)) . '.zip';

        $this->rubbish[] = $path;

        (new ZipWriter())->write($files ?? $this->package(), $path);

        return $path;
    }

    /**
     * Everything the plan says a package carries.
     *
     * Named one by one rather than counted, because the point of the list is
     * the list: "the concept model, the generated forms, the reference table,
     * a language file and a manifest naming the language, its version and its
     * root classifier".
     */
    public function testAPackageCarriesTheFivePartsTheStepAsksFor(): void
    {
        $files = $this->package();

        $this->assertTrue($files->has(MetalanguagePackage::MANIFEST), 'no manifest');
        $this->assertTrue($files->has(MetalanguagePackage::MODEL), 'no concept model');
        $this->assertTrue($files->has(MetalanguagePackage::REFERENCES), 'no reference table');
        $this->assertTrue(
            $files->has(MetalanguagePackage::languagePath('LIonCore_M3')),
            'no language file'
        );

        $forms = array_filter(
            $files->paths(),
            static fn (string $p): bool => str_starts_with($p, MetalanguagePackage::FORMS)
                && str_ends_with($p, '.xml')
        );

        $this->assertCount(16, $forms, 'one form per classifier');
    }

    /**
     * Exported, read back, and byte for byte what went in.
     */
    public function testAPackageRoundTripsThroughAZip(): void
    {
        $files = $this->package();
        $read  = PackageReader::fromZip($this->zip($files));

        $this->assertSame([], $read->problems(), 'the package complains about itself');
        $this->assertSame($files->all(), $read->files(), 'what came out is not what went in');
    }

    /**
     * And the same package comes back out of the unpacked tree.
     */
    public function testTheTreeBesideTheArchiveIsTheSamePackage(): void
    {
        $files = $this->package();
        $root  = sys_get_temp_dir() . '/metagen-tree-' . bin2hex(random_bytes(6));

        $this->rubbish[] = $root;

        mkdir($root, 0755, true);

        (new ZipWriter())->writeToDirectory($files, $root);

        $read = PackageReader::fromDirectory($root);

        $this->assertSame([], $read->problems());
        $this->assertSame($files->all(), $read->files());
    }

    /**
     * The manifest names the language, its version and its root classifier.
     */
    public function testTheManifestNamesTheLanguageItsVersionAndItsRoot(): void
    {
        $manifest = PackageReader::fromZip($this->zip())->manifest();

        $this->assertSame('LIonCore_M3', $manifest->name);
        $this->assertSame('LIonCore_M3', $manifest->key);
        $this->assertSame('2023.1', $manifest->version);
        $this->assertSame('Language', $manifest->root);
        $this->assertSame(MetalanguagePackage::FORMAT, $manifest->format);
        $this->assertSame('media/yepr_metalanguages/LIonCore_M3/2023.1/', $manifest->formRoot);
        $this->assertSame('language/en-GB/lioncore_m3.ini', $manifest->language);
    }

    /**
     * A language with no version at all is still a package.
     *
     * `0.0.0` rather than an empty path segment, because the version is half
     * of what identifies a language from 3.4 on and an empty one would put two
     * different languages in one directory.
     */
    public function testALanguageWithNoVersionGetsOne(): void
    {
        $model = ConceptModel::fromObject((object) [
            'name'             => 'Sketch',
            'languageEntities' => [
                (object) [
                    'key'                 => 'c-thing',
                    'languageEntity_type' => 'Classifier',
                    'name'                => 'Thing',
                    'classifier'          => (object) [
                        'classifier_type' => 'Concept',
                        'concept'         => (object) ['partition' => '1'],
                    ],
                ],
            ],
        ]);

        $manifest = PackageReader::fromZip($this->zip($this->package($model)))->manifest();

        $this->assertSame('0.0.0', $manifest->version);
        $this->assertSame('media/yepr_metalanguages/Sketch/0.0.0/', $manifest->formRoot);
    }

    /**
     * Every subform in the package resolves once it is where it says it goes.
     *
     * This is the claim the whole layout rests on. Joomla reads a `formsource`
     * as `JPATH_ROOT . '/' . $formsource` and nothing else, so a package whose
     * subforms point somewhere it will not be unpacked renders as a screen of
     * empty boxes - no exception, no missing file reported, nothing. The same
     * silent failure as 3.1's, and 3.0's asset uri, by a third route.
     */
    public function testEverySubformResolvesOncePackageIsWhereItSaysItGoes(): void
    {
        $read     = PackageReader::fromZip($this->zip());
        $manifest = $read->manifest();
        $files    = $read->files();
        $checked  = 0;
        $wrong    = [];

        foreach ($read->forms() as $path => $contents) {
            $xml = simplexml_load_string($contents);

            $this->assertNotFalse($xml, $path . ' is not XML.');

            foreach ($xml->xpath('//field[@formsource]') ?: [] as $field) {
                $source = (string) $field['formsource'];
                $checked++;

                if (!str_starts_with($source, $manifest->formRoot)) {
                    $wrong[] = $path . ' -> ' . $source . ' is outside the package';

                    continue;
                }

                $inside = substr($source, \strlen($manifest->formRoot));

                if (!isset($files[$inside])) {
                    $wrong[] = $path . ' -> ' . $source . ' is not in the package';
                }
            }
        }

        $this->assertSame([], $wrong, "Subforms that will not resolve:\n  " . implode("\n  ", $wrong));
        $this->assertGreaterThan(10, $checked, 'no subform was checked at all, so this proves nothing');
    }

    /**
     * Nothing in a package names the component that built it.
     *
     * The plan settled this for language strings and the argument is the same
     * for everything else: a package is consumed by com_extengen *and*
     * com_gengen, so naming either of them - or Meta-gen - is wrong whichever
     * one it names. `addruleprefix` was the last thing here that did, and it
     * pointed at a `Rule` namespace this component has never had.
     */
    public function testNothingInAPackageNamesAComponent(): void
    {
        $named = [];

        foreach ($this->package()->all() as $path => $contents) {
            foreach (['com_metagen', 'com_extengen', 'com_gengen', 'Component\\Metagen'] as $needle) {
                if (str_contains($path, $needle) || str_contains($contents, $needle)) {
                    $named[] = $path . ' names ' . $needle;
                }
            }
        }

        $this->assertSame([], $named, implode("\n  ", $named));
    }

    /**
     * The model travels with its output, and regenerates the same output.
     *
     * Which is the difference between importing a set of forms and importing a
     * language: a language that arrives somewhere can be regenerated there,
     * and this says the copy in the package is enough to do it with.
     */
    public function testTheModelInThePackageRegeneratesThePackage(): void
    {
        $first  = $this->package();
        $again  = $this->package(PackageReader::fromZip($this->zip($first))->model());
        $ignore = [MetalanguagePackage::MANIFEST];

        $this->assertSame(
            array_diff_key($first->all(), array_flip($ignore)),
            array_diff_key($again->all(), array_flip($ignore)),
            'the same model produced different forms the second time'
        );

        // The manifest is left out of that comparison for one field only, and
        // it is worth saying which: it records when the package was built.
        $this->assertSame(
            PackageReader::fromZip($this->zip($first))->manifest()->files,
            PackageReader::fromZip($this->zip($again))->manifest()->files
        );
    }

    /**
     * A file that arrives changed is reported rather than loaded.
     *
     * Checked by breaking one, because a hash nobody has seen fail is a hash
     * nobody knows is being compared. The failure it stands for is a truncated
     * download: a form file missing its last bytes still parses far enough for
     * Joomla to render a fieldset with nothing in it.
     */
    public function testAChangedFileIsNoticed(): void
    {
        $files = $this->package();
        $path  = $this->zip($files);
        $form  = MetalanguagePackage::formPath('Concept');

        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($path) === true);
        $zip->addFromString($form, substr($files->get($form), 0, -40));
        $zip->close();

        $problems = PackageReader::fromZip($path)->problems();

        $this->assertSame([$form . ' is not the file the manifest describes.'], $problems);
    }

    /**
     * And so is one that is not there at all.
     */
    public function testAMissingFileIsNoticed(): void
    {
        $path = $this->zip();
        $form = MetalanguagePackage::formPath('Concept');

        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($path) === true);
        $zip->deleteName($form);
        $zip->close();

        $this->assertSame(
            ['The manifest lists ' . $form . ', which is not in the package.'],
            PackageReader::fromZip($path)->problems()
        );
    }

    /**
     * Something that is not a package at all says so.
     */
    public function testSomethingThatIsNotAPackageSaysSo(): void
    {
        $path = sys_get_temp_dir() . '/metagen-notapackage-' . bin2hex(random_bytes(6)) . '.zip';

        $this->rubbish[] = $path;

        $zip = new \ZipArchive();

        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'holiday photos');
        $zip->close();

        $this->assertSame(
            ['This is not a metalanguage package: it has no manifest.'],
            PackageReader::fromZip($path)->problems()
        );
    }

    /**
     * A directory and everything under it.
     */
    private static function removeTree(string $root): void
    {
        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($tree as $entry) {
            if ($entry instanceof \SplFileInfo) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
        }

        rmdir($root);
    }
}
