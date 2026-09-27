<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A view can reach its model on a filesystem that cares about case.
 *
 * Joomla does not use the `view=` parameter to find a view's model. The
 * controller asks the *view* what it is called - `AbstractView::getName()`,
 * which takes the last segment of the view's namespace and **lower-cases all of
 * it** - and hands that to `MVCFactory::createModel()`, which `ucfirst`s it back
 * and autoloads the class of that name. So a view in `View\GenerateForms`
 * causes a load of `Model\GenerateformsModel`, with a small `f`, and the file
 * has to be spelled that way.
 *
 * Named the obvious way, `GenerateFormsModel.php`, it worked on every Windows
 * machine and on no Linux one. `createModel()` returned null, the controller
 * skipped `setModel()`, and the view called a method on null - reported as a
 * 500 with, in the log, `Undefined array key ""` from inside Joomla. Nothing
 * named the file that was missing.
 *
 * `JoomlaNamingTest` checks the other direction: what Joomla decides a model is
 * called once it has one. This checks that it can find one at all, which has to
 * happen first.
 *
 * Only views that ask for a model are required to have one. Several here render
 * from their own state and never call `getModel()`, and giving them a model to
 * satisfy a test would be inventing a file to keep a rule happy.
 *
 * @since  0.3.0
 */
final class ViewModelNamesTest extends TestCase
{
    private function componentRoot(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_metagen/src';
    }

    /**
     * Every view that asks for a model, with the file Joomla would load.
     *
     * @return array<string, array{string, string}>  Only views that use a model.
     */
    public static function views(): array
    {
        $root  = \dirname(__DIR__, 2) . '/src/administrator/components/com_metagen/src/View';
        $cases = [];

        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($root . '/' . $entry)) {
                continue;
            }

            // Only the views that ask for one. A view that renders from its own
            // state needs no model, and listing it here would be a case that
            // asserts nothing.
            $view = $root . '/' . $entry . '/HtmlView.php';

            if (!is_file($view) || !str_contains((string) file_get_contents($view), 'getModel(')) {
                continue;
            }

            // `AbstractView::getName()` then `MVCFactory::createModel()`, in
            // that order and reproduced exactly.
            $derived = strtolower($entry);
            $cases[$entry] = [$entry, ucfirst($derived) . 'Model.php'];
        }

        return $cases;
    }

    /**
     * A view that asks for a model has one Joomla can actually load.
     *
     * The file list is compared as strings rather than with `is_file`, because
     * `is_file` on Windows answers the question this test exists to ask - it
     * finds `GenerateFormsModel.php` when asked for `GenerateformsModel.php`,
     * which is the whole bug, and a check written that way passes on the machine
     * where the bug does not bite.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('views')]
    public function testAViewThatWantsAModelCanLoadIt(string $view, string $expected): void
    {
        $models = scandir($this->componentRoot() . '/Model') ?: [];

        $this->assertContains(
            $expected,
            $models,
            $view . ' calls getModel(), so Joomla will autoload ' . $expected
            . ' - and that is not the name of any file in Model/. On Windows it'
            . ' will resolve anyway; on Linux the view is handed null.'
        );
    }

    /**
     * And the directory listing is the real one.
     *
     * Guards the test above from passing because `scandir` returned nothing at
     * all - an empty haystack with an empty needle list asserts nothing.
     */
    public function testThereAreViewsAndModelsToCheck(): void
    {
        $this->assertNotSame([], self::views(), 'No view directories were found.');

        $models = array_values(array_filter(
            scandir($this->componentRoot() . '/Model') ?: [],
            static fn (string $f): bool => str_ends_with($f, 'Model.php')
        ));

        $this->assertNotSame([], $models, 'No model files were found.');
    }
}
