<?php

/**
 * Remove the languages the browser specs import, from the development site.
 *
 *   php tools/forget-imported-test-languages.php
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
 * **An exact name, never a pattern.** The row the import creates is called
 * `Cypress 1.0` - the importer composes the name and the version - and that is
 * matched whole. A `LIKE` here would eventually take a language somebody had
 * spent an afternoon modelling.
 */

declare(strict_types=1);

$root = \dirname(__DIR__) . '/joomla';

if (!is_file($root . '/configuration.php')) {
    fwrite(STDERR, "No site at {$root}: this is for the development install.\n");
    exit(1);
}

\defined('_JEXEC') || \define('_JEXEC', 1);

require $root . '/configuration.php';

/**
 * What the imported test language is stored as.
 *
 * From `cypress/e2e/import-lionweb.cy.js`, which builds a chunk naming itself
 * `Cypress` at version `1.0`.
 */
const SPEC_LANGUAGE_NAMES = [
    'Cypress 1.0',
];

$config = new JConfig();

try {
    $database = new PDO(
        'mysql:host=' . $config->host . ';dbname=' . $config->db,
        $config->user,
        $config->password
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Cannot reach the site database: ' . $e->getMessage() . "\n");
    exit(1);
}

$statement = $database->prepare(
    'DELETE FROM ' . $config->dbprefix . 'metagen_metalanguages WHERE name = ?'
);

$removed = 0;

foreach (SPEC_LANGUAGE_NAMES as $name) {
    $statement->execute([$name]);

    $count = $statement->rowCount();

    if ($count > 0) {
        printf("forgot %d language(s) named %s\n", $count, $name);
    }

    $removed += $count;
}

printf("%d row(s) removed\n", $removed);
