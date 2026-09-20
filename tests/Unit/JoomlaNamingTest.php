<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The names Joomla derives from this component's class names are the right ones.
 *
 * Joomla works out what a model is called by pattern-matching its class name,
 * and everything else follows from the answer: which table it loads, which
 * context it stores state under, which filter form it looks for. When the
 * pattern gets it wrong nothing says so - the model simply asks for the wrong
 * thing, and the failure surfaces somewhere else entirely.
 *
 * **This cost a rename to find.** The component's entity was called
 * `Metalanguage` only on the second attempt. The first was `ModelLanguage`, and
 * `BaseModel::getName()` matches `/Model(.*)/i` - case-insensitively, against
 * the *fully qualified* name, so it matches the `Model` namespace segment
 * first - and then strips every lowercase `model` from what it captured. So
 * `...\Model\ModelLanguageModel` came out as `language`, and
 * `ModelLanguageTable` was never looked for: Joomla asked for a `language`
 * table, did not find one, and the edit screen died with "Table language not
 * supported. File not found."
 *
 * `ListModel::getFilterForm()` has its own version of the same assumption -
 * `explode('Model', static::class)` and take the second piece - which gave a
 * lone backslash, so the model asked for `filter_.xml`, got nothing, and the
 * list view died inside Joomla's own searchtools layout on a null `filterForm`.
 *
 * Neither failure names the class that caused it. The rule is simple once
 * stated: **an entity name must not contain "model", in any case**. Writing it
 * down as a test costs four lines and means the next name is checked before it
 * reaches a screen.
 */
final class JoomlaNamingTest extends TestCase
{
    /**
     * Every model this component ships, and what Joomla should call it.
     *
     * @return array<string, array{string, string}>
     */
    public static function models(): array
    {
        return [
            'Metalanguage'  => ['MetalanguageModel', 'metalanguage'],
            'Metalanguages' => ['MetalanguagesModel', 'metalanguages'],
            'GenerateForms' => ['GenerateFormsModel', 'generateforms'],
            'FormsDiagram'  => ['FormsDiagramModel', 'formsdiagram'],
        ];
    }

    /**
     * `BaseModel::getName()`, reproduced exactly.
     *
     * Copied rather than called because calling it needs a Joomla, and this
     * suite deliberately has none. If Joomla changes the rule this test goes
     * on asserting the old one - which is why the class it is checking is
     * named here too, so the pair is read together.
     */
    private function joomlaNameOf(string $class): string
    {
        $fqcn = 'Yepr\\Component\\Metagen\\Administrator\\Model\\' . $class;

        $this->assertMatchesRegularExpression('/Model(.*)/i', $fqcn, $class . ' has no Model in its name at all.');

        preg_match('/Model(.*)/i', $fqcn, $matches);

        return str_replace(['\\', 'model'], '', strtolower($matches[1]));
    }

    /**
     * @param string $class     The model class, without its namespace.
     * @param string $expected  What Joomla should decide it is called.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('models')]
    public function testJoomlaDerivesTheNameTheComponentMeans(string $class, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->joomlaNameOf($class),
            $class . ' is not called what it looks like it is called.'
        );
    }

    /**
     * And the filter form a list model asks for is one that is there.
     *
     * `ListModel::getFilterForm()` splits the class name on the literal string
     * `Model` and takes the second piece, which assumes the only one is the
     * namespace segment.
     */
    public function testAListModelAsksForAFilterFormThatExists(): void
    {
        $fqcn  = 'Yepr\\Component\\Metagen\\Administrator\\Model\\MetalanguagesModel';
        $parts = explode('Model', $fqcn);

        $this->assertGreaterThanOrEqual(2, \count($parts));

        $form = 'filter_' . str_replace('\\', '', strtolower($parts[1]));

        $this->assertSame('filter_metalanguages', $form);
        $this->assertFileExists(
            \dirname(__DIR__, 2) . '/src/administrator/components/com_metagen/forms/' . $form . '.xml',
            'The filter form Joomla will look for is not there, so the list view dies on a null filterForm.'
        );
    }

    /**
     * No entity here is named in a way Joomla will mangle.
     *
     * The rule, stated once, over every class the component has: an entity name
     * containing "model" in any case comes back wrong from both derivations
     * above, and neither failure mentions the name that caused it.
     */
    public function testNoEntityIsNamedInAWayJoomlaMangles(): void
    {
        $offenders = [];
        $root      = \dirname(__DIR__, 2) . '/src/administrator/components/com_metagen/src';

        foreach (['Model', 'Table', 'Controller'] as $kind) {
            foreach (glob($root . '/' . $kind . '/*.php') ?: [] as $file) {
                $entity = preg_replace('/(Model|Table|Controller)$/', '', basename($file, '.php'));

                if ($entity !== null && $entity !== '' && str_contains(strtolower($entity), 'model')) {
                    $offenders[] = basename($file);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These carry "model" in the entity part of their name, which Joomla strips: '
            . implode(', ', $offenders)
        );
    }
}
