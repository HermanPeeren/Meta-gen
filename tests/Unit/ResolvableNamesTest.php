<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A class the component names is a class the component can reach.
 *
 * Nine field classes called `new ProjectRepository(...)` without importing it.
 * In their namespace that resolves to
 * `Yepr\Component\Metagen\Administrator\Field\ProjectRepository`, which does
 * not exist, so every project edit form and every metalanguage edit form was a
 * fatal. It arrived in 1.3, when thirteen copies of a loader were collapsed
 * into the repository: the call sites were rewritten and the imports were not,
 * and nothing loads these classes outside a running Joomla, so nothing said so.
 *
 * The rule is deliberately narrow. It resolves the unqualified class names a
 * file constructs, calls statically, or extends against that file's own imports
 * and namespace, and asks whether the result is a file on disk or a class this
 * project can be expected to have. It is not a type checker - PHPStan is, once
 * `src/` is in its scope - but it needs no Joomla to run, and it is exactly the
 * shape of mistake that a rename or an extracted class leaves behind.
 */
final class ResolvableNamesTest extends TestCase
{
    /**
     * Prefixes that belong to somebody else: Joomla, or a package in vendor.
     * Whether those resolve is not this project's business.
     */
    private const FOREIGN = [
        'Joomla\\',
        'Twig\\',
        'Yepr\\Gen\\',
    ];

