<?php

/**
 * Put LedgerLang and the BalloonLedger project on an Exten-gen site.
 *
 *   php tools/export-language.php tests/Fixtures/languages/ledgerlang.json
 *   php tools/seed-ledger-demo.php build/LedgerLang-0.1.zip ../Exten-gen/joomla
 *
 * The rehearsal reset for the JWC demo: one command from "whatever the last
 * run left" to a site where LedgerLang is installed and a project written in
 * it opens on ER1's tabs and LedgerLang's. Run it again and it replaces both.
 *
 * **The package goes through Exten-gen's own import**, not around it: the
 * library's `MetalanguageImporter` is what the Import button calls, so the
 * ancestry guard runs and a LedgerLang that broke ER1 is refused here exactly
 * as it would be on stage. Only the upload and the form token are skipped.
 *
 * It boots the site rather than this repository's autoloader, because the
 * importer has to be the library the site has installed - the one the screen
 * would run - and two copies of `Yepr\Gen` in one process resolve to whichever
 * autoloader asked first.
 *
 * The project is `tests/Fixtures/projects/balloonledger.json`, which
 * `LedgerLangTest` walks against the language, bound to the language and
 * version the package says it is. It writes into Exten-gen's tables, which is
 * the one thing here that knows about a component: the demo is about the two
 * of them meeting.
 */

declare(strict_types=1);

$root    = \dirname(__DIR__);
$package = $argv[1] ?? '';
$site    = $argv[2] ?? \dirname($root) . '/Exten-gen/joomla';

if (!is_file($package)) {
    fwrite(STDERR, "Usage: php tools/seed-ledger-demo.php <LedgerLang package.zip> [<Exten-gen site>]\n");
    exit(1);
}

if (!is_file($site . '/administrator/components/com_extengen/extengen.xml')) {
    fwrite(STDERR, "No Exten-gen at {$site}.\n");
    exit(2);
}

$package = (string) realpath($package);

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

// An administrator request gets the library from the namespace map; this
// framework boot does not load that map, so the installed copy is named here.
JLoader::registerNamespace('Yepr\\Gen', JPATH_LIBRARIES . '/yepr_gen/src');

/** @var Joomla\Database\DatabaseInterface $db */
$db = $container->get(Joomla\Database\DatabaseInterface::class);

// --- The language -----------------------------------------------------------

try {
    $entry = (new Yepr\Gen\Joomla\Metalanguage\MetalanguageImporter($db, '#__extengen_metalanguages', JPATH_ROOT))
        ->import($package);
} catch (\RuntimeException $e) {
    fwrite(STDERR, 'Exten-gen refused the package: ' . $e->getMessage() . "\n");
    exit(3);
}

$manifest = Yepr\Gen\Core\Package\PackageReader::fromZip($package)->manifest();

printf("imported %s\n", $entry->label());

// --- The project ------------------------------------------------------------

$json = (string) file_get_contents($root . '/tests/Fixtures/projects/balloonledger.json');
$name = (string) (json_decode($json, false, 512, \JSON_THROW_ON_ERROR)->name ?? 'BalloonLedger');

$existing = $db->setQuery(
    $db->getQuery(true)
        ->select($db->quoteName('id'))
        ->from($db->quoteName('#__extengen_projects'))
        ->where($db->quoteName('name') . ' = :name')
        ->bind(':name', $name)
)->loadResult();

$row = (object) [
    'form_data'            => $json,
    'metalanguage_key'     => $manifest->key,
    'metalanguage_version' => $manifest->version,
];

if ($existing !== null) {
    $row->id = (int) $existing;

    $db->updateObject('#__extengen_projects', $row, 'id');

    printf("updated %s (id %d), written in %s %s\n", $name, $row->id, $manifest->key, $manifest->version);

    exit(0);
}

$row->name      = $name;
$row->alias     = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $name));
$row->published = 1;
$row->access    = 1;
$row->language  = '*';
$row->ordering  = 0;
$row->state     = 1;

$db->insertObject('#__extengen_projects', $row, 'id');

printf("seeded %s (id %d), written in %s %s\n", $name, (int) $row->id, $manifest->key, $manifest->version);
