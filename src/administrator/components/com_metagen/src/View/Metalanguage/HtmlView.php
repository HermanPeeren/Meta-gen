<?php

/**
 * @package     Metagen

 * @subpackage  Metagen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Metagen\Administrator\View\Metalanguage;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Associations;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

use Joomla\CMS\Form\Form;

/**
 * View to edit a project.
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * The Form object
	 *
	 * @var  Form
	 */
	protected $form;

	/**
	 * The active item
	 *
	 * @var  object
	 */
	protected $item;

	/**
	 * Display the view.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  mixed  A string if successful, otherwise an Error object.
	 */
	public function display($tpl = null)
	{
		/** @var \Yepr\Component\Metagen\Administrator\Model\MetalanguageModel $model */
		$model = $this->getModel();

		$this->item = $model->getItem();

		// Everything in this metalanguage that a reference field can point at,
		// in the page once. <metagen-reference> reads it from here and adds
		// whatever the form holds that the server has not seen yet.
		$this->getDocument()->addScriptOptions('yepr.references', $model->getReferenceIndex());

		// If we are forcing a language in modal (used for associations).
		if ($this->getLayout() === 'modal' && $forcedLanguage = Factory::getApplication()->getInput()->get('forcedLanguage', '', 'cmd')) {
			// Set the language field to the forcedLanguage and disable changing it.
			$this->form->setValue('language', null, $forcedLanguage);
			$this->form->setFieldAttribute('language', 'readonly', 'true');

			// Only allow to select categories with All language or with the forced language.
			$this->form->setFieldAttribute('catid', 'language', '*,' . $forcedLanguage);
		}

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Add the page title and toolbar.
	 *
	 * @return  void
	 */
	protected function addToolbar()
	{
		Factory::getApplication()->getInput()->set('hidemainmenu', true);

		$user = $this->getCurrentUser();
		$userId = $user->id;
		$isNew = ($this->item->id == 0);

		ToolbarHelper::title($isNew ? Text::_('COM_METAGEN_MANAGER_METALANGUAGE_NEW') : Text::_('COM_METAGEN_MANAGER_METALANGUAGE_EDIT'), 'project');

		// Since we don't track these assets at the item level, use the category id.
		$canDo = ContentHelper::getActions('com_metagen'); //, 'category', $this->item->catid

		// Build the actions for new and existing records.
		if ($isNew) {
			// For new records, check the create permission.
			//if ($isNew && (count($user->getAuthorisedCategories('com_metagen', 'core.create')) > 0))
			//{
				ToolbarHelper::apply('metalanguage.apply');

				ToolbarHelper::saveGroup(
					[
						['save', 'metalanguage.save'],
						['save2new', 'metalanguage.save2new']
					],
					'btn-success'
				);
			//}

			ToolbarHelper::cancel('metalanguage.cancel'); // TODO: I want 'Close' on the button, not 'Cancel'
		} else {
			// Since it's an existing record, check the edit permission, or fall back to edit own if the owner.
			//$itemEditable = $canDo->get('core.edit') || ($canDo->get('core.edit.own') && $this->item->created_by == $userId);

			$toolbarButtons = [];

			// Can't save the record if it's not editable
			//if ($itemEditable)
			//{
				ToolbarHelper::apply('metalanguage.apply');

				$toolbarButtons[] = ['save', 'metalanguage.save'];

				// We can save this record, but check the create permission to see if we can return to make a new one.
				if ($canDo->get('core.create')) {
					$toolbarButtons[] = ['save2new', 'metalanguage.save2new'];
				}
			//}

			// If checked out, we can still save
			if ($canDo->get('core.create')) {
				$toolbarButtons[] = ['save2copy', 'metalanguage.save2copy'];
			}

			ToolbarHelper::saveGroup(
				$toolbarButtons,
				'btn-success'
			);

			/*if (Associations::isEnabled() && ComponentHelper::isEnabled('com_associations'))
			{
				ToolbarHelper::custom('project.editAssociations', 'contract', 'contract', 'JTOOLBAR_ASSOCIATIONS', false, false);
			}*/

			ToolbarHelper::cancel('metalanguage.cancel', 'JTOOLBAR_CLOSE'); // TODO: I want 'Close' on the button, not 'Cancel'
		}

		ToolbarHelper::divider();
		ToolbarHelper::help('', false, 'https://yepr.nl');
	}
}