    /**
     * @return array<string, string[]>
     */
    public static function componentClasses(): array
    {
        $root  = self::sourceRoot();
        $cases = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative         = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));
            $cases[$relative] = [$relative];
        }

        ksort($cases);

        return $cases;
    }

    /**
     * A file declares the namespace its own path spells out.
     *
     * PSR-4 is that correspondence and nothing else, so a file that breaks it
     * cannot be loaded by name at all - and *nothing else notices*, because
     * nothing asks for a class no request reaches. 1.5 found one:
     * `src/Factory/MVCFactory.php` declared `...\Administrator\Service`, and
     * `services/provider.php` had quietly gone on registering Joomla's stock
     * factory instead. 3.2 found the second: `View/GenerateProjectForm/HtmlView.php`
     * declared `View\GenerateForm`, so the one screen that generates the forms
     * of a modelled language could not be reached even by the link that pointed
     * at it - which itself named a third spelling again.
     *
     * The rule that would have caught both is one line of string comparison,
     * and neither the analyser nor the coding standard asks the question.
     */
    #[DataProvider('componentClasses')]
    public function testEveryFileDeclaresTheNamespaceItsPathImplies(string $relative): void
    {
        $source    = (string) file_get_contents(self::sourceRoot() . '/' . $relative);
        $namespace = $this->namespaceOf($source);

        if ($this->declaredIn($source) === []) {
            // A file with no class in it is a script, and PSR-4 says nothing
            // about where one lives. Asserted rather than skipped, so that the
            // run has something to report either way.
            $this->assertSame([], $this->declaredIn($source));

            return;
        }

        if (!str_starts_with($namespace, 'Yepr\\Component\\Metagen\\')) {
            // Nothing here is somebody else's. Exten-gen carries a vendored
            // third-party class that PSR-4 cannot find and reaches it with a
            // `require_once`; none of that came across in the split, so a file
            // under a foreign namespace here is a mistake rather than an
            // exception somebody decided on.
            $this->fail($relative . ' declares the foreign namespace ' . $namespace . '.');
        }

        $expected = rtrim(
            'Yepr\\Component\\Metagen\\Administrator\\' . str_replace('/', '\\', \dirname($relative)),
            '\\.'
        );

        $this->assertSame(
            $expected,
            $namespace,
            $relative . ' declares ' . $namespace . ', so nothing can load it by name.'
        );
    }

    /**
     * And the class it declares is named after the file it is in.
     *
     * The other half of PSR-4, and the half that bit during the split. Renaming
     * `GenerateProjectForm` to `GenerateForms` moved the file and left the class
     * inside it called something else, so Joomla's MVC factory asked for a
     * `GenerateFormsModel`, got nothing, and `AbstractView::getModel()` failed
     * on an undefined array key - a 500 with no message naming either name.
     *
     * The namespace rule above cannot see it: the namespace was right and only
     * the class was wrong. Both halves are one line of comparison each, and
     * between them they are what PSR-4 actually says.
     */
    #[DataProvider('componentClasses')]
    public function testEveryFileDeclaresAClassNamedAfterIt(string $relative): void
    {
        $source   = (string) file_get_contents(self::sourceRoot() . '/' . $relative);
        $declared = $this->declaredIn($source);

        if ($declared === []) {
            $this->assertSame([], $declared);

            return;
        }

        $this->assertContains(
            strtolower(basename($relative, '.php')),
            $declared,
            $relative . ' declares ' . implode(', ', $declared) . ', so nothing can load it by name.'
        );
    }

    /**
     * And every class it imports from this component is one that is there.
     *
     * The rules above read names the file *uses*. An unused `use` is invisible
     * to them and harmless to PHP, which never resolves one - right up until
     * somebody writes the call that needs it, and then it is a fatal naming a
     * class that left the repository months earlier.
     *
     * The split left exactly one: `FormsDiagramModel` imported
     * `Generator\\LanguageStringUtil`, which stayed in Exten-gen generating a
     * component's language strings. Nothing here used it, so nothing here
     * noticed.
     */
    #[DataProvider('componentClasses')]
    public function testEveryImportFromThisComponentResolves(string $relative): void
    {
        $source  = (string) file_get_contents(self::sourceRoot() . '/' . $relative);
        $prefix  = 'Yepr\\Component\\Metagen\\Administrator\\';
        $dangling = [];

        foreach ($this->importsOf($source) as $fqcn) {
            if (!str_starts_with($fqcn, $prefix)) {
                continue;
            }

            $path = str_replace('\\', '/', substr($fqcn, \strlen($prefix))) . '.php';

            if (!is_file(self::sourceRoot() . '/' . $path)) {
                $dangling[] = $fqcn;
            }
        }

        $this->assertSame(
            [],
            $dangling,
            $relative . ' imports classes that are not here: ' . implode(', ', $dangling)
        );
    }

    #[DataProvider('componentClasses')]
    public function testEveryNameItUsesResolves(string $relative): void
    {
        $source = (string) file_get_contents(self::sourceRoot() . '/' . $relative);

        $namespace = $this->namespaceOf($source);
        $imports   = $this->importsOf($source);
        $declared  = $this->declaredIn($source);

        $unresolved = [];

        foreach ($this->namesUsed($source) as $name) {
            // PHP matches class names without regard to case, so a file that
            // writes `Genericdataexception` under a `GenericDataException`
            // import is ugly but correct, and this rule is not about style.
            $key = strtolower($name);

            if (\in_array($key, $declared, true) || isset($imports[$key])) {
                continue;
            }

            // No import, so PHP resolves it inside the file's own namespace.
            $fqcn = $namespace . '\\' . $name;

            if (!$this->exists($fqcn)) {
                $unresolved[] = $name . ' (would be ' . $fqcn . ')';
            }
        }

        $this->assertSame(
            [],
            $unresolved,
            $relative . ' names these and nothing there answers to them: ' . implode(', ', $unresolved)
        );
    }

    /**
     * Unqualified class names the file constructs, calls statically, or extends.
     *
     * Read with PHP's own tokenizer rather than with a regular expression. The
     * first draft used regexes and reported `ParameterType` in a generator,
     * where the name sits inside a single-quoted string that is emitted into
     * generated code - not a class that file ever loads. A rule that cries wolf
     * about its own source text is one people learn to silence.
     *
     * Deliberately not every name: `\Foo` is rooted at the global namespace, and
     * a name in a docblock is not a class anybody will try to load.
     *
     * @return string[]
     */
    private function namesUsed(string $source): array
    {
        $tokens = token_get_all($source);
        $names  = [];
        $count  = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!\is_array($token)) {
                continue;
            }

            [$id, $text] = $token;

            // `new Foo` / `extends Foo`: the name is the next meaningful token.
            if ($id === T_NEW || $id === T_EXTENDS) {
                $name = $this->nextName($tokens, $i, $count);

                if ($name !== null) {
                    $names[] = $name;
                }

                continue;
            }

            // `Foo::` - a name immediately followed by a double colon.
            if ($id === T_STRING && $this->nextMeaningful($tokens, $i, $count) === T_DOUBLE_COLON) {
                // Not if it is already qualified: `Bar\Foo::` or `\Foo::`.
                $previous = $this->previousMeaningful($tokens, $i);

                if (
                    $previous === T_NAME_QUALIFIED || $previous === T_NAME_FULLY_QUALIFIED
                    || $previous === T_OBJECT_OPERATOR || $previous === T_DOUBLE_COLON
                ) {
                    continue;
                }

                $names[] = $text;
            }
        }

        $names = array_values(array_unique(array_filter(
            $names,
            static fn (string $n): bool => !\in_array(strtolower($n), ['self', 'static', 'parent'], true)
        )));

        sort($names);

        return $names;
    }

    /**
     * The unqualified name after position $i, or null when the name is already
     * qualified, rooted, or not a name at all (an anonymous class, say).
     *
     * @param  array<int, array{0:int, 1:string, 2:int}|string>  $tokens
     */
    private function nextName(array $tokens, int $i, int $count): ?string
    {
        for ($j = $i + 1; $j < $count; $j++) {
            $token = $tokens[$j];

            if (\is_array($token) && \in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return \is_array($token) && $token[0] === T_STRING ? $token[1] : null;
        }

        return null;
    }

    /** @param  array<int, array{0:int, 1:string, 2:int}|string>  $tokens */
    private function nextMeaningful(array $tokens, int $i, int $count): int|string|null
    {
        for ($j = $i + 1; $j < $count; $j++) {
            $token = $tokens[$j];

            if (\is_array($token) && \in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return \is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    /** @param  array<int, array{0:int, 1:string, 2:int}|string>  $tokens */
    private function previousMeaningful(array $tokens, int $i): int|string|null
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $token = $tokens[$j];

            if (\is_array($token) && \in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return \is_array($token) ? $token[0] : $token;
        }

        return null;
    }

    /** @return array<string, string> short name => fully qualified */
    private function importsOf(string $source): array
    {
        preg_match_all('/^use\s+([^;]+);/m', $source, $matches);

        $imports = [];

        foreach ($matches[1] as $import) {
            $import = trim($import);

            if (preg_match('/\s+as\s+(\w+)$/i', $import, $alias)) {
                $imports[strtolower($alias[1])] = preg_replace('/\s+as\s+\w+$/i', '', $import);

                continue;
            }

            $parts                        = explode('\\', $import);
            $imports[strtolower((string) end($parts))] = $import;
        }

        return $imports;
    }

    /** @return string[] classes and interfaces this file itself declares */
    private function declaredIn(string $source): array
    {
        preg_match_all('/^(?:final\s+|abstract\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $source, $matches);

        return array_map(strtolower(...), $matches[1]);
    }

    private function namespaceOf(string $source): string
    {
        preg_match('/^namespace\s+([^;]+);/m', $source, $match);

        return isset($match[1]) ? trim($match[1]) : '';
    }

    /**
     * Whether a fully qualified name is one this project can be expected to have.
     */
    private function exists(string $fqcn): bool
    {
        foreach (self::FOREIGN as $prefix) {
            if (str_starts_with($fqcn, $prefix)) {
                return true;
            }
        }

        if (class_exists($fqcn) || interface_exists($fqcn) || trait_exists($fqcn)) {
            return true;
        }

        // The component's own namespace maps onto src/ by PSR-4, and the
        // autoloader is configured for it, so a miss here is a real miss.
        $prefix = 'Yepr\\Component\\Metagen\\Administrator\\';

        if (str_starts_with($fqcn, $prefix)) {
            $path = str_replace('\\', '/', substr($fqcn, \strlen($prefix)));

            return is_file(self::sourceRoot() . '/' . $path . '.php');
        }

        // Anything else is outside this project: a global PHP class, or a
        // Joomla name written without its namespace, which is the caller's
        // business rather than a missing file here.
        return !str_contains($fqcn, '\\');
    }

    private static function sourceRoot(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_metagen/src';
    }
}
