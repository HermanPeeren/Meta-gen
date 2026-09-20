<?php

/**
 * Put a fixture metalanguage into a Joomla site's Meta-gen table.
 *
 *   php tools/seed-metalanguage.php                  # er1, into ./joomla
 *   php tools/seed-metalanguage.php lioncore-m3 ../site
 *
 * A metalanguage is a model of a modelling language, in LionCore M3. Step 3.1
 * needed one for the first time: the six reference dropdowns in the meta-model
 * used to read the stored language from the database, and there was no stored
 * one anywhere - not on this machine, not in the repository - so nothing had
 * ever exercised them.
 *
 * Building one through the form is slow and brittle, and these are the fixtures
 * the unit tests read, so what a spec opens on screen is what those tests
 * describe exactly.
 *
 * It boots Joomla and uses the site's own database driver rather than reading
 * `configuration.php` into a `JConfig` and opening a mysqli by hand. That is
 * not tidiness: `JConfig` is a class Joomla writes at install time and no
 * source file declares, so a tool built that way cannot be analysed, and this
 * one is analysed with everything else.
 *
 * Idempotent: seeding the same fixture twice updates the row rather than adding
 * a second one.
 */

declare(strict_types=1);

$root    = \dirname(__DIR__);
$fixture = $argv[1] ?? 'er1';
$site    = $argv[2] ?? $root . '/joomla';

$modelPath = $root . '/tests/Fixtures/languages/' . $fixture . '.json';

if (!is_file($modelPath)) {
    fwrite(STDERR, "No fixture at {$modelPath}.\n");
    exit(1);
}

if (!is_file($site . '/configuration.php')) {
    fwrite(STDERR, "No Joomla at {$site}.\n");
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

// --- Seed -------------------------------------------------------------------

$json = (string) file_get_contents($modelPath);
$name = (string) (json_decode($json, false, 512, \JSON_THROW_ON_ERROR)->name ?? $fixture);

/** @var Joomla\Database\DatabaseInterface $db */
$db = $container->get(Joomla\Database\DatabaseInterface::class);

$existing = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__metagen_metalanguages'))
        ->where($db->quoteName('name') . ' = :name')
        ->bind(':name', $name)
)->loadResult();

if ($existing !== null) {
    $row = (object) ['id' => (int) $existing, 'form_data' => $json];

    $db->updateObject('#__metagen_metalanguages', $row, 'id');

    printf("updated %s (id %d)\n", $name, (int) $existing);

    exit(0);
}

$row = (object) [
    'name'      => $name,
    'alias'     => strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name)),
    'form_data' => $json,
    'published' => 1,
    'access'    => 1,
    'language'  => '*',
    'ordering'  => 0,
    'state'     => 1,
];

$db->insertObject('#__metagen_metalanguages', $row, 'id');

printf("seeded %s (id %d)\n", $name, (int) $row->id);
