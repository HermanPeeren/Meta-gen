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

defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Yepr\Component\Metagen\Administrator\Model\GenerateFormsModel;
use Yepr\Gen\Core\Output\ZipWriter;

/**
 * Controller for a single project form
 */
class MetalanguageController extends FormController
{
	/**
	 * Override constructor to indicate the right list-view
	 *
	 * Alternatieve: Add $this->applyReturnUrl(); See Nic's PostController + ReturnURLAware mixin
	 */
	public function __construct($config = array(), MVCFactoryInterface $factory = null, $app = null, $input = null)
	{
		$this->view_list = 'metalanguages';
		parent::__construct($config, $factory, $app, $input);
	}

	/**
	 * Send this language away as a package: step 3.3.
	 *
	 * The same run the generate screen makes, to a different destination. It
	 * writes nothing under the component - a download is not a build - so the
	 * archive is assembled in the system temp directory and removed as soon as
	 * it has been sent.
	 *
	 * **The token is checked although this changes nothing.** A GET that runs a
	 * whole generation is worth a few hundred milliseconds of somebody else's
	 * server on every image tag that points at it, and the link in the list
	 * carries a token anyway.
	 *
	 * @return  void
	 */
	public function export()
	{
		$this->checkToken('get');

		$app = $this->app;

		if (!$app->getIdentity()->authorise('core.manage', 'com_metagen')) {
			throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		/** @var GenerateFormsModel $model */
		$model = $this->getModel('GenerateForms', 'Administrator', ['ignore_request' => true]);

		$model->setMetalanguageId($app->getInput()->getInt('id', 0));

		try {
			$language = $model->conceptModel();
			$files    = $model->package($language);
		} catch (\RuntimeException $e) {
			$app->enqueueMessage($e->getMessage(), 'error');
			$this->setRedirect(Route::_('index.php?option=com_metagen&view=metalanguages', false));

			return;
		}

		$name    = $model->packageName($language);
		$archive = tempnam(sys_get_temp_dir(), 'metagen') . '.zip';

		(new ZipWriter())->write($files, $archive);

		$app->setHeader('Content-Type', 'application/zip', true);
		$app->setHeader('Content-Disposition', 'attachment; filename="' . $name . '"', true);
		$app->setHeader('Content-Length', (string) filesize($archive), true);
		// A generated package is the current state of a model somebody is
		// editing, so a cached copy of it is a lie the moment they save.
		$app->setHeader('Cache-Control', 'no-store', true);
		$app->sendHeaders();

		readfile($archive);
		@unlink($archive);

		$app->close();
	}

	/**
	 * Method to run batch operations.
	 *
	 * @param   object|null  $model  The model.
	 *
	 * @return  boolean   True on success
	 */
	public function batch($model = null)
	{
		$this->checkToken();

		$model = $this->getModel('Metalanguage', '', array());

		// Preset the redirect
		$this->setRedirect(Route::_('index.php?option=com_metagen&view=metalanguages' . $this->getRedirectToListAppend(), false));

		return parent::batch($model);
	}
}
