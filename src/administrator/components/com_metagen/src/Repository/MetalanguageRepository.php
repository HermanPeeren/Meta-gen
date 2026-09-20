<?php

/**
 * @package     Metagen
 * @subpackage  Repository
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Repository;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Loads a stored project form: a model of the metalanguage, in LionWeb terms.
 *
 * Returns the decoded value rather than a type, and that is deliberate. A
 * project has a `Project` class because its shape is known and the generators
 * depend on it; the meta-model does not have one yet, because modelling it
 * properly is Meta-gen's work and inventing a half-type here would be a shape to
 * unpick later rather than a foundation.
 *
 * What it does remove is the duplication: eight copies of the same query, six of
 * them in reference-field classes that differ only in which kind of node they
 * offer.
 *
 * @since  0.9.0
 */
final class MetalanguageRepository
{
    /**
     * @param  DatabaseInterface  $db  The database to read from.
     *
     * @since  0.9.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * The stored project form with this id, decoded, or null when there is none.
     *
     * @since  0.9.0
     */
    public function findRaw(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('form_data'))
            ->from($this->db->quoteName('#__metagen_metalanguages'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $this->db->setQuery($query);

        $json = $this->db->loadResult();

        if (!\is_string($json) || trim($json) === '') {
            return null;
        }

        $decoded = json_decode($json);

        return \is_object($decoded) ? $decoded : null;
    }
}
