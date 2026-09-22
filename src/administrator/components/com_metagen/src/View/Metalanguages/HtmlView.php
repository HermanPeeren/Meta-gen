<?php

/**
 * @package     Metagen

 * @subpackage  Metagen component
 * @version     0.9.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Metagen\Administrator\View\Metalanguages;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\MVC\View\GenericDataException;

/**
 * View class for a list of metalanguages.
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * An array of items
	 *
	 * @var  array
	 */
	protected $items;

	/**
	 * The pagination object
	 *
	 * @var  Pagination
	 */
	protected $pagination;

	/**
	 * The model state
	 *
	 * @var  \Joomla\Registry\Registry
	 */
	protected $state;

	/**
	 * Form object for search filters
	 *
	 * @var  \Joomla\CMS\Form\Form
	 */
	public $filterForm;

	/**
	 * The active search filters
	 *
	 * @var  array
	 */
	public $activeFilters;

	/**
	 * The sidebar markup
	 *
	 * @var  string
	 */
	protected $sidebar;

	/**
	 * Method to display the view.
	 *
	 * @param   string  $tpl  A template file to load. [optional]
	 *
	 * @return  void
     * @throws Genericdataexception
	 */
	public function display($tpl = null): void
	{
		/** @var \Yepr\Component\Metagen\Administrator\Model\MetalanguagesModel $model */
		$model = $this->getModel();

		// Ask the model to throw rather than collect errors. This is what
		// retires the getErrors() check below it: setError() and getError()
		// go in Joomla 7, and a model that throws says what went wrong at
		// the point it went wrong.
		$model->setUseExceptions(true);

		$this->items         = $model->getItems();
		$this->pagination    = $model->getPagination();
		$this->filterForm    = $model->getFilterForm();
		$this->activeFilters = $model->getActiveFilters();
		$this->state         = $model->getState();

        /*if (!\count($this->items) && $this->isEmptyState = $this->get('IsEmptyState')) {
            $this->setLayout('emptystate');*/


		// Preprocess the list of items to find ordering divisions.
		// TODO: Complete the ordering stuff with nested sets
		foreach ($this->items as &$item) {
			$item->order_up = true;
			$item->order_dn = true;
		}

		// We don't need toolbar in the modal window.
		if ($this->getLayout() !== 'modal') {
			$this->addToolbar();
			$this->sidebar = Sidebar::render();
		} else {
			// In project associations modal we need to remove language filter if forcing a language.
			// We also need to change the category filter to show show categories with All or the forced language.
			if ($forcedLanguage = Factory::getApplication()->getInput()->get('forcedLanguage', '', 'CMD')) {
				// If the language is forced we can't allow to select the language, so transform the language selector filter into a hidden field.
				$languageXml = new \SimpleXMLElement('<field name="language" type="hidden" default="' . $forcedLanguage . '" />');
				$this->filterForm->setField($languageXml, 'filter', true);

				// Also, unset the active language filter so the search tools is not open by default with this filter.
				unset($this->activeFilters['language']);

				// One last changes needed is to change the category filter to just show categories with All language or with the forced language.
				$this->filterForm->setFieldAttribute('category_id', 'language', '*,' . $forcedLanguage, 'filter');
			}
		}

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 */
	protected function addToolbar()
	{
		$canDo = ContentHelper::getActions('com_metagen', 'category', $this->state->get('filter.category_id'));
		$user  = $this->getCurrentUser();

		// Get the toolbar object instance
		$toolbar = $this->getDocument()->getToolbar();

		ToolbarHelper::title(Text::_('COM_METAGEN_MANAGER_METALANGUAGES'), 'metalanguages');

		if ($canDo->get('core.create') || count($user->getAuthorisedCategories('com_metagen', 'core.create')) > 0) {
			$toolbar->addNew('metalanguage.add');

			// Creating a metalanguage, by reading one somebody else wrote
			// rather than by typing it, so it belongs beside New.
			$toolbar->standardButton('upload', 'COM_METAGEN_LIONWEB_IMPORT', 'metalanguages.importLionweb')
				->icon('icon-download')
				->listCheck(false);
		}

		if ($canDo->get('core.edit.state')) {
			$dropdown = $toolbar->dropdownButton('status-group')
				->text('JTOOLBAR_CHANGE_STATUS')
				->toggleSplit(false)
				->icon('fa fa-globe')
				->buttonClass('btn btn-info')
				->listCheck(true);

			$childBar = $dropdown->getChildToolbar();

			//$childBar->publish('projects.publish')->listCheck(true);

			//$childBar->unpublish('projects.unpublish')->listCheck(true);

			//$childBar->archive('projects.archive')->listCheck(true);

			if ($user->authorise('core.admin')) {
				$childBar->checkin('metalanguages.checkin')->listCheck(true);
			}

			if ($this->state->get('filter.published') != -2) {
				$childBar->trash('metagen.trash')->listCheck(true);
			}
		}

		$toolbar->popupButton('batch')
			->text('JTOOLBAR_BATCH')
			->selector('collapseModal')
			->listCheck(true);

		if ($this->state->get('filter.published') == -2 && $canDo->get('core.delete')) {
			$toolbar->delete('metalanguages.delete')
				->text('JTOOLBAR_EMPTY_TRASH')
				->message('JGLOBAL_CONFIRM_DELETE')
				->listCheck(true);
		}

		if ($user->authorise('core.admin', 'com_metagen') || $user->authorise('core.options', 'com_metagen')) {
			$toolbar->preferences('com_metagen');
		}

		ToolbarHelper::divider();
		ToolbarHelper::help('', false, 'https://yepr.nl');

		HTMLHelper::_('sidebar.setAction', 'index.php?option=com_metagen&view=metalanguages');
	}

	/**
	 * Returns an array of fields the table can be sorted by
	 *
	 * @return  array  Array containing the field name to sort by as the key and display text as value
	 */
	protected function getSortFields()
	{
		return array(
			'a.ordering'     => Text::_('JGRID_HEADING_ORDERING'),
			'a.published'    => Text::_('JSTATUS'),
			'a.name'         => Text::_('JGLOBAL_TITLE'),
			'category_title' => Text::_('JCATEGORY'),
			'a.access'       => Text::_('JGRID_HEADING_ACCESS'),
			'a.language'     => Text::_('JGRID_HEADING_LANGUAGE'),
			'a.id'           => Text::_('JGRID_HEADING_ID'),
		);
	}
}
