<?php

/**
 * @package     Metagen
 * @subpackage  Field
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Metagen\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Which language a language derives from: step 4.5.
 *
 * The metalanguages modelled in this component, offered as `key|version` - the
 * pair, because two versions of one language are two different parents. A
 * language deriving from ER1 1.0 does not inherit what 1.1 added, and a picker
 * that offered the key alone would let somebody say something the manifest
 * cannot record.
 *
 * **It lists what is modelled here, not what is installed elsewhere.** You
 * derive from a language you have in front of you; Exten-gen's and Gen-gen's
 * stores hold languages that were *imported*, which is the other end of the
 * same pipe. A parent named here reaches those sites in the package's manifest,
 * and whether they have it is `Ancestry::missing()`'s question rather than
 * this one's.
 *
 * **The value it already holds is kept even when it is not in the list.** A
 * model may name a parent that was deleted here, or that was typed against a
 * Meta-gen elsewhere, and a picker that silently dropped it would rewrite the
 * ancestry the first time somebody opened the form and saved it - which is the
 * quietest way to break a derived language there is.
 *
 * @since  1.5.0
 */
class MetalanguageField extends ListField
{
    /**
     * The field type, as a form refers to it.
     *
     * @var    string
     * @since  1.5.0
     */
    protected $type = 'Metalanguage';

    /**
     * Every metalanguage modelled here, and whatever this field already holds.
     *
     * @return  array<int, object>  The options, as Joomla wants them.
     *
     * @since   1.5.0
     */
    protected function getOptions(): array
    {
        $options = [];
        $seen    = [];

        foreach ($this->stored() as $binding => $label) {
            $options[]     = \Joomla\CMS\HTML\HTMLHelper::_('select.option', $binding, $label);
            $seen[$binding] = true;
        }

        // The value on the row, when the list does not have it. See the class
        // comment: dropping it would rewrite the ancestry on the next save.
        $value = trim((string) ($this->value ?? ''));

        if ($value !== '' && !isset($seen[$value])) {
            $options[] = \Joomla\CMS\HTML\HTMLHelper::_(
                'select.option',
                $value,
                Text::sprintf('COM_METAGEN_METALANGUAGE_DEPENDSON_NOT_HERE', $value)
            );
        }

        return array_merge(parent::getOptions(), $options);
    }

    /**
     * The languages this component holds, as `key|version` => label.
     *
     * A metalanguage's name and version live inside its stored model rather
     * than in columns of their own, so this reads the JSON. A row whose model
     * will not decode is skipped: a dropdown is not where somebody finds out
     * that a language is corrupt, and the edit screen for that language is.
     *
     * @return  array<string, string>
     *
     * @since   1.5.0
     */
    private function stored(): array
    {
        $database = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $database->getQuery(true)
            ->select($database->quoteName(['id', 'name', 'form_data']))
            ->from($database->quoteName('#__metagen_metalanguages'))
            ->order($database->quoteName('name') . ' ASC');

        try {
            $rows = $database->setQuery($query)->loadObjectList() ?: [];
        } catch (\RuntimeException) {
            return [];
        }

        $languages = [];

        foreach ($rows as $row) {
            // Not itself: a language deriving from itself is the shortest cycle
            // there is, and `Ancestry` survives one rather than enjoying it.
            if ((int) ($row->id ?? 0) === (int) ($this->form->getValue('id') ?? 0)) {
                continue;
            }

            try {
                $model = json_decode((string) ($row->form_data ?? ''), false, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            $name    = trim((string) ($model->name ?? $row->name ?? ''));
            $version = trim((string) ($model->version ?? ''));

            if ($name === '' || $version === '') {
                continue;
            }

            $languages[$name . '|' . $version] = $name . ' ' . $version;
        }

        return $languages;
    }
}
