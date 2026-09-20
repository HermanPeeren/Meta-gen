<?php

/**
 * @package     Metagen
 * @subpackage  Controller
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Metagen\Administrator\Controller;

use Joomla\CMS\MVC\Controller\BaseController;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Where a request with no view of its own ends up.
 *
 * @since  0.1.0
 */
class DisplayController extends BaseController
{
	/**
	 * The view shown when none is asked for.
	 *
	 * @var    string
	 * @since  0.1.0
	 */
	protected $default_view = 'generators';
}
