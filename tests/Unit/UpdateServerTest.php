<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The update server tells sites the truth about this release: step 3.7.
 *
 * `updates.xml` repeats what the manifest already says - the element, the
 * version, the platform - beside a URL where that version can be fetched.
 * Repeating it by hand is how a site is offered a version that was never
 * released, or told an extension needs a Joomla it stopped running on.
 *
 * It is generated here, and `build/update-xml.php` has said since it was
 * written that "`UpdateServerTest` fails when the committed file and the
 * manifest disagree". That sentence came over from Exten-gen with the script
 * and the test did not: until now nothing in this repository read updates.xml
 * at all, and the release workflow's own check runs only when a tag is pushed -
 * which is the one moment it is too late to find out.
 *
 * It had drifted, in the way a hand-copied file does. The description read
 * "Model a Joomla extension, and generate it", which is Exten-gen's, on the one
 * screen where somebody is deciding whether to install this.
 *
 * @since  0.1.0
 */
final class UpdateServerTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function updates(): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->root() . '/updates.xml');

        $this->assertNotFalse($xml, 'updates.xml is not valid XML.');

        return $xml;
    }

    private function manifest(): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->root() . '/src/metagen.xml');

        $this->assertNotFalse($xml, 'The manifest is not valid XML.');

        return $xml;
    }

    /**
     * The committed file is the one the script writes.
     *
     * Without this the generator is a suggestion: somebody edits the XML, the
     * two drift, and the drift shows up as a site being offered the wrong
     * thing.
     */
    public function testTheCommittedFileIsWhatTheScriptGenerates(): void
    {
        $committed = (string) file_get_contents($this->root() . '/updates.xml');

        exec('php ' . escapeshellarg($this->root() . '/build/update-xml.php') . ' 2>&1', $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));

        $this->assertSame(
            $committed,
            (string) file_get_contents($this->root() . '/updates.xml'),
            'updates.xml differs from what build/update-xml.php writes. Run it and commit the result.'
        );
    }

    public function testItOffersTheVersionTheManifestDeclares(): void
    {
        $this->assertSame(
            trim((string) $this->manifest()->version),
            trim((string) $this->updates()->update->version)
        );
    }

    public function testItNamesTheExtensionTheManifestNames(): void
    {
        $this->assertSame(
            trim((string) $this->manifest()->name),
            trim((string) $this->updates()->update->element)
        );
    }

    /**
     * It describes this component rather than one of its siblings.
     *
     * The four repositories share a build script and most of a manifest, and
     * this is the field that says which of them a person is looking at. It is
     * resolved from the language key the manifest holds, so the update server
     * and the extension manager cannot say different things.
     */
    public function testItDescribesThisComponent(): void
    {
        $key = trim((string) $this->manifest()->description);

        $this->assertMatchesRegularExpression('/^[A-Z0-9_]+$/', $key, 'The manifest describes itself with a sentence rather than a key.');

        $ini = parse_ini_file(
            $this->root() . '/src/administrator/components/com_metagen/language/en-GB/com_metagen.sys.ini'
        ) ?: [];

        $this->assertArrayHasKey($key, $ini, 'Nothing defines ' . $key . ', so the extension manager shows the key.');

        $this->assertSame($ini[$key], trim((string) $this->updates()->update->description));
    }

    /**
     * The download URL names this version, in this repository.
     */
    public function testTheDownloadUrlPointsAtAReleaseOfThisVersion(): void
    {
        $version  = trim((string) $this->manifest()->version);
        $download = trim((string) $this->updates()->update->downloads->downloadurl);

        $this->assertStringStartsWith('https://', $download);
        $this->assertStringContainsString('HermanPeeren/Meta-gen', $download);
        $this->assertStringContainsString($version, $download);
        $this->assertStringEndsWith('.zip', $download);
    }

    /**
     * And it names the file the build actually produces.
     *
     * The release workflow uploads `build/com_metagen-*.zip` and the update
     * server links to a name it composes itself. A site follows that link and
     * gets a 404 if the two spell it differently, which no test above would
     * notice and which looks to the user like the release is broken.
     */
    public function testTheDownloadUrlNamesTheFileTheBuildProduces(): void
    {
        $version = trim((string) $this->manifest()->version);
        $element = trim((string) $this->manifest()->name);

        $this->assertSame(
            $element . '-' . $version . '.zip',
            basename(trim((string) $this->updates()->update->downloads->downloadurl))
        );

        $build = (string) file_get_contents($this->root() . '/build/build.php');

        $this->assertStringContainsString(
            $element . '-',
            $build,
            'The build does not name its archive the way the update server expects.'
        );
    }

    /**
     * It asks for the same Joomla and PHP that `script.php` insists on.
     *
     * An update server that offers a package the install script will refuse
     * produces a failed update and no explanation.
     */
    public function testItAgreesWithTheInstallScriptAboutTheEnvironment(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        preg_match("/minimumJoomlaVersion\s*=\s*'([^']+)'/", $script, $joomla);
        preg_match("/minimumPHPVersion\s*=\s*'([^']+)'/", $script, $php);

        $this->assertSame($php[1], trim((string) $this->updates()->update->php_minimum));

        // The platform is a regular expression, so the major version has to be
        // in it rather than compared literally.
        $platform = (string) $this->updates()->update->targetplatform['version'];

        $this->assertStringStartsWith(explode('.', $joomla[1])[0], $platform);
        $this->assertMatchesRegularExpression('/^' . $platform . '$/', $joomla[1]);
    }

    /**
     * The manifest points sites at this repository's file, and it is there.
     */
    public function testTheManifestPointsAtThisRepositorysUpdateServer(): void
    {
        $server = trim((string) $this->manifest()->updateservers->server);

        $this->assertStringContainsString('HermanPeeren/Meta-gen', $server);
        $this->assertSame('updates.xml', basename($server));
        $this->assertFileExists($this->root() . '/updates.xml');
    }
}
