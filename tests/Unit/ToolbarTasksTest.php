<?php

declare(strict_types=1);

namespace Yepr\Component\Metagen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every toolbar button names a controller this component has.
 *
 * A Joomla toolbar button carries a task as a plain string, `metalanguages.trash`,
 * and nothing checks it until somebody presses it: the dispatcher looks for
 * `MetalanguagesController`, does not find what the string named, and answers
 * *404 Invalid controller class*. Nothing in the component fails to build, no
 * test goes red, and the button looks exactly like the ones beside it.
 *
 * One had been wrong for as long as the screen has existed. The Change Status
 * dropdown said `metagen.trash` - the component's own name rather than the
 * list controller's - while `checkin` and `delete` two lines away said
 * `metalanguages.*`. Trashing a metalanguage 404'd.
 *
 * Read off the views rather than listed here, so a button added tomorrow is
 * covered without anybody remembering to add it.
 */
final class ToolbarTasksTest extends TestCase
{
    /**
     * Where this component keeps its admin side.
     */
    private function componentPath(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_metagen';
    }

    /**
     * Every `controller.task` string a view hands to a toolbar button.
     *
     * @return array<string, string[]>  File, relative to the component, => tasks in it.
     */
    private function tasksByFile(): array
    {
        $found    = [];
        $views    = $this->componentPath() . '/src/View';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($views));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Comments stripped first, through the tokeniser rather than a
            // regex. Both views carry commented-out buttons from before the
            // status dropdown was cut down - `//$childBar->publish('projects.
            // publish')` and two more - and a scan of raw source reads those as
            // live tasks. One of them names a controller that does not exist in
            // this component at all, so without this the test fails on dead
            // code and passes on nothing.
            $source = '';

            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (!\is_array($token)) {
                    $source .= $token;

                    continue;
                }

                if ($token[0] !== \T_COMMENT && $token[0] !== \T_DOC_COMMENT) {
                    $source .= $token[1];
                }
            }

            // The toolbar methods that take a task. `popupButton`, `dropdownButton`
            // and `preferences` take other things and are left out on purpose.
            preg_match_all(
                '/->(?:trash|checkin|delete|publish|unpublish|archive|addNew|editList|save|cancel|apply|standardButton)\(\s*'
                . '\'([a-zA-Z]+)\.([a-zA-Z]+)\'/',
                $source,
                $matches,
                PREG_SET_ORDER
            );

            foreach ($matches as $match) {
                $found[str_replace('\\', '/', substr($file->getPathname(), \strlen($this->componentPath()) + 1))][]
                    = $match[1] . '.' . $match[2];
            }
        }

        return $found;
    }

    public function testEveryToolbarTaskNamesAControllerThatExists(): void
    {
        $tasks = $this->tasksByFile();

        $this->assertNotEmpty($tasks, 'No toolbar tasks were found at all, so this test proves nothing.');

        foreach ($tasks as $file => $inFile) {
            foreach ($inFile as $task) {
                [$controller] = explode('.', $task, 2);

                $class = $this->componentPath() . '/src/Controller/' . ucfirst($controller) . 'Controller.php';

                $this->assertFileExists(
                    $class,
                    $file . ' names the task ' . $task . ', and there is no '
                    . ucfirst($controller) . 'Controller to answer it.'
                );
            }
        }
    }
}
