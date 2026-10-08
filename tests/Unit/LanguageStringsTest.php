<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every language constant this component names has a string: step 5.1.
 *
 * Joomla renders an undefined constant as itself, in capitals, and logs
 * nothing - so a hole in a language file is a screen that looks broken to a
 * person and fine to every other gate.
 *
 * Meta-gen had no hole when this was written. Exten-gen and Gen-gen each had
 * one in the same place: a menu entry whose string was in the main `.ini` and
 * not in the `.sys.ini`, which is the only file Joomla reads for the menu. So
 * the second test is about that file specifically.
 *
 * Constants are collected from quoted strings and from XML text, not from bare
 * words, because `JPATH_ROOT` and `JSON_THROW_ON_ERROR` are PHP constants that
 * look exactly like language keys. The same test is in Exten-gen and Gen-gen,
 * with only the component's name changed.
 *
 * @since  0.3.0
 */
final class LanguageStringsTest extends TestCase
{
    private const COMPONENT = 'com_metagen';

    /**
     * The component's own prefix, which every key of its own starts with.
     */
    private function prefix(): string
    {
        return strtoupper(self::COMPONENT) . '_';
    }

    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function adminRoot(): string
    {
        return $this->root() . '/src/administrator/components/' . self::COMPONENT;
    }

    /**
     * The keys one ini file defines.
     *
     * @return array<string, true>
     */
    private function keysIn(string $file): array
    {
        $keys = [];

        foreach ((array) file($file) as $line) {
            if (preg_match('/^\s*([A-Z0-9_]+)\s*=/', (string) $line, $match)) {
                $keys[$match[1]] = true;
            }
        }

        return $keys;
    }

    /**
     * @return array<string, true>
     */
    private function componentKeys(): array
    {
        $keys = [];

        foreach (glob($this->adminRoot() . '/language/en-GB/*.ini') ?: [] as $file) {
            $keys += $this->keysIn($file);
        }

        return $keys;
    }

    /**
     * Every language constant named in one file, with the file it was in.
     *
     * Quoted, or the whole text of an XML element: `label="JNO"`,
     * `Text::_('COM_X_Y')`, `<option value="0">JNO</option>`.
     *
     * @return string[]
     */
    private function constantsIn(string $text): array
    {
        preg_match_all(
            '/(?:["\'>])((?:COM|J|PLG|MOD|LIB|TPL)[A-Z0-9]*_[A-Z0-9_]*[A-Z0-9])(?=["\'<])/',
            $text,
            $matches
        );

        return array_values(array_unique($matches[1]));
    }

    /**
     * Constants by the files that name them.
     *
     * `generator_templates/` is left out: those are Twig templates for a
     * *generated* component, and the constants in them are that component's.
     * So are the language files, which name every key and define them too.
     *
     * @return array<string, string[]>
     */
    private function namedConstants(): array
    {
        $named = [];
        $tree  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root() . '/src', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (
                !preg_match('/\.(php|xml|js)$/', $path)
                || str_contains($path, '/generator_templates/')
                || str_contains($path, '/language/')
                || str_contains($path, '/vendor/')
            ) {
                continue;
            }

            foreach ($this->constantsIn($this->withoutComments($path)) as $constant) {
                $named[$constant][] = substr($path, \strlen($this->root()) + 1);
            }
        }

        ksort($named);

        return $named;
    }

    /**
     * A file's text, with PHP comments removed.
     *
     * A commented-out example is not a use. Exten-gen's form generator carries
     * the XML it builds as a comment, labelled with a tutorial's `COM_FOOS_*`,
     * and that is not a string anybody will see.
     */
    private function withoutComments(string $path): string
    {
        $text = (string) file_get_contents($path);

        if (!str_ends_with($path, '.php')) {
            return $text;
        }

        $kept = '';

        foreach (token_get_all($text) as $token) {
            if (\is_array($token) && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $kept .= \is_array($token) ? $token[1] : $token;
        }

        return $kept;
    }

    public function testTheScanFindsTheConstantsItIsMeantTo(): void
    {
        // A pattern that matched nothing would make every test below pass.
        $named = $this->namedConstants();

        self::assertGreaterThan(50, \count($named));
        self::assertArrayHasKey('COM_METAGEN_BUTTON_GENERATE', $named);
        self::assertArrayHasKey('JGLOBAL_TITLE', $named);
        self::assertArrayNotHasKey('JPATH_ROOT', $named, 'a PHP constant is not a language key');
    }

    public function testEveryConstantOfThisComponentHasAString(): void
    {
        $keys    = $this->componentKeys();
        $missing = [];

        foreach ($this->namedConstants() as $constant => $files) {
            if (str_starts_with($constant, $this->prefix()) && !isset($keys[$constant])) {
                $missing[] = $constant . '  (' . implode(', ', array_unique($files)) . ')';
            }
        }

        self::assertSame([], $missing, 'named, and defined in no language file');
    }

    public function testTheManifestsStringsAreInTheSystemFile(): void
    {
        $manifest = (string) file_get_contents($this->root() . '/src/' . substr(self::COMPONENT, 4) . '.xml');
        $system   = $this->keysIn($this->adminRoot() . '/language/en-GB/' . self::COMPONENT . '.sys.ini');
        $missing  = [];

        foreach ($this->constantsIn($manifest) as $constant) {
            if (!isset($system[$constant])) {
                $missing[] = $constant;
            }
        }

        self::assertNotSame([], $this->constantsIn($manifest), 'the manifest names its menu entries');
        self::assertSame([], $missing, 'the menu reads these from the .sys.ini only');
    }

    public function testEveryOtherConstantIsOneJoomlaDefines(): void
    {
        $languages = $this->root() . '/joomla/administrator/language/en-GB';

        if (!is_dir($languages)) {
            self::markTestSkipped('No Joomla at /joomla, so its own strings cannot be read.');
        }

        // Read from the installed core rather than written down here, so that a
        // key Joomla renames is found the day it happens.
        $core = [];

        foreach (
            array_merge(
                glob($languages . '/*.ini') ?: [],
                glob($this->root() . '/joomla/language/en-GB/*.ini') ?: []
            ) as $file
        ) {
            $core += $this->keysIn($file);
        }

        $own     = $this->componentKeys();
        $missing = [];

        foreach ($this->namedConstants() as $constant => $files) {
            // `\defined('JPATH_ROOT')` is quoted and is not a language key.
            if (
                str_starts_with($constant, $this->prefix())
                || str_starts_with($constant, 'JPATH_')
                || isset($core[$constant])
                || isset($own[$constant])
            ) {
                continue;
            }

            $missing[] = $constant . '  (' . implode(', ', array_unique($files)) . ')';
        }

        self::assertSame([], $missing, 'neither this component nor Joomla defines these');
    }
}
