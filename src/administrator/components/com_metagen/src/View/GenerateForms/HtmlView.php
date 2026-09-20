<?php

/**
 * @package     Extesion Generator

 * @subpackage  Metagen component
 * @version     0.9.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Metagen\Administrator\View\GenerateForms;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Pagination\Pagination;


/**
 * View class for a list of projects.
 */
class HtmlView extends BaseHtmlView
{
	/**
	 * Method to display the view.
	 *
	 * @param   string  $tpl  A template file to load. [optional]
	 *
	 * @return  void
	 */
	public function display($tpl = null): void
	{
        /** @var \Yepr\Component\Metagen\Administrator\Model\GenerateFormsModel $model */
        $model = $this->getModel();

		// Get the project_id and put it in the model
		// Todo: this must be done in the (display)controller and probably best via UserState
		$projectId = Factory::getApplication()->getInput()->getInt('metalanguage_id');
		$model->setMetalanguageId($projectId);

				$model->generate();
		$log = $model->log;
		$text = implode("<br />\n", $log);
		echo '<h2>Generation log of these project forms</h2>';
		echo '<p>' . $text . '</p>';

		//parent::display($tpl);
	}
}
