<?php

/**
 * @package     Metagen

 * @subpackage  Metagen component
 * @version     0.8.0
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
use Yepr\Component\Metagen\Administrator\Repository\MetalanguageRepository;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\String\PunycodeHelper;
use Joomla\CMS\Table\TableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\UCM\UCMType;
use Joomla\CMS\Versioning\VersionableModelTrait;
use Joomla\CMS\Workflow\Workflow;
use Joomla\Component\Categories\Administrator\Helper\CategoriesHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;



/**
 * Forms Diagram Model: to get the project form data from the db
 */
class FormsDiagramModel extends AdminModel
{
	/**
	 * The (internal) id of the project forms definition from which we generate form files
	 *
	 * @var   int|Integer
	 */
	protected int $metalanguageId;
	/**
	 * Set the project id.
	 *
	 * @param   int  $metalanguageId
	 */
	public function setMetalanguageId(int $metalanguageId): void
	{
		$this->metalanguageId = $metalanguageId;
	}

	/**
	 * Get the (json-encoded) form-data of the project that form the AST.
	 * todo: use this as private method and query the PlantUML-stuff from this model
	 *
	 * @return object the AST
	 */
	public function getAST(): object
    {

		// The id is set on this model by the controller, not taken from the
		// request: the model is told which record it is working on.
		$id = (int) $this->metalanguageId;
$model = (new MetalanguageRepository($this->getDatabase()))->findRaw($id);
if ($model === null) {
// The declared return type is not nullable, so without this a
			// missing or unreadable record arrives as a TypeError with
			// nothing in it to act on.
			throw new \RuntimeException(sprintf('Cannot read project form %d.', $id));
}

		return $model;
    }

	/**
	 * The table this model reads, which is the Metalanguage table.
	 *
	 * This replaced a copy of AdminModel::getItem() whose only reason to
	 * exist was the same one: without it, AdminModel asks for a table
	 * named after the model - ERDTable, GenerateTable - and there is no
	 * such thing. Saying so here is one line instead of thirty, and it
	 * leaves getItem() to the parent, which is where the behaviour was
	 * copied from in the first place.
	 *
	 * @param   string  $name     The table name.
	 * @param   string  $prefix   The class prefix.
	 * @param   array   $options  Configuration for the table.
	 *
	 * @return  \Joomla\CMS\Table\Table
	 */
	public function getTable($name = 'Metalanguage', $prefix = 'Administrator', $options = [])
	{
		return parent::getTable($name, $prefix, $options);
	}


	/**
	 * NOT USED ATM. BUT MUST BE IMPLEMENTED. MIGHT USE IN FUTURE.
	 * Method to get the row form.
	 *
	 * @param   array    $data      Data for the form
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not
	 *
	 * @return  Form|boolean  A Form object on success, false on failure
	 */
	public function getForm($data = array(), $loadData = true)
	{
		return false;
	}
}
