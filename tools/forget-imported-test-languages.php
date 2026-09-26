<?php

/**
 * Remove the languages the browser specs import, from the development site.
 *
 *   php tools/forget-imported-test-languages.php
 *   php tools/forget-imported-test-languages.php ../some/site
 *
 * `import-lionweb.cy.js` imports a LionWeb chunk through the screens, which is
 * the point of it: a language written somewhere else has to arrive here as
 * something this component can open. What it never did is take it away again,
 * and the import makes a new row each time rather than updating one - so every
 * run left another, and the site had reached twenty of them against the two
 * languages that are actually modelled here.
 *
 * Untidy, and worse than untidy: the spec asserts on a language appearing in a
 * list, and a list holding nineteen earlier copies of it is a list where that
 * assertion passes before the import has done anything at all.
 *
 * It boots Joomla and uses the site's own database driver, for the reason
 * `seed-metalanguage.php` gives: `JConfig` is a class Joomla writes at install
 * time and no source file declares, so a tool built on it cannot be analysed -
 * and `tools/` is analysed here with everything else. The first version of this
 * file did read `configuration.php` into a `JConfig`, passed on a machine whose
 * `/joomla` is an installed site, and failed on CI where it is not. Which is
 * the trap CLAUDE.md names, found for the third time.
 *
 * **An exact name, never a pattern.** The row the import creates is called
 * `Cypress 1.0` - the importer composes the name and the version - and that is
 * matched whole. A `LIKE` here would eventually take a language somebody had
 * spent an afternoon modelling.
 */

declare(strict_types=1);

$root = \dirname(__DIR__);
$site = $argv[1] ?? $root . '/joomla';

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No Joomla at {$site}: this is for the development install.\n");
    exit(2);
}

\defined('_JEXEC') || \define('_JEXEC', 1);
\define('JPATH_BASE', $site);

require_once $site . \DIRECTORY_SEPARATOR . 'includes' . \DIRECTORY_SEPARATOR . 'defines.php';
require_once $site . \DIRECTORY_SEPARATOR . 'includes' . \DIRECTORY_SEPARATOR . 'framework.php';

$container = Joomla\CMS\Factory::getContainer();

$container->alias('session', 'session.cli')
    ->alias(Joomla\CMS\Session\Session::class, 'session.cli')
    ->alias(Joomla\Session\Session::class, 'session.cli')
    ->alias(Joomla\Session\SessionInterface::class, 'session.cli');

Joomla\CMS\Factory::$application = $container->get(Joomla\Console\Application::class);

// --- Forget -----------------------------------------------------------------

/**
 * What the imported test language is stored as.
 *
 * From `cypress/e2e/import-lionweb.cy.js`, which builds a chunk naming itself
 * `Cypress` at version `1.0`.
 */
$names = ['Cypress 1.0'];

/** @var Joomla\Database\DatabaseInterface $db */
$db = $container->get(Joomla\Database\DatabaseInterface::class);

$removed = 0;

foreach ($names as $name) {
    $query = $db->getQuery(true)
        ->delete($db->quoteName('#__metagen_metalanguages'))
        ->where($db->quoteName('name') . ' = :name')
        ->bind(':name', $name);

    $db->setQuery($query)->execute();

    $count = $db->getAffectedRows();

    if ($count > 0) {
        printf("forgot %d language(s) named %s\n", $count, $name);
    }

    $removed += $count;
}

printf("%d row(s) removed\n", $removed);
