<?php

/**
 * What the suite needs before it can load any of the component's own classes.
 *
 * A Joomla source file opens with `defined('_JEXEC') or die;` so that it does
 * nothing when requested directly over the web instead of through the
 * application. Under PHPUnit that constant is not defined either, so loading
 * such a class calls `die` and the whole run stops - with no failure, no error
 * and no output at all, because `die` is not an exception and there is nothing
 * left to report it. A test written against a guarded class simply produces
 * silence. It cost an afternoon in Exten-gen; it costs nothing here.
 *
 * Defining it is what Joomla's own test suites do, and it is honest: the guard
 * asks whether we came in through the application, and a test run did.
 */

declare(strict_types=1);

// phpcs:disable PSR1.Files.SideEffects
require __DIR__ . '/../vendor/autoload.php';

if (!\defined('_JEXEC')) {
    \define('_JEXEC', 1);
}
// phpcs:enable PSR1.Files.SideEffects
