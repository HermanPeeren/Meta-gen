<?php

/**
 * @package     Metagen

 * @subpackage  Metagen component
 * @version     0.9.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Metagen\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormHelper;
// todo: clean up unused use clauses
use Joomla\CMS\Date\Date;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\WorkflowBehaviorTrait;
use Joomla\CMS\MVC\Model\WorkflowModelInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Table\TableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\UCM\UCMType;
use Joomla\CMS\Versioning\VersionableModelTrait;
use Joomla\CMS\Workflow\Workflow;
use Joomla\Component\Categories\Administrator\Helper\CategoriesHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use Yepr\Component\Metagen\Administrator\Reference\LionCoreM3;
use Yepr\Gen\Core\Reference\ReferenceIndex;

/**
 * Item Model for a project form.
 */
class MetalanguageModel extends AdminModel
{
	/**
	 * The type alias for this content type.
	 *
	 * @var    string
	 */
	public $typeAlias = 'com_metagen.metalanguage';

	/**
	 * The context used for the associations table
	 *
	 * @var    string
	 */
	protected $associationsContext = 'com_metagen.item'; // todo: is now same as project???!!!

	/**
	 * Batch copy/move command. If set to false, the batch copy/move command is not supported
	 *
	 * @var  string
	 */
	protected $batch_copymove = 'category_id';

	/**
	 * Allowed batch commands
	 *
	 * @var array
	 */
	protected $batch_commands = array(
		'assetgroup_id' => 'batchAccess',
		'language_id'   => 'batchLanguage',
	);


	/**
	 * Constructor.
	 *
	 * @param   array                 $config       An array of configuration options (name, state, dbo, table_path, ignore_request).
	 * @param   MVCFactoryInterface   $factory      The factory.
	 * @param   FormFactoryInterface  $formFactory  The form factory.
	 *
	 * @throws  \Exception
	 */
	public function __construct($config = [], MVCFactoryInterface $factory = null, FormFactoryInterface $formFactory = null)
	{
		parent::__construct($config, $factory, $formFactory);

		// Add the namespace to find the meta-forms (a.t.m. only LIonCore_M3) to define the project-forms
		//FormHelper::addFormPrefix('Yepr\\Component\\Metagen\\Administrator\\MetaMetalanguage\\LIonCore_M3');
		FormHelper::addFormPath(JPATH_ROOT . '/administrator/components/com_metagen/forms/LIonCore_M3/');
	}

	/**
	 * Method to get the row form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  Form|boolean  A Form object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_metagen.metalanguage', 'metalanguage', array('control' => 'jform', 'load_data' => $loadData));

		if (empty($form)) {
			return false;
		}

		return $form;
	}
	/**
	 * Everything in this metalanguage that a reference field can point at.
	 *
	 * The same mechanism 1.9 built for projects, arriving here at 3.1. Until
	 * now the six M3 dropdowns each loaded the stored metalanguage from the
	 * database and walked it, so they described what had been saved: a concept
	 * added a minute ago could not be extended, and a concept renamed on screen
	 * kept its old name in every list until the form was saved and reopened.
	 *
	 * It is the model's job rather than the view's because it reads the stored
	 * model, and reading the stored model happens here and nowhere else.
	 *
	 * @return  array  index and types, as <metagen-reference> expects them.
	 *
	 * @since   1.1.0
	 */
	public function getReferenceIndex(): array
	{
		$item   = $this->getItem();
		$stored = empty($item->form_data) ? null : json_decode((string) $item->form_data);

		// A metalanguage that will not decode is reported by loadFormData(),
		// which runs for the same request. An index of nothing is the honest
		// answer here, and it leaves the form usable.
		//
		// The mechanism is the shared library's, because Exten-gen and Gen-gen
		// ask the same question of their own models; the table is this
		// component's, because that is the only thing that differs.
		return ReferenceIndex::fromTable(LionCoreM3::TABLE)
			->payload(is_object($stored) ? $stored : null);
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  mixed  The data for the form.
	 */
	protected function loadFormData()
	{
		$app = Factory::getApplication();

		$item = $this->getItem();

		//$this->preprocessData('com_metagen.metalanguage', $data);

        // deserialise all data
        $data = json_decode($item->form_data);

		return $data;
	}

	/**
	 * Method to get a single record.
	 *
	 * @param   integer  $pk  The id of the primary key.
	 *
	 * @return  mixed  Object on success, false on failure.
	 */
	public function getItem($pk = null)
	{
		$item = parent::getItem($pk);

		// Load associated metagen items
		$assoc = Associations::isEnabled();

		if ($assoc) {
			$item->associations = array();

			if ($item->id != null) {
				$associations = Associations::getAssociations('com_metagen', '#__metagen_metalanguages', 'com_metagen.item', $item->id, 'id', null);

				foreach ($associations as $tag => $association) {
					$item->associations[$tag] = $association->id;
				}
			}
		}

		return $item;
	}

	/**
	 * Allows preprocessing of the Form object.
     *
     * @param   Form    $form   The form object
     * @param   array   $data   The data to be merged into the form object
     * @param   string  $group  The plugin group to be executed
	 *
	 * @return  void
	 */
	protected function preprocessForm(Form $form, $data, $group = 'content')
	{
		// Association contact items
		if (Associations::isEnabled()) {
			$languages = LanguageHelper::getContentLanguages(false, true, null, 'ordering', 'asc');

			if (count($languages) > 1) {
				$addform = new \SimpleXMLElement('<form />');
				$fields = $addform->addChild('fields');
				$fields->addAttribute('name', 'associations');
				$fieldset = $fields->addChild('fieldset');
				$fieldset->addAttribute('name', 'item_associations');

				foreach ($languages as $language) {
					$field = $fieldset->addChild('field');
					$field->addAttribute('name', $language->lang_code);
					$field->addAttribute('type', 'modal_metagen');
					$field->addAttribute('language', $language->lang_code);
					$field->addAttribute('label', $language->title);
					$field->addAttribute('translate_label', 'false');
					$field->addAttribute('select', 'true');
					$field->addAttribute('new', 'true');
					$field->addAttribute('edit', 'true');
					$field->addAttribute('clear', 'true');
				}

				$form->load($addform, false);
			}
		}

		parent::preprocessForm($form, $data, $group);
	}

    /**
     * Overriden method to save the form data.
     * All data is serialised in JSON to save the entire form_data
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean  True on success, False on error.
     */
    public function save($data)
    {
        // Todo: Pruning of empty subforms after switch

		$form_data = json_encode($data);
        $data['form_data'] = $form_data;

        return parent::save($data);
    }
}
