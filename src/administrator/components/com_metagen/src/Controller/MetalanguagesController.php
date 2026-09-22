<?php

/**
 * @package     Metagen

 * @subpackage  Metagen component
 * @version     0.8.0
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren, 2023. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Metagen\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * Project forms list controller class.
 */
class MetalanguagesController extends AdminController
{
	/**
     * Constructor.
     *
     * @param   array                         $config   An optional associative array of configuration settings.
     *                                                  Recognized key values include 'name', 'default_task', 'model_path', and
     *                                                  'view_path' (this list is not meant to be comprehensive).
     * @param   MVCFactoryInterface|null      $factory  The factory.
     * @param   CMSApplication|null           $app      The JApplication for the dispatcher
     * @param   Input|null                    $input    Input
	 */
	public function __construct($config = array(), MVCFactoryInterface $factory = null, $app = null, $input = null)
	{
		parent::__construct($config, $factory, $app, $input);
	}

	/**
	 * Proxy for getModel.
	 *
	 * @param   string  $name    The name of the model.
	 * @param   string  $prefix  The prefix for the PHP class name.
	 * @param   array   $config  Array of configuration parameters.
	 *
	 * @return  BaseDatabaseModel
	 */
	public function getModel($name = 'Metalanguages', $prefix = '', $config = array('ignore_request' => true))
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Read a metalanguage in from a LionWeb chunk.
	 *
	 * A language written anywhere that speaks LionWeb becomes a metalanguage
	 * here, and from there it is a metalanguage like any other: forms are
	 * generated from it, a package carries it, Exten-gen imports that. Nothing
	 * downstream can tell where it came from, which is the point.
	 *
	 * @return  void
	 */
	public function importLionweb()
	{
		$this->checkToken();

		$path = trim((string) $this->input->getString('chunk', ''));
		$back = 'index.php?option=com_metagen&view=metalanguages';

		if ($path === '') {
			$this->setRedirect(
				Route::_($back, false),
				Text::_('COM_METAGEN_LIONWEB_NO_PATH'),
				'warning'
			);

			return;
		}

		/** @var \Yepr\Component\Metagen\Administrator\Model\LionwebModel $model */
		$model = $this->getModel('Lionweb', '', ['ignore_request' => true]);

		try {
			$converted = $model->convert($model->readChunk($path));
			$id        = $model->store($converted);

			// Said before the success message, because a language that converted
			// with something missing is still one somebody is about to generate
			// forms from.
			foreach ($converted['diagnostics'] as $diagnostic) {
				$this->app->enqueueMessage(
					$diagnostic['message'],
					$diagnostic['severity'] === 'error' ? 'error' : 'warning'
				);
			}

			$this->setRedirect(
				Route::_('index.php?option=com_metagen&task=metalanguage.edit&id=' . $id, false),
				Text::sprintf(
					'COM_METAGEN_LIONWEB_IMPORTED',
					$converted['name'],
					$converted['version'],
					$converted['entities']
				)
			);
		} catch (\Throwable $e) {
			$this->setRedirect(Route::_($back, false), $e->getMessage(), 'error');
		}
	}
}
