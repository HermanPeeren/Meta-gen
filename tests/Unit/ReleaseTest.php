<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The manifest describes the package, and the package contains what it says.
 *
 * Step 3.7: this repository has run its gates since it was split out and has
 * never published anything, so nothing here has been installed the way a
 * stranger would install it. Development runs off a symlink into the working
 * copy, where every file is present whatever the manifest claims - which is
 * exactly the condition under which an inventory rots without anyone noticing.
 *
 * Ported from Exten-gen, where every rule below was written after the thing it
 * checks had already shipped broken: a folder missing from `<files>`, a
 * `schemapath` pointing at a directory that did not exist, a second manifest
 * kept in step by hand. Meta-gen's manifest was copied from that one, so it
 * inherits the shape and deserves the same reading.
 *
 * `PackageTest` in this repository is about a different package entirely - the
 * metalanguage packages Meta-gen *produces*. This is about the one it *is*.
 *
 * @since  0.1.0
 */
final class ReleaseTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function component(): string
    {
        return $this->root() . '/src/administrator/components/com_metagen/';
    }

    private function manifest(): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->root() . '/src/metagen.xml');

        $this->assertNotFalse($xml, 'The package manifest is not valid XML.');

        return $xml;
    }

    public function testEveryFileTheManifestClaimsIsThere(): void
    {
        $manifest = $this->manifest();
        $missing  = [];

        foreach ($manifest->administration->files->filename as $file) {
            if (!is_file($this->component() . (string) $file)) {
                $missing[] = (string) $file;
            }
        }

        foreach ($manifest->administration->files->folder as $folder) {
            if (!is_dir($this->component() . (string) $folder)) {
                $missing[] = (string) $folder . '/';
            }
        }

        $this->assertSame([], $missing, 'The manifest claims these and they are not there: ' . implode(', ', $missing));
    }

    /**
     * The other direction, which is the one that actually bites.
     *
     * A folder that exists and is not listed is shipped by nobody. It works
     * perfectly in development and is missing the moment somebody installs the
     * package - and the feature it held simply is not there, with no error to
     * say so.
     */
    public function testEveryFolderInTheComponentIsListed(): void
    {
        $listed = [];

        foreach ($this->manifest()->administration->files->folder as $folder) {
            $listed[] = (string) $folder;
        }

        $unlisted = [];

        foreach (scandir(rtrim($this->component(), '/')) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($this->component() . $entry)) {
                continue;
            }

            if (!\in_array($entry, $listed, true)) {
                $unlisted[] = $entry;
            }
        }

        $this->assertSame([], $unlisted, 'These folders exist but no manifest lists them: ' . implode(', ', $unlisted));
    }

    /**
     * And every file directly in it, which is the same rule one level down.
     *
     * `access.xml` and `config.xml` are named one by one rather than by their
     * folder, so a third file beside them is as invisible as an unlisted folder
     * and harder to spot: the directory looks right in every listing.
     */
    public function testEveryFileDirectlyInTheComponentIsListed(): void
    {
        $listed = [];

        foreach ($this->manifest()->administration->files->filename as $file) {
            $listed[] = (string) $file;
        }

        $unlisted = [];

        foreach (scandir(rtrim($this->component(), '/')) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_file($this->component() . $entry)) {
                continue;
            }

            if (!\in_array($entry, $listed, true)) {
                $unlisted[] = $entry;
            }
        }

        $this->assertSame([], $unlisted, 'These files are in the component and nothing ships them: ' . implode(', ', $unlisted));
    }

    /**
     * There is one manifest.
     *
     * `Installer::copyManifest()` puts it into the component folder during
     * installation, which is why a core component ships exactly one. A second
     * one in the repository is a second place to be wrong.
     */
    public function testThereIsOnlyOneManifest(): void
    {
        $this->assertFileExists($this->root() . '/src/metagen.xml');

        $this->assertFileDoesNotExist($this->component() . 'metagen.xml');

        $this->assertStringNotContainsString(
            '<filename>metagen.xml</filename>',
            (string) file_get_contents($this->root() . '/src/metagen.xml')
        );
    }

    /**
     * Every path the manifest names outside `<files>` is there too.
     *
     * The installer also reads `<install><sql><file>`, its uninstall
     * counterpart, and `<update><schemas><schemapath>` - without checking
     * first, so a path that is not in the package is an error dialog during
     * install rather than a missing feature afterwards. In Exten-gen a declared
     * `sql/updates/mysql` that did not exist failed the very first install
     * attempt with "Path is not a folder", while the `<files>` rules above
     * passed the whole time, because `sql` itself is listed and does exist.
     */
    public function testEveryOtherPathTheManifestNamesIsThere(): void
    {
        $manifest = $this->manifest();
        $missing  = [];

        foreach (['install', 'uninstall'] as $stage) {
            foreach ($manifest->{$stage}->sql->file ?? [] as $file) {
                if (!is_file($this->component() . (string) $file)) {
                    $missing[] = $stage . ': ' . (string) $file;
                }
            }
        }

        foreach ($manifest->update->schemas->schemapath ?? [] as $path) {
            if (!is_dir($this->component() . (string) $path)) {
                $missing[] = 'schemapath: ' . (string) $path;
            }
        }

        $this->assertSame([], $missing, 'The manifest names these and they are not there: ' . implode(', ', $missing));
    }

    /**
     * A declared schema path holds at least one version file.
     *
     * An empty folder passes the check above and is still wrong: Joomla reads
     * the highest version in it to fill `#__schemas`, so with none, a site has
     * no record of which schema it is on and no later update knows where to
     * start.
     */
    public function testTheSchemaPathHasAVersionToStartFrom(): void
    {
        foreach ($this->manifest()->update->schemas->schemapath ?? [] as $path) {
            $files = glob($this->component() . (string) $path . '/*.sql') ?: [];

            $this->assertNotSame([], $files, (string) $path . ' holds no version file.');
        }
    }

    /**
     * Every asset a layout asks for by name is one somebody declares.
     *
     * `useScript(...)` is a promise about a file and nothing at run time checks
     * it: an asset Joomla cannot resolve raises nothing at all and simply never
     * reaches the page, so the script is missing and the screen looks fine
     * until somebody uses it.
     *
     * This component declares none of its own. The one it asks for is the
     * shared reference dropdown, which lives in the library because three
     * components edit models with it - so the declaration to check against is
     * the library's, in `vendor/`, which is the copy this repository resolves
     * against.
     */
    public function testEveryAssetALayoutUsesIsDeclared(): void
    {
        $libraryManifest = $this->root() . '/vendor/yepr/generator-core/media/joomla.asset.json';

        $this->assertFileExists($libraryManifest, 'The shared library has no asset manifest.');

        $library  = json_decode((string) file_get_contents($libraryManifest), true, 512, JSON_THROW_ON_ERROR);
        $declared = array_column($library['assets'] ?? [], 'name');

        $own = $this->root() . '/src/media/com_metagen/joomla.asset.json';

        if (is_file($own)) {
            $mine     = json_decode((string) file_get_contents($own), true, 512, JSON_THROW_ON_ERROR);
            $declared = [...$declared, ...array_column($mine['assets'] ?? [], 'name')];
        }

        $missing = 0;
        $asked   = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->component() . 'tmpl', \FilesystemIterator::SKIP_DOTS)
        );

        $unknown = [];

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all(
                "/use(?:Script|Style)\(\s*'([^']+)'/",
                (string) file_get_contents($file->getPathname()),
                $matches
            );

            foreach ($matches[1] as $name) {
                $asked++;

                if (!\in_array($name, $declared, true)) {
                    $missing++;
                    $unknown[] = basename($file->getPathname()) . ': ' . $name;
                }
            }
        }

        $this->assertSame(0, $missing, 'Layouts ask for assets nothing declares: ' . implode(', ', $unknown));

        // The guard. This rule is about finding a name nobody declares, and a
        // layout directory it could not read would find none and say so
        // cheerfully - which is how a check about missing things goes missing.
        $this->assertGreaterThan(0, $asked, 'No layout asks for any asset, so this rule checked nothing.');
    }

    /**
     * The install script and the manifest agree about the environment.
     */
    public function testTheInstallScriptRequiresJoomlaSix(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        $this->assertMatchesRegularExpression('/minimumJoomlaVersion\s*=\s*\x276\./', $script);
        $this->assertMatchesRegularExpression('/minimumPHPVersion\s*=\s*\x278\.3\x27/', $script);
    }

    /**
     * And it knows which library release it needs, because the build reads that
     * number to decide what to bundle.
     */
    public function testTheInstallScriptNamesTheLibraryItNeeds(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        $this->assertMatchesRegularExpression("/LIBRARY_MINIMUM\s*=\s*'[0-9]+\.[0-9]+\.[0-9]+'/", $script);
        $this->assertStringContainsString("LIBRARY = 'yepr_gen'", $script);
    }

    /**
     * The composer dependency and the install script ask for the same library.
     *
     * Two places name a version and only one of them is checked at run time.
     * `composer.json` decides what the tests run against; `LIBRARY_MINIMUM`
     * decides what an installed site is allowed to have, and what the release
     * build bundles. Let them drift and the suite passes against a library the
     * package will not ship, or - the direction that was actually true here -
     * a site is handed a library older than the code that was tested.
     *
     * This repository had exactly that on the eve of its first release:
     * `composer.json` asked for ^0.8, which is where `Lionweb\ChunkBuilder`
     * arrived, and `script.php` still said 0.6.0 from before the LionWeb import
     * existed. A site that installed it would have got a library with no
     * `Lionweb` namespace in it and a fatal on the import screen. Exten-gen and
     * Gen-gen have had this rule for some time; Meta-gen was the one repository
     * without it, which is why it was the one that drifted.
     */
    public function testTheDeclaredDependencyMatchesTheInstallScript(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        preg_match("/LIBRARY_MINIMUM\s*=\s*'([^']+)'/", $script, $minimum);

        $composer = json_decode(
            (string) file_get_contents($this->root() . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        $constraint = $composer['require']['yepr/generator-core'];

        $this->assertSame(
            '^' . implode('.', \array_slice(explode('.', $minimum[1]), 0, 2)),
            $constraint,
            'composer.json asks for ' . $constraint . ' but script.php insists on ' . $minimum[1] . '.'
        );
    }

    /**
     * The build refuses a library older than the install script demands.
     *
     * Read out of the build script rather than checked by building, because a
     * build needs the network and this needs to fail on the commit that breaks
     * it.
     */
    public function testTheBuildComparesTheLibraryVersionItPicks(): void
    {
        $build = (string) file_get_contents($this->root() . '/build/build.php');

        $this->assertStringContainsString(
            'version_compare($version, $required,',
            $build,
            'build.php picks a local library without checking it is new enough.'
        );
    }
}
