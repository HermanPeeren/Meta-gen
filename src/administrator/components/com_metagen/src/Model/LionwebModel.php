<?php

/**
 * @package     Metagen
 * @subpackage  Metagen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Yepr\Gen\Core\Lionweb\Chunk;
use Yepr\Gen\Core\Lionweb\LionCoreLanguage;

/**
 * Reading a metalanguage in from LionWeb.
 *
 * A language written anywhere that speaks LionWeb - JCB by way of JcbInOut,
 * MPS, anything else - becomes a metalanguage here, and from there goes
 * everywhere a hand-written one goes: forms are generated from it, a package
 * carries it, Exten-gen imports that. Nothing downstream can tell where it came
 * from, which is the whole point.
 *
 * **The conversion is the library's**, in `Yepr\Gen\Core\Lionweb`, because
 * Exten-gen needs the same reader for the models written in these languages and
 * two implementations of one format drift. What is here is the part that is
 * Meta-gen's: which file, and the row it becomes.
 *
 * @since  1.4.0
 */
class LionwebModel extends BaseDatabaseModel
{
    /**
     * A file JcbInOut leaves on the same site, offered as the obvious one to
     * read. The two components meet on disk rather than over a wire.
     *
     * @since  1.4.0
     */
    public const JCBINOUT = 'administrator/components/com_jcbinout/data/derived/jcb-language.lionweb.json';

    /**
     * Where a chunk may be read from.
     *
     * Under the site and nothing else. An administrator can already reach the
     * filesystem by other means, so this is not a wall - but a field that reads
     * any path the web server can is a worse habit than one that does not, and
     * costs nothing to avoid.
     *
     * @throws \RuntimeException  When the path is outside the site, or unreadable.
     *
     * @since  1.4.0
     */
    public function readChunk(string $path): string
    {
        $root  = realpath(JPATH_ROOT);
        $given = realpath($path !== '' && $path[0] === '/' ? $path : JPATH_ROOT . '/' . $path);

        if ($given === false || !is_file($given)) {
            throw new \RuntimeException('There is no file at ' . $path . '.');
        }

        if ($root === false || !str_starts_with($given, $root)) {
            throw new \RuntimeException('A LionWeb chunk has to be a file under this site.');
        }

        $contents = file_get_contents($given);

        if ($contents === false) {
            throw new \RuntimeException('The file at ' . $path . ' could not be read.');
        }

        return $contents;
    }

    /**
     * Convert a chunk into the row a metalanguage is stored as.
     *
     * No database here, which is what lets the conversion be tested without
     * one. Storing it is the next call.
     *
     * @return array{name: string, version: string, form_data: string, entities: int, diagnostics: array<int, array<string, string>>}
     *
     * @throws \RuntimeException  When the chunk holds no language.
     *
     * @since  1.4.0
     */
    public function convert(string $json): array
    {
        $reader = new LionCoreLanguage(Chunk::fromJson($json));
        $stored = $reader->toStoredModel();

        if ($stored['name'] === '') {
            throw new \RuntimeException('The language in this chunk has no name, so there is nothing to call it.');
        }

        $encoded = json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new \RuntimeException('The converted language could not be encoded: ' . json_last_error_msg());
        }

        return [
            'name'        => (string) $stored['name'],
            'version'     => (string) $stored['version'],
            'form_data'   => $encoded,
            'entities'    => \count($stored['languageEntities']),
            'diagnostics' => $reader->diagnostics(),
        ];
    }

    /**
     * Store a converted language as a metalanguage of its own.
     *
     * Always a new row. Replacing one that shares a name would be a guess about
     * which of two languages called `JCB` somebody meant, and a metalanguage is
     * cheap to delete and expensive to get back.
     *
     * @param  array{name: string, version: string, form_data: string}  $converted
     *
     * @throws \RuntimeException  When the row will not save.
     *
     * @since  1.4.0
     */
    public function store(array $converted): int
    {
        $table = $this->getTable('Metalanguage');

        $name = $converted['version'] === ''
            ? $converted['name']
            : $converted['name'] . ' ' . $converted['version'];

        // Table reports a refusal by throwing in Joomla 6; the false its older
        // half still returns is caught alongside, so neither way goes quiet.
        try {
            $stored = $table->bind([
                'name'      => $name,
                'form_data' => $converted['form_data'],
                'published' => 1,
            ]) && $table->check() && $table->store();
        } catch (\Throwable $e) {
            throw new \RuntimeException('The metalanguage could not be saved: ' . $e->getMessage(), 0, $e);
        }

        if (!$stored) {
            throw new \RuntimeException('The metalanguage could not be saved.');
        }

        return (int) $table->id;
    }
}
